<?php

use App\Models\User;

test('guests can view the landing page dashboard', function () {
    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->has('stories')
            ->has('events')
            ->has('margas'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('the Google Analytics tag is rendered only when its measurement id is configured', function () {
    config()->set('services.google.analytics_measurement_id', 'G-11B9BPF26M');

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('googletagmanager.com/gtag/js?id=G-11B9BPF26M', false)
        ->assertSee("gtag('config', 'G-11B9BPF26M'", false);

    config()->set('services.google.analytics_measurement_id', null);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('googletagmanager.com/gtag/js', false);
});
