<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerPayment extends Model
{
    use HasFactory;

    public const PAYMENT_METHODS = [
        'Cash' => 'Cash',
        'Bank Transfer' => 'Bank Transfer',
        'Check' => 'Check',
        'GCash' => 'GCash',
        'Other' => 'Other',
    ];

    public const TYPES = [
        'collection' => 'Collection (against existing balance)',
        'advance' => 'Advance (prepayment for future order)',
    ];

    protected $fillable = [
        'customer_id',
        'type',
        'amount',
        'consumed_amount',
        'payment_date',
        'payment_method',
        'remarks',
        'prepared_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'consumed_amount' => 'decimal:2',
        'payment_date' => 'date',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }
}
