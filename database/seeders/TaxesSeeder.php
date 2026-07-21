<?php

namespace Database\Seeders;

use App\Models\Taxes;
use Illuminate\Database\Seeder;

class TaxesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Taxes::create(['name' => 'VAT', 'rate' => 12.00, 'is_active' => true]);
        Taxes::create(['name' => 'Zero-Rated', 'rate' => 0.00, 'is_active' => false]);
    }
}
