<?php

use App\Models\User;

test('admin can deactivate a sub admin', function () {
    $admin = User::factory()->asAdmin()->create();
    $subAdmin = User::factory()->create(['role' => 'subadmin']);

    $this->actingAs($admin)
        ->patch(route('sub-admins.deactivate', $subAdmin))
        ->assertRedirect(route('sub-admins.index'));

    expect($subAdmin->fresh()->is_active)->toBeFalse();
});

test('deactivate is limited to sub admin accounts', function () {
    $admin = User::factory()->asAdmin()->create();

    $this->actingAs($admin)
        ->patch(route('sub-admins.deactivate', User::factory()->create()))
        ->assertNotFound();
});

test('non admins cannot deactivate a sub admin', function () {
    $subAdmin = User::factory()->create(['role' => 'subadmin']);

    $this->actingAs(User::factory()->create())
        ->patch(route('sub-admins.deactivate', $subAdmin))
        ->assertForbidden();
});
