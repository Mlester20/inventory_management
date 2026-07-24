<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductBatch>
 */
class ProductBatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'batch_no' => strtoupper($this->faker->bothify('LOT###')),
            'expiration_date' => $this->faker->dateTimeBetween('+1 month', '+2 years'),
            'qty' => $this->faker->numberBetween(10, 500),
            'reserved_qty' => 0,
        ];
    }
}
