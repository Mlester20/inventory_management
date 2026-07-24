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
                'vat_type' => 'VAT',
                'contact_person' => 'Jorel Licuanan',
                'contact_number' => '09171234567',
                'email' => 'sales@medsupplyph.com',
                'delivery_address' => '123 Quirino Highway, Quezon City',
            ],
            [
                'supplier_name' => 'PharmaCorp Distribution',
                'vat_type' => 'VAT',
                'contact_person' => 'Anna Reyes',
                'contact_number' => '09182345678',
                'email' => 'orders@pharmacorp.ph',
                'delivery_address' => '45 Shaw Boulevard, Mandaluyong City',
            ],
            [
                'supplier_name' => 'HealthLine Traders',
                'vat_type' => 'VAT',
                'contact_person' => 'Mark Santos',
                'contact_number' => '09193456789',
                'email' => 'contact@healthlinetraders.com',
                'delivery_address' => '78 Aguinaldo Highway, Imus, Cavite',
            ],
            [
                'supplier_name' => 'Metro Drug Distributors',
                'vat_type' => 'VAT',
                'contact_person' => 'Liza Gonzales',
                'contact_number' => '09204567890',
                'email' => 'info@metrodrug.ph',
                'delivery_address' => '12 EDSA, Caloocan City',
            ],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::create($supplier);
        }
    }
}
