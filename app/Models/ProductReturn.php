<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The printable document for a return from a Delivery Receipt. Its lines are Return Items.
 */
class ProductReturn extends Model
{
    protected $fillable = ['return_no', 'delivery_receipt_id', 'customer_id', 'return_date', 'prepared_by', 'user_id'];

    protected $casts = [
        'return_date' => 'date',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(ReturnItem::class)->orderBy('id');
    }

    public function deliveryReceipt(): BelongsTo
    {
        return $this->belongsTo(DeliveryReceipt::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    /** pending while any line still waits, otherwise approved / rejected / "partly approved". */
    public function status(): string
    {
        $statuses = $this->items->pluck('status')->unique();

        return match (true) {
            $statuses->isEmpty() => 'pending',
            $statuses->contains('pending') => 'pending',
            $statuses->count() === 1 => (string) $statuses->first(),
            default => 'partly approved',
        };
    }

    public function hasPendingLines(): bool
    {
        return $this->items->contains(fn (ReturnItem $item) => $item->status === 'pending');
    }

    /** The next number for the year, e.g. PR-2026-00001. Call inside the transaction that creates it. */
    public static function nextNumber(): string
    {
        $prefix = 'PR-' . now()->year . '-';

        $last = static::where('return_no', 'like', $prefix . '%')->orderByDesc('return_no')->lockForUpdate()->value('return_no');
        $next = $last ? (int) substr($last, strlen($prefix)) + 1 : 1;

        return $prefix . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }
}
