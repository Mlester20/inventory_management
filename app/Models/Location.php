<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    protected $fillable = [
        'name',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function locationStocks(): HasMany
    {
        return $this->hasMany(LocationStock::class);
    }

    /**
     * The Warehouse holds bulk stock and is where Goods Receipt, Delivery
     * Receipt, and Inventory Adjustment default to.
     */
    public static function warehouse(): self
    {
        return static::where('name', 'Warehouse')->firstOrFail();
    }

    /**
     * The POS location holds only what's been transferred in — everything
     * sellable at checkout is scoped to this location's stock.
     */
    public static function pos(): self
    {
        return static::where('name', 'POS')->firstOrFail();
    }
}
