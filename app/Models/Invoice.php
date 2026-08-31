<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'customer_name',
        'customer_id',
        'po_no',
        'osca_no',
        'sales_no',
        'prepared_by',
        'approved_by',
        'vat_sales',
        'vatex_sales',
        'zero_sales',
        'vat_amount',
        'total_sales',
        'less_vat',
        'amount_net',
        'less_sc',
        'less_wt',
        'amount_due',
        'amount_paid',
        'add_vat',
        'archived_at',
        'cancelled_at',
    ];

    protected $casts = [
        'vat_sales' => 'decimal:2',
        'vatex_sales' => 'decimal:2',
        'zero_sales' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'total_sales' => 'decimal:2',
        'less_vat' => 'decimal:2',
        'amount_net' => 'decimal:2',
        'less_sc' => 'decimal:2',
        'less_wt' => 'decimal:2',
        'amount_due' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'add_vat' => 'decimal:2',
        'archived_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
