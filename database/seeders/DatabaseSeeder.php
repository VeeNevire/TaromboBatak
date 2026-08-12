<?php

namespace Database\Seeders;

use App\Models\Marga;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->asAdmin()->create([
            'name' => 'Admin Tarombo',
            'email' => 'admin@example.com',
        ]);

        $this->call(TaromboSeeder::class);
        $this->call(LandingSeeder::class);

        $marga = Marga::where('name', 'Limbong')->first();

        User::factory()->withMarga($marga?->id)->create([
            'name' => 'User Test',
            'email' => 'user@example.com',
        ]);
    }
}
