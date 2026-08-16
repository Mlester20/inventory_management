<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockTransfer extends Model
{
    protected $fillable = [
        'reference',
        'date',
        'from_location_id',
        'to_location_id',
        'prepared_by',
        'status',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public const STATUSES = [
        'draft' => 'Draft',
        'posted' => 'Posted',
    ];

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StockTransferLine::class);
    }
}
