<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->bothify('P####')),
            'generic_name_id' => \App\Models\GenericName::factory(),
            'brand_name' => $this->faker->word(),
            'description' => $this->faker->sentence(),
            'unit_price' => $this->faker->randomFloat(2, 5, 100),
            'unit_cost' => $this->faker->randomFloat(2, 1, 50),
            'low_stock_threshold' => $this->faker->numberBetween(5, 20),
        ];
    }
}
