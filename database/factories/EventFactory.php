<?php

namespace Database\Factories;

use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'location' => fake()->city(),
            'date' => fake()->dateTimeBetween('now', '+6 months')->format('Y-m-d'),
            'published' => true,
            'status' => Event::STATUS_APPROVED,
            'review_version' => 1,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'published' => false,
            'status' => Event::STATUS_PENDING,
        ]);
    }
}
