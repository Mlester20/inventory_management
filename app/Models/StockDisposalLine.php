<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockDisposalLine extends Model
{
    protected $fillable = [
        'stock_disposal_id',
        'product_batch_id',
        'location_id',
        'qty',
    ];

    protected $casts = [
        'qty' => 'integer',
    ];

    public function stockDisposal(): BelongsTo
    {
        return $this->belongsTo(StockDisposal::class);
    }

    public function productBatch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
