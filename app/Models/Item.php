<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_name', 'serial_number', 'generic_name_id', 'brand_name', 'category_id', 'supplier_id',
        'description', 'quantity', 'unit_price', 'unit_cost',
        'wholesale_percent', 'wholesale_price',
        'price_1_percent', 'price_1',
        'price_2_percent', 'price_2',
        'price_3_percent', 'price_3',
        'location', 'low_stock_threshold', 'image', 'tax_id', 'batch_no', 'expiration_date',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'low_stock_threshold' => 'integer',
        'unit_price' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'wholesale_percent' => 'decimal:2',
        'wholesale_price' => 'decimal:2',
        'price_1_percent' => 'decimal:2',
        'price_1' => 'decimal:2',
        'price_2_percent' => 'decimal:2',
        'price_2' => 'decimal:2',
        'price_3_percent' => 'decimal:2',
        'price_3' => 'decimal:2',
        'expiration_date' => 'date',
    ];

    /**
     * Keep category_id and item_name in sync with the selected Generic Name,
     * so every screen that already reads those columns directly (COGS, the
     * Expiration Report, dashboard, POS, search) keeps working unchanged.
     */
    protected static function booted(): void
    {
        static::saving(function (Item $item) {
            if (! $item->generic_name_id) {
                return;
            }

            $genericName = $item->genericName()->first();

            if ($genericName) {
                $item->category_id = $genericName->category_id;
                $item->item_name = $item->brand_name
                    ? "{$genericName->generic_name} ({$item->brand_name})"
                    : $genericName->generic_name;
            }
        });
    }

    /**
     * Get the generic name this product belongs to.
     */
    public function genericName(): BelongsTo
    {
        return $this->belongsTo(GenericName::class);
    }

    /**
     * Get the tax associated with this item.
     */
    public function tax(): BelongsTo
    {
        return $this->belongsTo(Taxes::class, 'tax_id');
    }

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
     * Get all return items for this item.
     */
    public function returnItems(): HasMany
    {
        return $this->hasMany(ReturnItem::class);
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