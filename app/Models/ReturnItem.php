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
        'customer_id',
        'quantity',
        'return_date',
        'reason',
        'notes',
        'status',
        'refund_method',
        'refund_amount',
        'stock_disposition',
        'stock_disposal_id',
    ];

    public const REFUND_METHODS = [
        'credit' => 'Store Credit',
        'cash' => 'Cash Refund',
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
