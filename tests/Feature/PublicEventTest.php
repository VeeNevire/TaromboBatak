<?php

use App\Models\Event;
use Inertia\Testing\AssertableInertia as Assert;

test('published events are ordered with upcoming events before past events', function () {
    Event::factory()->create(['title' => 'Sudah Lewat', 'date' => now()->subDay()]);
    Event::factory()->create(['title' => 'Akan Datang', 'date' => now()->addDay()]);

    $this->get(route('kegiatan.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('events.data.0.title', 'Akan Datang')
            ->where('events.data.1.title', 'Sudah Lewat')
            ->etc());
});
