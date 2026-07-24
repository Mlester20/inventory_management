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
        'quantity',
        'return_date',
        'reason',
        'notes',
        'status'
    ];

    public function productBatch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
