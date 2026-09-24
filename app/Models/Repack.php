<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Repack extends Model
{
    protected $fillable = [
        'reference',
        'date',
        'location_id',
        'prepared_by',
        'status',
        'remarks',
        'voided_at',
        'voided_by',
        'void_reason',
    ];

    protected $casts = [
        'date' => 'date',
        'voided_at' => 'datetime',
    ];

    public const STATUSES = [
        'posted' => 'Posted',
        'voided' => 'Voided',
    ];

    public function isVoided(): bool
    {
        return $this->status === 'voided';
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(RepackLine::class);
    }
}
