<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryReceipt extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'customer_id',
        'sales_order_id',
        'split_from_delivery_receipt_id',
        'dr_no',
        'transaction_type',
        'status',
        'is_draft',
        'description',
        'receipt_date',
        'prepared_by',
        'archived_at',
        'cancellation_note',
    ];

    protected $casts = [
        'receipt_date' => 'date',
        'is_draft' => 'boolean',
        'archived_at' => 'datetime',
    ];

    public const TRANSACTION_TYPES = [
        'advance_order' => 'ADVANCE ORDER',
        'purchase_order' => 'PURCHASE ORDER',
        'walk_in' => 'WALK-IN',
    ];

    public const STATUSES = [
        'for_delivery' => 'FOR DELIVERY',
        'delivered' => 'DELIVERED',
        'cancelled' => 'CANCELLED',
    ];

    public function isDraft(): bool
    {
        return (bool) $this->is_draft;
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * COMPLETE once every line is fully invoiced, PARTIALLY INVOICED once
     * any line has some invoiced qty, otherwise NOT INVOICED.
     */
    public function getInvoiceStatusAttribute(): string
    {
        $items = $this->items;

        return match (true) {
            $items->isNotEmpty() && $items->every(fn (DeliveryReceiptItem $item) => $item->invoiced_qty >= $item->qty) => 'COMPLETE',
            $items->contains(fn (DeliveryReceiptItem $item) => $item->invoiced_qty > 0) => 'PARTIALLY INVOICED',
            default => 'NOT INVOICED',
        };
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    /**
     * Goods the customer sent back from this Delivery Receipt, by product: approved Return Items plus
     * posted "Stock In - Returned by Customer" adjustments that point at it. Only a marker for the DR
     * and SO screens — the delivered quantities themselves are not changed by a return.
     *
     * @return array<int,array{name:string,qty:int,refs:array<int,string>}>
     */
    public function returnedByProduct(): array
    {
        $out = [];
        $add = function (?Product $product, int $qty, string $ref) use (&$out) {
            if (! $product || $qty <= 0) {
                return;
            }

            $out[$product->id] ??= ['name' => $product->display_description, 'qty' => 0, 'refs' => []];
            $out[$product->id]['qty'] += $qty;
            $out[$product->id]['refs'][] = $ref;
        };

        $returns = ReturnItem::where('delivery_receipt_id', $this->id)
            ->where('is_draft', false)->where('status', 'approved')
            ->with('productBatch.product')->get();
        foreach ($returns as $return) {
            $add($return->productBatch?->product, (int) $return->quantity, $return->reference);
        }

        $adjustments = InventoryAdjustment::where('delivery_receipt_id', $this->id)
            ->where('adjustment_type', InventoryAdjustment::CUSTOMER_RETURN)->where('status', 'posted')
            ->with('lines.product')->get();
        foreach ($adjustments as $adjustment) {
            foreach ($adjustment->lines as $line) {
                $add($line->product, (int) $line->qty, $adjustment->adjustment_no);
            }
        }

        return $out;
    }

    /** Return Items recording goods a customer sent back from this Delivery Receipt. */
    public function returnItems(): HasMany
    {
        return $this->hasMany(ReturnItem::class);
    }

    /** Stock-in adjustments recording goods a customer sent back from this Delivery Receipt. */
    public function customerReturns(): HasMany
    {
        return $this->hasMany(InventoryAdjustment::class)->where('adjustment_type', InventoryAdjustment::CUSTOMER_RETURN);
    }

    /**
     * Who the goods go to. A draft saved from a Sales Order can have no customer of its own yet,
     * so it falls back to the Sales Order's customer — a Delivery Receipt is printed for review
     * long before it is posted.
     */
    public function deliverTo(): ?Customer
    {
        return $this->customer ?? $this->salesOrder?->customer;
    }

    /** The Advance Order DR this one was split off from when part of it was linked to a Sales Order. */
    public function splitFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'split_from_delivery_receipt_id')->withTrashed();
    }

    public function splitChildren(): HasMany
    {
        return $this->hasMany(self::class, 'split_from_delivery_receipt_id');
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    /** chaperone(): each line knows this very receipt, so line-level checks share one computation. */
    public function items(): HasMany
    {
        return $this->hasMany(DeliveryReceiptItem::class)->chaperone();
    }

    /** @var array<int,int>|null line id => quantity already sent back, computed once per receipt */
    protected ?array $returnAllocation = null;

    /**
     * How much of this line was sent back (or is on its way back), so it can no longer be invoiced.
     * Returns are per product on the receipt; when the same item sits on several lines they are taken
     * off the last lines first.
     */
    public function returnedQtyForLine(DeliveryReceiptItem $line): int
    {
        $this->returnAllocation ??= $this->allocateReturnsToLines();

        return $this->returnAllocation[$line->id] ?? 0;
    }

    /** @return array<int,int> */
    protected function allocateReturnsToLines(): array
    {
        $returned = [];
        $add = function (?int $productId, int $qty) use (&$returned) {
            if ($productId && $qty > 0) {
                $returned[$productId] = ($returned[$productId] ?? 0) + $qty;
            }
        };

        // Pending counts too: goods that came back must not be billed while the return is being approved.
        foreach (ReturnItem::where('delivery_receipt_id', $this->id)->where('is_draft', false)->where('status', '!=', 'rejected')->with('productBatch')->get() as $return) {
            $add($return->productBatch?->product_id, (int) $return->quantity);
        }
        foreach (InventoryAdjustment::where('delivery_receipt_id', $this->id)->where('adjustment_type', InventoryAdjustment::CUSTOMER_RETURN)->where('status', 'posted')->with('lines')->get() as $adjustment) {
            foreach ($adjustment->lines as $adjustmentLine) {
                $add($adjustmentLine->product_id, (int) $adjustmentLine->qty);
            }
        }

        if ($returned === []) {
            return [];
        }

        $allocation = [];
        $lines = $this->items()->with('productBatch')->get()->sortByDesc('id');
        foreach ($lines as $line) {
            $productId = $line->productBatch?->product_id;
            $left = $returned[$productId] ?? 0;
            if ($left <= 0) {
                continue;
            }

            $take = min($left, (int) $line->qty);
            $allocation[$line->id] = $take;
            $returned[$productId] = $left - $take;
        }

        return $allocation;
    }
}
