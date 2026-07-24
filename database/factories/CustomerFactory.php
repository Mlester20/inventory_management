<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_name' => $this->faker->unique()->name(),
            'customer_type' => $this->faker->randomElement(['Pharmacy', 'Hospital', 'Clinic']),
            'price_level' => 'retail',
            'vat_type' => 'VAT',
            'contact_person' => $this->faker->name(),
            'contact_number' => $this->faker->phoneNumber(),
            'email' => $this->faker->unique()->safeEmail(),
            'delivery_address' => $this->faker->address(),
        ];
    }
}
