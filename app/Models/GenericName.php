<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GenericName extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'generic_name', 'category_id', 'unit', 'vat_type'];

    public const VAT_TYPES = [
        'VAT' => 'VAT',
        'VAT-EX' => 'VAT-EX',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
