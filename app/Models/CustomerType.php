<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerType extends Model
{
    protected $fillable = ['name'];

    /** Walk-In is built in (personal use, no withholding tax) — it can't be renamed or deleted. */
    public function isProtected(): bool
    {
        return Customer::isWalkInType($this->name);
    }

    public function customersCount(): int
    {
        return Customer::where('customer_type', $this->name)->count();
    }
}
