<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Taxes extends Model
{
    protected $fillable = [
        'name',
        'rate',
        'is_active'
    ];

    /**
     * The VAT percentage to apply on invoices/SO/SQ. The Taxes page lets more
     * than one row be marked active at once (e.g. a 12% and a 0% tax both
     * "active"), and a plain `where('is_active', true)->value('rate')` would
     * then return whichever row MySQL happens to return first — unordered,
     * so effectively random, and a 0% result silently zeroes out VAT on every
     * sale. Ordering by rate descending means an accidentally-active 0% tax
     * can never win over a real VAT rate.
     */
    public static function activeRate(): float
    {
        return (float) (self::where('is_active', true)->orderByDesc('rate')->value('rate') ?? 0);
    }
}