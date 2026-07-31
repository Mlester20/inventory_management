<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierPayment extends Model
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
        'payment' => 'Payment (against existing balance)',
        'advance' => 'Advance (prepayment for future PO)',
    ];

    protected $fillable = [
        'supplier_id',
        'type',
        'amount',
        'payment_date',
        'payment_method',
        'remarks',
        'prepared_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }
}
