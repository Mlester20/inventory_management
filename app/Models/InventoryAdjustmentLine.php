<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryAdjustmentLine extends Model
{
    protected $fillable = [
        'inventory_adjustment_id', 'product_id', 'product_batch_id',
        'batch_no', 'expiration_date', 'qty', 'remarks',
    ];

    protected $casts = [
        'qty' => 'integer',
        'expiration_date' => 'date',
    ];

    public function inventoryAdjustment(): BelongsTo
    {
        return $this->belongsTo(InventoryAdjustment::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productBatch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class);
    }
}
