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

    /**
     * What a Delivery Receipt can still deliver. Advance Qty was already handed over in advance
     * (it will never get a DR from this order — a DR would deduct that stock a second time), so
     * it counts as covered, the same as a delivered quantity. Goods the client sent back are
     * owed again: they come off the delivered quantity here, so a replacement DR can be made.
     * The Delivered column itself keeps the original quantity.
     */
    public function getRemainingQtyAttribute(): int
    {
        $netDelivered = max(0, (int) $this->delivered_qty - $this->returnedQty());

        return max(0, (int) $this->qty - (int) $this->advance_order_qty - $netDelivered);
    }

    /** How many approved returned units were allocated to this SO line through its Delivery Receipt lines. */
    public function returnedQty(): int
    {
        $lines = $this->deliveryReceiptItems()
            ->with('deliveryReceipt')
            ->get();

        $returned = $lines->sum(function (DeliveryReceiptItem $line) {
            $allocation = $line->deliveryReceipt?->approvedReturnAllocations() ?? [];

            return (int) ($allocation[$line->id] ?? 0);
        });

        return min((int) $this->delivered_qty, (int) $returned);
    }
}
