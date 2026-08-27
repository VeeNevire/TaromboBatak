<?php

namespace Database\Factories;

use App\Models\FamilyTreeShare;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FamilyTreeShare>
 */
class FamilyTreeShareFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sender_id' => User::factory(),
            'recipient_id' => User::factory(),
            'status' => FamilyTreeShare::STATUS_PENDING,
            'responded_at' => null,
        ];
    }
}
