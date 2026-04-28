<?php

namespace Database\Factories;

use App\Models\Circuit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Circuit>
 */
class CircuitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->sentence(3),
            'description' => fake()->optional()->sentence(),
            'location' => fake()->optional()->randomElement(['Bancada 1', 'Armario A', 'Prateleira C']),
            'assembled_at' => fake()->optional()->dateTimeThisYear(),
        ];
    }
}
