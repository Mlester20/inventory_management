<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    protected $fillable = [
        'item_id',
        'user_id',
        'quantity',
        'type',
        'remarks',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the item associated with this stock movement.
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Get the user who performed this stock movement.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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