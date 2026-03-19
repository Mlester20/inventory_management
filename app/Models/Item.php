<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    use HasFactory;

    protected $fillable = ['item_name', 'category_id', 'supplier_id', 'description', 'quantity', 'unit_price', 'low_stock_threshold'];

    protected $casts = [
        'quantity' => 'integer',
        'low_stock_threshold' => 'integer',
        'unit_price' => 'decimal:2',
    ];

    /**
     * Get the supplier associated with this item.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Get the category associated with this item.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get all purchases associated with this item.
     */
    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    /**
     * Get all stock movements for this item.
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Check if item is low on stock.
     */
    public function isLowOnStock(): bool
    {
        return $this->quantity <= $this->low_stock_threshold;
    }

    /**
     * Get stock-in movements for this item.
     */
    public function restockMovements(): HasMany
    {
        return $this->stockMovements()->where('type', 'in');
    }

    /**
     * Get stock-out movements for this item.
     */
    public function deductionMovements(): HasMany
    {
        return $this->stockMovements()->where('type', 'out');
    }

    /**
     * Get total quantity restocked.
     */
    public function getTotalRestockedAttribute(): int
    {
        return (int) $this->restockMovements()->sum('quantity');
    }

    /**
     * Get total quantity deducted (in absolute value).
     */
    public function getTotalDeductedAttribute(): int
    {
        return (int) abs($this->deductionMovements()->sum('quantity'));
    }

    /**
     * Scope to get low stock items.
     */
    public function scopeLowStock($query)
    {
        return $query->whereRaw('quantity <= low_stock_threshold');
    }

    /**
     * Scope to get out of stock items.
     */
    public function scopeOutOfStock($query)
    {
        return $query->where('quantity', '<=', 0);
    }
}
