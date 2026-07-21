<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryReceiptItem extends Model
{
    protected $fillable = [
        'delivery_receipt_id',
        'sales_order_item_id',
        'item_id',
        'qty',
        'batch_no',
        'expiration_date',
    ];

    protected $casts = [
        'qty' => 'integer',
        'expiration_date' => 'date',
    ];

    public function deliveryReceipt(): BelongsTo
    {
        return $this->belongsTo(DeliveryReceipt::class);
    }

    public function salesOrderItem(): BelongsTo
    {
        return $this->belongsTo(SalesOrderItem::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
