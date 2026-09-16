<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesQuoteItem extends Model
{
    // See SalesOrderItem::TAX_CLASSIFICATIONS — same manual, optional flag.
    public const TAX_CLASSIFICATIONS = [
        'vatable' => 'VATable',
        'vatex' => 'VAT-Exempt',
        'zero' => 'Zero-Rated',
    ];

    protected $fillable = [
        'sales_quote_id',
        'generic_name_id',
        'product_id',
        'qty',
        'price',
        'tax_classification',
    ];

    public function salesQuote(): BelongsTo
    {
        return $this->belongsTo(SalesQuote::class);
    }

    public function genericName(): BelongsTo
    {
        return $this->belongsTo(GenericName::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
