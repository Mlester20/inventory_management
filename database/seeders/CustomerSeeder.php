<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $customers = [
            [
                'customer_name' => 'Walk-in Customer',
                'customer_type' => null,
                'price_level' => 'retail',
                'vat_type' => 'VAT',
                'contact_person' => null,
                'contact_number' => null,
                'email' => null,
                'delivery_address' => null,
            ],
            [
                'customer_name' => 'Green Cross Pharmacy',
                'customer_type' => 'Pharmacy',
                'price_level' => 'wholesale',
                'vat_type' => 'VAT',
                'contact_person' => 'Ramon Dela Cruz',
                'contact_number' => '09151234567',
                'email' => 'purchasing@greencross.ph',
                'delivery_address' => '88 Commonwealth Ave, Quezon City',
            ],
            [
                'customer_name' => "St. Luke's Clinic",
                'customer_type' => 'Clinic',
                'price_level' => 'wholesale',
                'vat_type' => 'VAT',
                'contact_person' => 'Dr. Maria Fernandez',
                'contact_number' => '09162345678',
                'email' => 'admin@stlukesclinic.ph',
                'delivery_address' => '279 E Rodriguez Sr. Ave, Quezon City',
            ],
            [
                'customer_name' => 'Metro Health Clinic',
                'customer_type' => 'Clinic',
                'price_level' => 'retail',
                'vat_type' => 'VAT',
                'contact_person' => 'Dr. Paolo Villanueva',
                'contact_number' => '09173456789',
                'email' => 'info@metrohealthclinic.ph',
                'delivery_address' => '56 Ortigas Avenue, Pasig City',
            ],
        ];

        foreach ($customers as $customer) {
            Customer::create($customer);
        }
    }
}
