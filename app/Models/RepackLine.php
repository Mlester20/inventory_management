<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepackLine extends Model
{
    protected $fillable = [
        'repack_id',
        'source_batch_id',
        'source_qty',
        'destination_product_id',
        'destination_batch_id',
        'destination_qty',
    ];

    protected $casts = [
        'source_qty' => 'integer',
        'destination_qty' => 'integer',
    ];

    public function repack(): BelongsTo
    {
        return $this->belongsTo(Repack::class);
    }

    public function sourceBatch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'source_batch_id');
    }

    public function destinationProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'destination_product_id');
    }

    public function destinationBatch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'destination_batch_id');
    }
}
