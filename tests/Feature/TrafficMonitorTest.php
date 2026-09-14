<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('admins can view the embedded traffic monitor', function () {
    config()->set(
        'services.google.analytics_report_embed_url',
        'https://datastudio.google.com/embed/reporting/example',
    );

    $this->actingAs(User::factory()->asAdmin()->create())
        ->get(route('traffic-monitor.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('traffic-monitor/index')
            ->where(
                'reportUrl',
                'https://datastudio.google.com/embed/reporting/example',
            ));
});

test('non-admin users cannot view the traffic monitor', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('traffic-monitor.index'))
        ->assertForbidden();
});
