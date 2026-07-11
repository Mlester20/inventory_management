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
}