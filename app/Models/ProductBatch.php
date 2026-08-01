<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id', 'batch_no', 'expiration_date',
    ];

    protected $casts = [
        'expiration_date' => 'date',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function restockMovements(): HasMany
    {
        return $this->stockMovements()->where('type', 'in');
    }

    public function deductionMovements(): HasMany
    {
        return $this->stockMovements()->where('type', 'out');
    }

    public function locationStocks(): HasMany
    {
        return $this->hasMany(LocationStock::class);
    }

    /**
     * This batch's qty at a specific location. Uses the eager-loaded
     * locationStocks collection when available (avoids N+1 in list views);
     * falls back to a query otherwise.
     */
    public function qtyAtLocation(int $locationId): int
    {
        if ($this->relationLoaded('locationStocks')) {
            return (int) ($this->locationStocks->firstWhere('location_id', $locationId)->qty ?? 0);
        }

        return (int) $this->locationStocks()->where('location_id', $locationId)->value('qty');
    }

    /**
     * This batch's total qty across every location.
     */
    public function getTotalQtyAttribute(): int
    {
        if ($this->relationLoaded('locationStocks')) {
            return (int) $this->locationStocks->sum('qty');
        }

        return (int) $this->locationStocks()->sum('qty');
    }

    /**
     * Batches with stock on hand at the given location, nearest-expiring
     * first (nulls last), for FEFO (first-expired-first-out) auto-selection
     * at POS/Invoice checkout.
     */
    public function scopeAvailableFefo($query, int $locationId)
    {
        return $query->whereHas('locationStocks', function ($q) use ($locationId) {
                $q->where('location_id', $locationId)->where('qty', '>', 0);
            })
            ->with(['locationStocks' => function ($q) use ($locationId) {
                $q->where('location_id', $locationId);
            }])
            ->orderByRaw('expiration_date IS NULL, expiration_date ASC');
    }
}
