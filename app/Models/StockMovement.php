<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockMovement extends Model
{
    protected $fillable = [
        'product_batch_id',
        'user_id',
        'quantity',
        'type',
        'remarks',
        'source_type',
        'source_id',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the batch associated with this stock movement.
     */
    public function productBatch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class);
    }

    /**
     * Get the user who performed this stock movement.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The transaction that caused this movement (GoodsReceipt, DeliveryReceipt,
     * or InventoryAdjustment) — powers the Customer/Supplier columns on the
     * Product History view.
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope to filter by type 'in' (stock-in/restock).
     */
    public function scopeIn($query)
    {
        return $query->where('type', 'in');
    }

    /**
     * Scope to filter by type 'out' (stock-out/deduction).
     */
    public function scopeOut($query)
    {
        return $query->where('type', 'out');
    }

    /**
     * Get the absolute value of quantity movement.
     */
    public function getAbsoluteQuantityAttribute()
    {
        return abs($this->quantity);
    }
}
