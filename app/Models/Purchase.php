<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    protected $fillable = [
        'item_id',
        'quantity_sold',
        'unit_price',
        'total_price',
        'purchase_date',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
