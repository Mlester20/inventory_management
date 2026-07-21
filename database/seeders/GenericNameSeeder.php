<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\GenericName;
use Illuminate\Database\Seeder;

class GenericNameSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $generics = [
            ['generic_name' => 'Paracetamol 500mg', 'category' => 'Pain Relief', 'unit' => 'Tablet'],
            ['generic_name' => 'Mefenamic Acid 500mg', 'category' => 'Pain Relief', 'unit' => 'Tablet'],
            ['generic_name' => 'Amoxicillin 500mg', 'category' => 'Antibiotics', 'unit' => 'Capsule'],
            ['generic_name' => 'Cefalexin 500mg', 'category' => 'Antibiotics', 'unit' => 'Capsule'],
            ['generic_name' => 'Ascorbic Acid 500mg', 'category' => 'Vitamins & Supplements', 'unit' => 'Tablet'],
            ['generic_name' => 'Multivitamins + Minerals', 'category' => 'Vitamins & Supplements', 'unit' => 'Bottle'],
            ['generic_name' => 'Cetirizine 10mg', 'category' => 'Cough & Cold', 'unit' => 'Tablet'],
            ['generic_name' => 'Carbocisteine 500mg', 'category' => 'Cough & Cold', 'unit' => 'Capsule'],
            ['generic_name' => 'Omeprazole 20mg', 'category' => 'Antacids & Digestives', 'unit' => 'Capsule'],
        ];

        foreach ($generics as $generic) {
            $category = Category::where('category_name', $generic['category'])->first();

            GenericName::create([
                'generic_name' => $generic['generic_name'],
                'category_id' => $category->id,
                'unit' => $generic['unit'],
            ]);
        }
    }
}
