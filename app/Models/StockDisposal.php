<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockDisposal extends Model
{
    // Kept generic rather than hardcoded — expiration is the only trigger
    // wired up today, but a future "Refund to Supplier" reason (a separate,
    // not-yet-defined flow) can be added here without restructuring this
    // table.
    public const REASONS = [
        'Expired' => 'Expired',
    ];

    protected $fillable = [
        'reference',
        'date',
        'reason',
        'remarks',
        'prepared_by',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StockDisposalLine::class);
    }
}
