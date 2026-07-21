<?php

namespace Database\Factories;

use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Item>
 */
class ItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'generic_name_id' => \App\Models\GenericName::factory(),
            'brand_name' => $this->faker->word(),
            'supplier_id' => 1,
            'description' => $this->faker->sentence(),
            'quantity' => $this->faker->numberBetween(10, 500),
            'unit_price' => $this->faker->randomFloat(2, 5, 100),
            'unit_cost' => $this->faker->randomFloat(2, 1, 50),
            'low_stock_threshold' => $this->faker->numberBetween(5, 20),
        ];
    }
}
