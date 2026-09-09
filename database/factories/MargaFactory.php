<?php

namespace Database\Factories;

use App\Models\Marga;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Marga>
 */
class MargaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'description' => fake()->sentence(),
            'color' => fake()->hexColor(),
            'is_public' => false,
        ];
    }

    /**
     * Mark the marga as publicly listed.
     */
    public function public(): static
    {
        return $this->state(fn (): array => ['is_public' => true]);
    }
}
