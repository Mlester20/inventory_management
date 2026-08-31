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

    protected $fillable = ['code', 'generic_name', 'category_id', 'unit', 'vat_type', 'archived_at'];

    protected $casts = [
        'archived_at' => 'datetime',
    ];

    public const VAT_TYPES = [
        'VAT' => 'VAT',
        'VAT-EX' => 'VAT-EX',
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
