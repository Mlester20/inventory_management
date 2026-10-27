<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesOrder extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'customer_id',
        'sales_quote_id',
        'so_no',
        'po_no',
        'customer_po_date',
        'status',
        'is_draft',
        'order_date',
        'prepared_by',
        'notes',
        'archived_at',
    ];

    protected $casts = [
        'order_date' => 'date',
        'customer_po_date' => 'date',
        'is_draft' => 'boolean',
        'archived_at' => 'datetime',
    ];

    public function isDraft(): bool
    {
        return (bool) $this->is_draft;
    }

    /**
     * What came back from the Delivery Receipts of this order, by Sales Order line (each product on a DR
     * counted once per line it was delivered against).
     *
     * @return array<int,array{qty:int,refs:array<int,string>}>
     */
    public function returnedByItem(): array
    {
        $out = [];

        foreach (DeliveryReceipt::where('sales_order_id', $this->id)->with('items.productBatch')->get() as $deliveryReceipt) {
            $returned = $deliveryReceipt->returnedByProduct();
            if ($returned === []) {
                continue;
            }

            $counted = [];
            foreach ($deliveryReceipt->items as $line) {
                $productId = $line->productBatch?->product_id;
                $key = $line->sales_order_item_id . '|' . $productId;
                if (! $line->sales_order_item_id || ! isset($returned[$productId]) || isset($counted[$key])) {
                    continue;
                }
                $counted[$key] = true;

                $out[$line->sales_order_item_id] ??= ['qty' => 0, 'refs' => []];
                $out[$line->sales_order_item_id]['qty'] += $returned[$productId]['qty'];
                $out[$line->sales_order_item_id]['refs'] = array_merge($out[$line->sales_order_item_id]['refs'], $returned[$productId]['refs']);
            }
        }

        return $out;
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    /** completed / partially_delivered / open, as the delivered and advance quantities say. */
    public function deliveryStatus(): string
    {
        $items = $this->items()->get();

        return match (true) {
            $items->isNotEmpty() && $items->every(fn (SalesOrderItem $item) => $item->remaining_qty <= 0) => 'completed',
            $items->contains(fn (SalesOrderItem $item) => $item->delivered_qty > 0) => 'partially_delivered',
            default => 'open',
        };
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function salesQuote(): BelongsTo
    {
        return $this->belongsTo(SalesQuote::class);
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class);
    }

    public function deliveryReceipts(): HasMany
    {
        return $this->hasMany(DeliveryReceipt::class);
    }
}
