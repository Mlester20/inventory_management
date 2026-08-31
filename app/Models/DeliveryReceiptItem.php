<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryReceiptItem extends Model
{
    protected $fillable = [
        'delivery_receipt_id',
        'sales_order_item_id',
        'product_batch_id',
        'qty',
        'remarks',
        'invoiced_qty',
        'batch_no',
        'expiration_date',
    ];

    protected $casts = [
        'qty' => 'integer',
        'invoiced_qty' => 'integer',
        'expiration_date' => 'date',
    ];

    public function getRemainingInvoiceableQtyAttribute(): int
    {
        return $this->qty - $this->invoiced_qty;
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function deliveryReceipt(): BelongsTo
    {
        return $this->belongsTo(DeliveryReceipt::class);
    }

    public function salesOrderItem(): BelongsTo
    {
        return $this->belongsTo(SalesOrderItem::class);
    }

    public function productBatch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class);
    }
}