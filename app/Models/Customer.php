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
        'withholding_vat_rate',
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
     * Customer Type for a buyer purchasing for personal use. Per Sir, personal-use
     * medicine is not covered by withholding tax, so a Walk-In customer never has
     * any (no 1% / 2% and no Withholding VAT).
     */
    public const WALK_IN = 'Walk-In';

    /** "Walk-In", "WALK-IN", "walk in"... all count. */
    public static function isWalkInType(?string $type): bool
    {
        return preg_replace('/[^a-z]/', '', mb_strtolower((string) $type)) === 'walkin';
    }

    public function isWalkIn(): bool
    {
        return static::isWalkInType($this->customer_type);
    }

    /**
     * The Customer Type drop-down: Walk-In first, then the rest of the managed list
     * (Customer Types page).
     *
     * @return array<int,string>
     */
    public static function typeOptions(): array
    {
        $others = CustomerType::orderBy('name')
            ->pluck('name')
            ->reject(fn ($type) => static::isWalkInType($type))
            ->values()
            ->all();

        return array_merge([static::WALK_IN], $others);
    }

    protected static function booted(): void
    {
        // Any spelling of walk-in is stored as the one built-in type, and a type that arrives
        // any other way (Excel import, the quick-add on the Delivery Receipt form) joins the
        // managed list instead of living only on the customer.
        static::saving(function (Customer $customer) {
            if (static::isWalkInType($customer->customer_type)) {
                $customer->customer_type = static::WALK_IN;
            }
        });

        static::saved(function (Customer $customer) {
            $type = trim((string) $customer->customer_type);
            if ($type !== '') {
                CustomerType::firstOrCreate(['name' => $type]);
            }
        });
    }

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

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
