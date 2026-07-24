<?php

namespace Database\Factories;

use App\Models\ProductBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ReturnItem>
 */
class ReturnItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_batch_id' => ProductBatch::factory(),
            'quantity' => $this->faker->numberBetween(1, 5),
            'return_date' => now(),
            'reason' => $this->faker->sentence(),
            'status' => 'pending',
        ];
    }
}
