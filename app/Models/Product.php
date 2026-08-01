<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

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

    /**
     * All location_stocks rows across every batch of this product — the
     * single relation nearly every quantity read below sums or filters.
     */
    public function locationStocks(): HasManyThrough
    {
        return $this->hasManyThrough(LocationStock::class, ProductBatch::class);
    }

    /**
     * Total on-hand qty across every location (Warehouse + POS + any
     * future location) — unchanged meaning from before locations existed.
     */
    public function getQuantityAttribute(): int
    {
        return (int) $this->locationStocks()->sum('qty');
    }

    /**
     * On-hand qty at a specific location only (e.g. Location::pos()->id for
     * what's actually sellable at POS).
     */
    public function quantityAtLocation(int $locationId): int
    {
        return (int) $this->locationStocks()->where('location_id', $locationId)->sum('qty');
    }

    public function getReservedQtyAttribute(): int
    {
        return (int) $this->locationStocks()->sum('reserved_qty');
    }

    public function getAvailableQtyAttribute(): int
    {
        return $this->quantity - $this->reserved_qty;
    }

    public function isLowOnStock(): bool
    {
        return $this->quantity <= $this->low_stock_threshold;
    }

    /**
     * Low stock is evaluated against total on-hand qty across all locations
     * — POS running low while the Warehouse is full just means a transfer
     * is needed, not a reorder from the supplier.
     */
    public function scopeLowStock($query)
    {
        return $query->whereRaw(
            '(select coalesce(sum(ls.qty), 0) from location_stocks ls
                inner join product_batches pb on pb.id = ls.product_batch_id
                where pb.product_id = products.id) <= products.low_stock_threshold'
        );
    }

    public function scopeOutOfStock($query)
    {
        return $query->whereRaw(
            '(select coalesce(sum(ls.qty), 0) from location_stocks ls
                inner join product_batches pb on pb.id = ls.product_batch_id
                where pb.product_id = products.id) <= 0'
        );
    }
}
