<?php

namespace Database\Factories;

use App\Models\TaromboSnapshot;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaromboSnapshot>
 */
class TaromboSnapshotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'center_person_id' => null,
            'view' => fake()->randomElement(['diagram', 'tree']),
            'path' => 'tarombo-snapshots/'.fake()->uuid().'.jpg',
        ];
    }
}
