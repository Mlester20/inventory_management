<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_name',
        'customer_type',
        'contact_person',
        'contact_number',
        'email',
        'delivery_address',
        'price_level',
        'vat_type',
    ];

    /**
     * Price level keys match Item's actual price columns 1:1 (see
     * priceColumn() below) — the client's mockup labels these "P. Level
     * 3/4/5" on the Customer form but "P. Level 1/2/3" on the Product form;
     * confirmed these are the same 3rd/4th/5th tier slots, just numbered
     * differently per form. Only the label differs, not the underlying tier.
     */
    public const PRICE_LEVELS = [
        'retail' => 'Retail (Default)',
        'wholesale' => 'Wholesale',
        'price_level_1' => 'P. Level 3',
        'price_level_2' => 'P. Level 4',
        'price_level_3' => 'P. Level 5',
    ];

    public const VAT_TYPES = [
        'VAT' => 'VAT',
        'NON-VAT' => 'NON-VAT',
    ];

    /**
     * Which item price column this customer's price level should default to.
     */
    public function priceColumn(): string
    {
        return match ($this->price_level) {
            'wholesale' => 'wholesale_price',
            'price_level_1' => 'price_1',
            'price_level_2' => 'price_2',
            'price_level_3' => 'price_3',
            default => 'unit_price',
        };
    }

    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class);
    }

    public function deliveryReceipts(): HasMany
    {
        return $this->hasMany(DeliveryReceipt::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CustomerPayment::class);
    }
}
