<?php

namespace Database\Factories;

use App\Models\ContributionRequest;
use App\Models\Person;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContributionRequest>
 */
class ContributionRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'requester_id' => User::factory(),
            'matched_father_id' => Person::factory(),
            'subject_person_id' => Person::factory(),
            'affected_person_ids' => [],
            'status' => ContributionRequest::STATUS_PENDING,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => ContributionRequest::STATUS_APPROVED,
            'reviewed_by' => User::factory(),
            'reviewed_at' => now(),
        ]);
    }
}
