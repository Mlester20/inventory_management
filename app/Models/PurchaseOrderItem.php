<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'item_id',
        'qty',
        'unit_cost',
        'received_qty',
    ];

    protected $casts = [
        'qty' => 'integer',
        'received_qty' => 'integer',
        'unit_cost' => 'decimal:2',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function goodsReceiptItems(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }

    public function getRemainingQtyAttribute(): int
    {
        return $this->qty - $this->received_qty;
    }
}
