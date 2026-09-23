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
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public const STATUSES = [
        'posted' => 'Posted',
    ];

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
