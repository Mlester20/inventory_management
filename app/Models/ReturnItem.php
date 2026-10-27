<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_batch_id',
        'user_id',
        'prepared_by',
        'customer_id',
        'delivery_receipt_id',
        'quantity',
        'return_date',
        'reason',
        'notes',
        'status',
        'is_draft',
        'refund_method',
        'refund_amount',
        'stock_disposition',
        'stock_disposal_id',
    ];

    protected $casts = [
        'is_draft' => 'boolean',
    ];

    public function isDraft(): bool
    {
        return (bool) $this->is_draft;
    }

    public const REFUND_METHODS = [
        'credit' => 'Store Credit',
        'cash' => 'Cash Refund',
        'none' => 'No refund (not invoiced yet)',
    ];

    public const STOCK_DISPOSITIONS = [
        'sellable' => 'Restocked (Sellable)',
        'write_off' => 'Written Off',
    ];

    // Reasons where the item itself is presumed unsellable — the approval
    // form defaults Stock Disposition to "Write Off" for these, though the
    // admin can still override it after actually inspecting the item.
    public const REASONS_DEFAULTING_TO_WRITE_OFF = [
        'Defective Product',
        'Damaged During Delivery',
    ];

    public function suggestedStockDisposition(): string
    {
        return in_array($this->reason, self::REASONS_DEFAULTING_TO_WRITE_OFF, true) ? 'write_off' : 'sellable';
    }

    public function productBatch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class);
    }

    public function stockDisposal(): BelongsTo
    {
        return $this->belongsTo(StockDisposal::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** The Delivery Receipt the goods came back from (null for a POS or direct-invoice sale). */
    public function deliveryReceipt(): BelongsTo
    {
        return $this->belongsTo(DeliveryReceipt::class);
    }

    /** What Product History and the lists call this return, e.g. RET-00012. */
    public function getReferenceAttribute(): string
    {
        return 'RET-' . str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
    }

    /**
     * How much of this return's item was already invoiced on its Delivery Receipt. A return that
     * is linked to a DR only earns a refund for the part that was actually billed.
     */
    public function invoicedQtyOnDeliveryReceipt(): int
    {
        if (! $this->delivery_receipt_id || ! $this->productBatch) {
            return 0;
        }

        return (int) DeliveryReceiptItem::where('delivery_receipt_id', $this->delivery_receipt_id)
            ->whereHas('productBatch', fn ($q) => $q->where('product_id', $this->productBatch->product_id))
            ->sum('invoiced_qty');
    }

    /** Goods from a Delivery Receipt that was never invoiced have nothing to refund. */
    public function isRefundable(): bool
    {
        return ! $this->delivery_receipt_id || $this->invoicedQtyOnDeliveryReceipt() > 0;
    }

    /**
     * Scope to filter only approved returns.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope to get total value of approved returns joined with products unit_price.
     */
    public function scopeTotalReturnValue($query)
    {
        return $query->join('product_batches', 'product_batches.id', '=', 'return_items.product_batch_id')
            ->join('products', 'products.id', '=', 'product_batches.product_id')
            ->selectRaw('SUM(return_items.quantity * products.unit_price) as return_value');
    }
}
