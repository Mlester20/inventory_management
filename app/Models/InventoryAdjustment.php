<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class InventoryAdjustment extends Model
{
    protected $fillable = [
        'adjustment_no', 'adjustment_date', 'adjustment_type', 'description', 'note', 'prepared_by',
        'reverses_adjustment_id', 'status',
    ];

    protected $casts = [
        'adjustment_date' => 'date',
    ];

    public const STATUSES = [
        'draft' => 'Draft',
        'posted' => 'Posted',
    ];

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Adjustment type => stock direction. "Correction" is split into two
     * literal types (increase/decrease) so direction never has to be a
     * separate per-line field.
     */
    public const TYPES = [
        'stock_in_found' => 'Stock In - Found/Recount',
        'stock_out_damaged' => 'Stock Out - Damaged',
        'stock_out_lost' => 'Stock Out - Lost/Theft',
        'stock_out_expired' => 'Stock Out - Expired',
        'correction_increase' => 'Correction - Increase',
        'correction_decrease' => 'Correction - Decrease',
    ];

    public const STOCK_IN_TYPES = ['stock_in_found', 'correction_increase'];

    public function direction(): string
    {
        return in_array($this->adjustment_type, self::STOCK_IN_TYPES, true) ? 'in' : 'out';
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InventoryAdjustmentLine::class);
    }

    /**
     * The original adjustment this one reverses, if this is a write-off.
     */
    public function reverses(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_adjustment_id');
    }

    /**
     * The write-off that reverses this adjustment, if it's been written off.
     */
    public function reversedBy(): HasOne
    {
        return $this->hasOne(self::class, 'reverses_adjustment_id');
    }
}
