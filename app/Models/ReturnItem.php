<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnItem extends Model
{
    protected $fillable = [
        'item_id',
        'user_id',
        'quantity',
        'return_date',
        'reason',
        'notes',
        'status'
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to filter only approved returns.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope to get total value of approved returns joined with items unit_price.
     */
    public function scopeTotalReturnValue($query)
    {
        return $query->join('items', 'items.id', '=', 'return_items.item_id')
            ->selectRaw('SUM(return_items.quantity * items.unit_price) as return_value');
    }
}
