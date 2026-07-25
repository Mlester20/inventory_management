<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesQuoteItem extends Model
{
    protected $fillable = [
        'sales_quote_id',
        'generic_name_id',
        'qty',
        'price',
    ];

    public function salesQuote(): BelongsTo
    {
        return $this->belongsTo(SalesQuote::class);
    }

    public function genericName(): BelongsTo
    {
        return $this->belongsTo(GenericName::class);
    }
}
