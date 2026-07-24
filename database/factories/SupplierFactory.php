<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Supplier>
 */
class SupplierFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_name' => $this->faker->company(),
            'vat_type' => 'VAT',
            'contact_person' => $this->faker->name(),
            'contact_number' => $this->faker->phoneNumber(),
            'email' => $this->faker->companyEmail(),
            'delivery_address' => $this->faker->address(),
        ];
    }
}
