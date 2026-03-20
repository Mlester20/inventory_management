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
            'item_name' => $this->faker->unique()->word() . ' ' . $this->faker->word(),
            'category_id' => 1,
            'supplier_id' => 1,
            'description' => $this->faker->sentence(),
            'quantity' => $this->faker->numberBetween(10, 500),
            'unit_price' => $this->faker->randomFloat(2, 5, 100),
            'low_stock_threshold' => $this->faker->numberBetween(5, 20),
        ];
    }
}
