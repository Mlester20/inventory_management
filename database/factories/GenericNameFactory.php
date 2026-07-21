<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GenericName>
 */
class GenericNameFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'generic_name' => $this->faker->unique()->word() . ' ' . $this->faker->word(),
            'category_id' => Category::factory(),
            'unit' => $this->faker->randomElement(['Tablet', 'Box', 'Bottle', 'Piece']),
        ];
    }
}
