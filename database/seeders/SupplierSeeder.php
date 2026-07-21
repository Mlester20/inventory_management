<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $suppliers = [
            [
                'supplier_name' => 'MedSupply Philippines Inc.',
                'contact_person' => 'Jorel Licuanan',
                'phone' => '09171234567',
                'email' => 'sales@medsupplyph.com',
                'address' => '123 Quirino Highway, Quezon City',
            ],
            [
                'supplier_name' => 'PharmaCorp Distribution',
                'contact_person' => 'Anna Reyes',
                'phone' => '09182345678',
                'email' => 'orders@pharmacorp.ph',
                'address' => '45 Shaw Boulevard, Mandaluyong City',
            ],
            [
                'supplier_name' => 'HealthLine Traders',
                'contact_person' => 'Mark Santos',
                'phone' => '09193456789',
                'email' => 'contact@healthlinetraders.com',
                'address' => '78 Aguinaldo Highway, Imus, Cavite',
            ],
            [
                'supplier_name' => 'Metro Drug Distributors',
                'contact_person' => 'Liza Gonzales',
                'phone' => '09204567890',
                'email' => 'info@metrodrug.ph',
                'address' => '12 EDSA, Caloocan City',
            ],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::create($supplier);
        }
    }
}
