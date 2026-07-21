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
                'contact_person' => null,
                'phone' => null,
                'email' => null,
                'address' => null,
            ],
            [
                'customer_name' => 'Green Cross Pharmacy',
                'contact_person' => 'Ramon Dela Cruz',
                'phone' => '09151234567',
                'email' => 'purchasing@greencross.ph',
                'address' => '88 Commonwealth Ave, Quezon City',
            ],
            [
                'customer_name' => "St. Luke's Clinic",
                'contact_person' => 'Dr. Maria Fernandez',
                'phone' => '09162345678',
                'email' => 'admin@stlukesclinic.ph',
                'address' => '279 E Rodriguez Sr. Ave, Quezon City',
            ],
            [
                'customer_name' => 'Metro Health Clinic',
                'contact_person' => 'Dr. Paolo Villanueva',
                'phone' => '09173456789',
                'email' => 'info@metrohealthclinic.ph',
                'address' => '56 Ortigas Avenue, Pasig City',
            ],
        ];

        foreach ($customers as $customer) {
            Customer::create($customer);
        }
    }
}
