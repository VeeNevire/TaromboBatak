<?php

use App\Models\User;

test('guests can view the public traffic report', function () {
    config()->set('services.google.analytics_report_embed_url', 'https://lookerstudio.google.com/embed/reporting/example/page/example');

    $this->get(route('traffic-monitor.public'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('traffic-monitor/public')
            ->where('reportUrl', 'https://lookerstudio.google.com/embed/reporting/example/page/example'));
});

test('admins can view the dashboard traffic monitor', function () {
    config()->set('services.google.analytics_report_embed_url', 'https://lookerstudio.google.com/embed/reporting/example/page/example');

    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get(route('traffic-monitor.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('traffic-monitor/index')
            ->where('reportUrl', 'https://lookerstudio.google.com/embed/reporting/example/page/example'));
});

test('non-admins cannot view the dashboard traffic monitor', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('traffic-monitor.index'))
        ->assertForbidden();
});
