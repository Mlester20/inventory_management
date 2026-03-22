<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Purchase extends Model
{
    protected $fillable = [
        'item_id',
        'user_id',
        'quantity_sold',
        'unit_price',
        'total_price',
        'purchase_date',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'total_price' => 'decimal:2',
        'unit_price' => 'decimal:2',
    ];

    /**
     * Get the item associated with this purchase.
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Get the cashier/staff member who processed this purchase.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to filter purchases within a date range.
     * Uses the purchase_date column.
     */
    public function scopeDateRange($query, ?string $start, ?string $end)
    {
        if ($start && $end) {
            return $query->whereBetween('purchase_date', [$start, $end]);
        }
        return $query;
    }

    /**
     * Scope to get total COGS as a single aggregated value.
     */
    public function scopeTotalCogs($query)
    {
        return $query->selectRaw('SUM(quantity_sold * unit_price) as cogs');
    }
}
