<?php

namespace Database\Factories;

use App\Models\MargaNews;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MargaNews>
 */
class MargaNewsFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->unique()->sentence(6);
        $url = fake()->unique()->url();

        return [
            'title' => $title,
            'url' => $url,
            'url_hash' => MargaNews::hashUrl($url),
            'title_hash' => MargaNews::hashTitle($title),
            'publisher' => fake()->company(),
            'excerpt' => fake()->sentence(12),
            'published_at' => now()->subDays(fake()->numberBetween(0, 30)),
            'status' => MargaNews::STATUS_PENDING,
            'submitted_by' => 'hermes',
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => ['status' => MargaNews::STATUS_APPROVED]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => ['status' => MargaNews::STATUS_REJECTED]);
    }
}
