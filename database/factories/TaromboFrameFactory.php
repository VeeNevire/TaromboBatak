<?php

namespace Database\Factories;

use App\Models\TaromboFrame;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TaromboFrame> */
class TaromboFrameFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'path' => 'tarombo-frames/'.fake()->uuid().'.jpg',
            'canvas_width' => 1200,
            'canvas_height' => 800,
            'area_x' => 100,
            'area_y' => 100,
            'area_width' => 1000,
            'area_height' => 600,
            'is_active' => true,
        ];
    }
}
