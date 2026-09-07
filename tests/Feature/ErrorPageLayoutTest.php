<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('an authenticated 404 includes the sidebar context', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/missing-authenticated-page')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Error/Page')
            ->where('status', 404)
            ->where('auth.user.id', $user->id)
            ->where('sidebarOpen', true)
            ->where('unreadContributionCount', 0));
});

test('a guest 404 keeps a null user context', function () {
    $this->get('/missing-guest-page')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Error/Page')
            ->where('status', 404)
            ->where('auth.user', null));
});
