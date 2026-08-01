<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocationStock extends Model
{
    protected $fillable = [
        'location_id',
        'product_batch_id',
        'qty',
        'reserved_qty',
    ];

    protected $casts = [
        'qty' => 'integer',
        'reserved_qty' => 'integer',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function productBatch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class);
    }

    public function getAvailableQtyAttribute(): int
    {
        return $this->qty - $this->reserved_qty;
    }
}
