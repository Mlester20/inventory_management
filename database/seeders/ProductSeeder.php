<?php

namespace Database\Seeders;

use App\Models\GenericName;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Taxes;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $vatTaxId = Taxes::where('name', 'VAT')->value('id');
        $suppliers = Supplier::pluck('id', 'supplier_name');

        $products = [
            [
                'generic_name' => 'Paracetamol 500mg', 'brand_name' => 'Biogesic',
                'supplier' => 'MedSupply Philippines Inc.', 'quantity' => 200,
                'unit_cost' => 2.50, 'unit_price' => 5.00, 'wholesale_percent' => 5, 'wholesale_price' => 4.75,
                'low_stock_threshold' => 50, 'expiration_days' => 200,
            ],
            [
                'generic_name' => 'Mefenamic Acid 500mg', 'brand_name' => 'Dolfenal',
                'supplier' => 'PharmaCorp Distribution', 'quantity' => 15,
                'unit_cost' => 6.00, 'unit_price' => 12.00, 'wholesale_percent' => 8, 'wholesale_price' => 11.04,
                'low_stock_threshold' => 30, 'expiration_days' => 20,
            ],
            [
                'generic_name' => 'Amoxicillin 500mg', 'brand_name' => 'Amoxil',
                'supplier' => 'MedSupply Philippines Inc.', 'quantity' => 150,
                'unit_cost' => 5.00, 'unit_price' => 9.50, 'wholesale_percent' => 5, 'wholesale_price' => 9.03,
                'low_stock_threshold' => 40, 'expiration_days' => 400,
            ],
            [
                'generic_name' => 'Cefalexin 500mg', 'brand_name' => 'Keflex',
                'supplier' => 'HealthLine Traders', 'quantity' => 80,
                'unit_cost' => 7.00, 'unit_price' => 13.00, 'wholesale_percent' => 5, 'wholesale_price' => 12.35,
                'low_stock_threshold' => 20, 'expiration_days' => 5,
            ],
            [
                'generic_name' => 'Ascorbic Acid 500mg', 'brand_name' => 'Cecon',
                'supplier' => 'PharmaCorp Distribution', 'quantity' => 300,
                'unit_cost' => 3.00, 'unit_price' => 6.00, 'wholesale_percent' => 5, 'wholesale_price' => 5.70,
                'low_stock_threshold' => 60, 'expiration_days' => 300,
            ],
            [
                'generic_name' => 'Multivitamins + Minerals', 'brand_name' => 'Enervon',
                'supplier' => 'Metro Drug Distributors', 'quantity' => 120,
                'unit_cost' => 8.00, 'unit_price' => 15.00, 'wholesale_percent' => 5, 'wholesale_price' => 14.25,
                'low_stock_threshold' => 30, 'expiration_days' => 180,
            ],
            [
                'generic_name' => 'Cetirizine 10mg', 'brand_name' => 'Virlix',
                'supplier' => 'MedSupply Philippines Inc.', 'quantity' => 5,
                'unit_cost' => 4.00, 'unit_price' => 8.00, 'wholesale_percent' => 5, 'wholesale_price' => 7.60,
                'low_stock_threshold' => 25, 'expiration_days' => -10,
            ],
            [
                'generic_name' => 'Carbocisteine 500mg', 'brand_name' => 'Solmux',
                'supplier' => 'HealthLine Traders', 'quantity' => 90,
                'unit_cost' => 6.50, 'unit_price' => 12.50, 'wholesale_percent' => 5, 'wholesale_price' => 11.88,
                'low_stock_threshold' => 20, 'expiration_days' => 60,
            ],
            [
                'generic_name' => 'Omeprazole 20mg', 'brand_name' => 'Omepron',
                'supplier' => 'Metro Drug Distributors', 'quantity' => 60,
                'unit_cost' => 9.00, 'unit_price' => 16.00, 'wholesale_percent' => 5, 'wholesale_price' => 15.20,
                'low_stock_threshold' => 15, 'expiration_days' => 90,
            ],
        ];

        foreach ($products as $index => $product) {
            $genericNameId = GenericName::where('generic_name', $product['generic_name'])->value('id');

            $productModel = Product::create([
                'code' => str_pad((string) ($index + 1), 5, '0', STR_PAD_LEFT),
                'generic_name_id' => $genericNameId,
                'brand_name' => $product['brand_name'],
                'supplier_id' => $suppliers[$product['supplier']],
                'tax_id' => $vatTaxId,
                'unit_cost' => $product['unit_cost'],
                'unit_price' => $product['unit_price'],
                'wholesale_percent' => $product['wholesale_percent'],
                'wholesale_price' => $product['wholesale_price'],
                'low_stock_threshold' => $product['low_stock_threshold'],
            ]);

            $productModel->batches()->create([
                'batch_no' => 'B' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
                'expiration_date' => now()->addDays($product['expiration_days'])->toDateString(),
                'qty' => $product['quantity'],
                'reserved_qty' => 0,
            ]);
        }
    }
}
