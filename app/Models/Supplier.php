<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = ['supplier_name', 'contact_person', 'phone', 'email', 'address'];

    public function items()
    {
        return $this->hasMany(Item::class);
    }
}
