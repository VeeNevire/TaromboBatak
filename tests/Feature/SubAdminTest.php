<?php

use App\Models\Marga;
use App\Models\User;

test('sub-admins can access staff data routes', function () {
    $subAdmin = User::factory()->asSubAdmin()->create();

    $this->actingAs($subAdmin)
        ->get(route('people.index'))
        ->assertOk();

    $this->actingAs($subAdmin)
        ->get(route('marga.index'))
        ->assertOk();

    $this->actingAs($subAdmin)
        ->get(route('stories.index'))
        ->assertOk();

    $this->actingAs($subAdmin)
        ->get(route('events.index'))
        ->assertOk();
});

test('sub-admins can manage people', function () {
    $subAdmin = User::factory()->asSubAdmin()->create();
    $marga = Marga::factory()->create();

    $this->actingAs($subAdmin)
        ->get(route('people.create'))
        ->assertOk();

    $this->actingAs($subAdmin)
        ->post(route('people.store'), [
            'name' => 'Orang Baru',
            'marga_id' => $marga->id,
        ])
        ->assertRedirect(route('people.index'));
});

test('sub-admins cannot access sub-admin management routes', function () {
    $subAdmin = User::factory()->asSubAdmin()->create();

    $this->actingAs($subAdmin)->get(route('sub-admins.index'))->assertForbidden();
    $this->actingAs($subAdmin)->get(route('sub-admins.create'))->assertForbidden();
});

test('regular users are forbidden from sub-admin management routes', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('sub-admins.index'))->assertForbidden();
});

test('admins can create a sub-admin', function () {
    $admin = User::factory()->asAdmin()->create();
    $marga = Marga::factory()->create();

    $this->actingAs($admin)
        ->post(route('sub-admins.store'), [
            'name' => 'Sub Admin Baru',
            'email' => 'subadmin@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'marga_id' => $marga->id,
        ])
        ->assertRedirect(route('sub-admins.index'));

    $this->assertDatabaseHas('users', [
        'email' => 'subadmin@example.com',
        'role' => 'subadmin',
        'marga_id' => $marga->id,
    ]);
});

test('admins can update a sub-admin', function () {
    $admin = User::factory()->asAdmin()->create();
    $subAdmin = User::factory()->asSubAdmin()->create(['name' => 'Lama']);

    $this->actingAs($admin)
        ->put(route('sub-admins.update', $subAdmin), [
            'name' => 'Baru',
            'email' => $subAdmin->email,
            'marga_id' => null,
        ])
        ->assertRedirect(route('sub-admins.index'));

    $this->assertDatabaseHas('users', [
        'id' => $subAdmin->id,
        'name' => 'Baru',
    ]);
});

test('admins can delete a sub-admin', function () {
    $admin = User::factory()->asAdmin()->create();
    $subAdmin = User::factory()->asSubAdmin()->create();

    $this->actingAs($admin)
        ->delete(route('sub-admins.destroy', $subAdmin))
        ->assertRedirect(route('sub-admins.index'));

    $this->assertDatabaseMissing('users', ['id' => $subAdmin->id]);
});

test('creating a sub-admin requires a unique email', function () {
    $admin = User::factory()->asAdmin()->create();
    User::factory()->create(['email' => 'same@example.com']);

    $this->actingAs($admin)
        ->post(route('sub-admins.store'), [
            'name' => 'Sub Admin',
            'email' => 'same@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
        ->assertSessionHasErrors('email');
});
