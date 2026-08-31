<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryReceipt extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'customer_id',
        'sales_order_id',
        'dr_no',
        'transaction_type',
        'status',
        'is_draft',
        'description',
        'receipt_date',
        'prepared_by',
        'archived_at',
        'cancellation_note',
    ];

    protected $casts = [
        'receipt_date' => 'date',
        'is_draft' => 'boolean',
        'archived_at' => 'datetime',
    ];

    public const TRANSACTION_TYPES = [
        'advance_order' => 'ADVANCE ORDER',
        'purchase_order' => 'PURCHASE ORDER',
        'walk_in' => 'WALK-IN',
    ];

    public const STATUSES = [
        'for_delivery' => 'FOR DELIVERY',
        'delivered' => 'DELIVERED',
        'cancelled' => 'CANCELLED',
    ];

    public function isDraft(): bool
    {
        return (bool) $this->is_draft;
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * COMPLETE once every line is fully invoiced, PARTIALLY INVOICED once
     * any line has some invoiced qty, otherwise NOT INVOICED.
     */
    public function getInvoiceStatusAttribute(): string
    {
        $items = $this->items;

        return match (true) {
            $items->isNotEmpty() && $items->every(fn (DeliveryReceiptItem $item) => $item->invoiced_qty >= $item->qty) => 'COMPLETE',
            $items->contains(fn (DeliveryReceiptItem $item) => $item->invoiced_qty > 0) => 'PARTIALLY INVOICED',
            default => 'NOT INVOICED',
        };
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryReceiptItem::class);
    }
}
