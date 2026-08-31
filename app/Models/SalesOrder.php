<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesOrder extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'customer_id',
        'sales_quote_id',
        'so_no',
        'po_no',
        'status',
        'is_draft',
        'order_date',
        'prepared_by',
        'notes',
        'archived_at',
    ];

    protected $casts = [
        'order_date' => 'date',
        'is_draft' => 'boolean',
        'archived_at' => 'datetime',
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

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function salesQuote(): BelongsTo
    {
        return $this->belongsTo(SalesQuote::class);
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class);
    }

    public function deliveryReceipts(): HasMany
    {
        return $this->hasMany(DeliveryReceipt::class);
    }
}
