<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesOrderItem extends Model
{
    // Manually asserted by the encoder, independent of product_id below —
    // even when a Brand is identified up front, Tax stays a manual pick
    // rather than being derived from Product.tax_id, since the encoder may
    // still want to correct it. Null means "not yet identified", the same
    // blank-VAT-box state as before this existed.
    public const TAX_CLASSIFICATIONS = [
        'vatable' => 'VATable',
        'vatex' => 'VAT-Exempt',
        'zero' => 'Zero-Rated',
    ];

    protected $fillable = [
        'sales_order_id',
        'generic_name_id',
        'product_id',
        'qty',
        'price',
        'tax_classification',
        'advance_order_qty',
        'delivered_qty',
        'remarks',
    ];

    protected $casts = [
        'qty' => 'integer',
        'advance_order_qty' => 'integer',
        'delivered_qty' => 'integer',
        'price' => 'decimal:2',
    ];

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function genericName(): BelongsTo
    {
        return $this->belongsTo(GenericName::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function deliveryReceiptItems(): HasMany
    {
        return $this->hasMany(DeliveryReceiptItem::class);
    }

    public function getRemainingQtyAttribute(): int
    {
        return $this->qty - $this->delivered_qty;
    }
}
