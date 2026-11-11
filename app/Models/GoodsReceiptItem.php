<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoodsReceiptItem extends Model
{
    protected $fillable = [
        'goods_receipt_id',
        'purchase_order_item_id',
        'supplier_id',
        'supplier_not_determined',
        'product_id',
        'product_batch_id',
        'qty',
        'invoiced_qty',
        'unit',
        'unit_cost',
        'batch_no',
        'expiration_date',
        'remarks',
    ];

    protected $casts = [
        'qty' => 'integer',
        'supplier_not_determined' => 'boolean',
        'unit_cost' => 'decimal:2',
        'expiration_date' => 'date',
    ];

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function productBatch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseInvoiceItems(): HasMany
    {
        return $this->hasMany(PurchaseInvoiceItem::class);
    }
}
