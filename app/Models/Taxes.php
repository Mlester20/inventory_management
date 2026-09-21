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

    /**
     * The tax row a "VAT Inc" product points at, found by rate (> 0), never by
     * name — admins rename tax rows freely (e.g. "VAT" -> "VAT-INC"), and a
     * literal-name lookup would silently return nothing and leave new products
     * VAT-exempt. Mirrors Product::taxClassification(). Prefers the active row
     * so a deactivated leftover isn't picked over the real one.
     */
    public static function vatable(): ?self
    {
        return self::where('rate', '>', 0)->orderByDesc('is_active')->orderBy('id')->first();
    }

    /** The tax row a "Zero Vat" product points at: the 0% row, found by rate, not name. */
    public static function zeroRated(): ?self
    {
        return self::where('rate', 0)->orderByDesc('is_active')->orderBy('id')->first();
    }
}