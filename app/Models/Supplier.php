<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $fillable = ['supplier_name', 'contact_person', 'phone', 'email', 'address'];

    public function items()
    {
        return $this->hasMany(Item::class);
    }
}
