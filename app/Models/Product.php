<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'generic_name_id', 'brand_name', 'item_name', 'category_id', 'supplier_id',
        'description', 'barcode', 'unit_cost',
        'unit_price_percent', 'unit_price',
        'wholesale_percent', 'wholesale_price',
        'price_1_percent', 'price_1',
        'price_2_percent', 'price_2',
        'price_3_percent', 'price_3',
        'fda_reg_no', 'fda_reg_exp',
        'custom_field_1', 'custom_field_2', 'custom_field_3', 'custom_field_4',
        'location', 'low_stock_threshold', 'image', 'tax_id',
    ];

    protected $casts = [
        'low_stock_threshold' => 'integer',
        'unit_cost' => 'decimal:2',
        'unit_price_percent' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'wholesale_percent' => 'decimal:2',
        'wholesale_price' => 'decimal:2',
        'price_1_percent' => 'decimal:2',
        'price_1' => 'decimal:2',
        'price_2_percent' => 'decimal:2',
        'price_2' => 'decimal:2',
        'price_3_percent' => 'decimal:2',
        'price_3' => 'decimal:2',
        'fda_reg_exp' => 'date',
    ];

    /**
     * Keep category_id and item_name in sync with the selected Generic Name,
     * mirroring the legacy Item model's behavior so existing joins/reports
     * that group by products.category_id / products.item_name keep working.
     */
    protected static function booted(): void
    {
        static::saving(function (Product $product) {
            if (! $product->generic_name_id) {
                return;
            }

            $genericName = $product->genericName()->first();

            if ($genericName) {
                $product->category_id = $genericName->category_id;
                $product->item_name = $product->brand_name
                    ? "{$genericName->generic_name} ({$product->brand_name})"
                    : $genericName->generic_name;
            }
        });
    }

    public function genericName(): BelongsTo
    {
        return $this->belongsTo(GenericName::class);
    }

    public function tax(): BelongsTo
    {
        return $this->belongsTo(Taxes::class, 'tax_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(ProductBatch::class);
    }

    public function getQuantityAttribute(): int
    {
        return (int) $this->batches()->sum('qty');
    }

    public function getReservedQtyAttribute(): int
    {
        return (int) $this->batches()->sum('reserved_qty');
    }

    public function getAvailableQtyAttribute(): int
    {
        return $this->quantity - $this->reserved_qty;
    }

    public function isLowOnStock(): bool
    {
        return $this->quantity <= $this->low_stock_threshold;
    }

    public function scopeLowStock($query)
    {
        return $query->whereRaw(
            '(select coalesce(sum(qty), 0) from product_batches where product_batches.product_id = products.id) <= products.low_stock_threshold'
        );
    }

    public function scopeOutOfStock($query)
    {
        return $query->whereRaw(
            '(select coalesce(sum(qty), 0) from product_batches where product_batches.product_id = products.id) <= 0'
        );
    }
}
