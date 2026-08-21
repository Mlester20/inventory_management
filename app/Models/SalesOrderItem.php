<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesOrderItem extends Model
{
    protected $fillable = [
        'sales_order_id',
        'generic_name_id',
        'qty',
        'price',
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

    public function deliveryReceiptItems(): HasMany
    {
        return $this->hasMany(DeliveryReceiptItem::class);
    }

    public function getRemainingQtyAttribute(): int
    {
        return $this->qty - $this->delivered_qty;
    }
}
