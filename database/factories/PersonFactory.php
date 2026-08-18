<?php

namespace Database\Factories;

use App\Models\Marga;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Person>
 */
class PersonFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'alias' => fake()->optional()->word(),
            'marga_id' => Marga::factory(),
            'father_id' => null,
            'birth_year' => (string) fake()->numberBetween(1900, 2020),
            'death_year' => null,
            'image' => null,
            'bio' => fake()->optional()->sentence(),
            'is_public' => false,
        ];
    }

    public function public(): static
    {
        return $this->state(fn () => ['is_public' => true]);
    }
}
