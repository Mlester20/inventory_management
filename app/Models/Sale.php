<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sale extends Model
{
    protected $fillable = [
        'invoice_id',
        'item_id',
        'desc',
        'qty',
        'unit',
        'batch_no',
        'exp',
        'price',
        'vat',
        'dis',
        'amount',
    ];

    protected $casts = [
        'qty' => 'integer',
        'exp' => 'date',
        'price' => 'decimal:2',
        'vat' => 'decimal:2',
        'dis' => 'decimal:2',
        'amount' => 'decimal:2',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
