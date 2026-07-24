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
        'product_id', 'batch_no', 'expiration_date', 'qty', 'reserved_qty',
    ];

    protected $casts = [
        'qty' => 'integer',
        'reserved_qty' => 'integer',
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

    public function getAvailableQtyAttribute(): int
    {
        return $this->qty - $this->reserved_qty;
    }

    /**
     * Batches with stock on hand, nearest-expiring first (nulls last),
     * for FEFO (first-expired-first-out) auto-selection at POS/Invoice checkout.
     */
    public function scopeAvailableFefo($query)
    {
        return $query->where('qty', '>', 0)
            ->orderByRaw('expiration_date IS NULL, expiration_date ASC');
    }
}
