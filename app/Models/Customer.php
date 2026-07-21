<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = ['customer_name', 'customer_type', 'contact_person', 'phone', 'email', 'address'];

    public const CUSTOMER_TYPES = [
        'walk_in' => 'Walk-in',
        'pharmacy_clinic' => 'Pharmacy / Clinic',
        'category_2' => 'Customer Category 2',
    ];

    /**
     * Which item price column this customer type should default to.
     */
    public function priceColumn(): string
    {
        return match ($this->customer_type) {
            'pharmacy_clinic' => 'wholesale_price',
            'category_2' => 'price_1',
            default => 'unit_price',
        };
    }
}
