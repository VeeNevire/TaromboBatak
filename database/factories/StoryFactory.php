<?php

namespace Database\Factories;

use App\Models\Story;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Story>
 */
class StoryFactory extends Factory
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
            'image' => fake()->imageUrl(),
            'published' => true,
            'classification' => Story::CLASSIFICATION_GENERAL,
            'status' => Story::STATUS_APPROVED,
            'review_version' => 1,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'published' => false,
            'status' => Story::STATUS_PENDING,
        ]);
    }
}
