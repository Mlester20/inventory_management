<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class GenericName extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['code', 'generic_name', 'category_id', 'unit', 'vat_type', 'product_type', 'archived_at'];

    protected $casts = [
        'archived_at' => 'datetime',
    ];

    public const VAT_TYPES = [
        'VAT' => 'VAT',
        'VAT-EX' => 'VAT-EX',
    ];

    public const PRODUCT_TYPES = [
        'goods' => 'Goods',
        'services' => 'Services',
    ];

    /**
     * Income-tax withholding rate (percent) each Product Type attracts — fixed
     * per Sir, so it is not stored anywhere: 1% on Goods, 2% on Services.
     */
    public const WITHHOLDING_RATES = [
        'goods' => 1,
        'services' => 2,
    ];

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
