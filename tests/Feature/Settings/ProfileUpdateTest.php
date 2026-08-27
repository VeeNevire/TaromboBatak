<?php

use App\Models\Marga;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('profile page is displayed', function () {
    $marga = Marga::factory()->create(['name' => 'Sitorus']);
    $user = User::factory()->create(['marga_id' => $marga->id]);

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/profile')
            ->where('margas.0.id', $marga->id)
            ->where('margas.0.name', 'Sitorus')
            ->where('regions.0.code', '11')
            ->has('regions.0.regencies'));
});

test('profile information can be updated', function () {
    $marga = Marga::factory()->create();
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'marga_id' => $marga->id,
            'province_code' => '12',
            'regency_code' => '12.71',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
    expect($user->email_verified_at)->toBeNull();
    expect($user->marga_id)->toBe($marga->id);
    expect($user->province_code)->toBe('12');
    expect($user->regency_code)->toBe('12.71');
});

test('profile rejects a city or regency outside the selected province', function () {
    $user = User::factory()->create([
        'province_code' => '12',
        'regency_code' => '12.71',
    ]);

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'province_code' => '12',
            'regency_code' => '13.01',
        ])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHasErrors('regency_code');

    expect($user->fresh()->province_code)->toBe('12')
        ->and($user->fresh()->regency_code)->toBe('12.71');
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete(route('profile.destroy'), [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertGuest();
    expect($user->fresh())->toBeNull();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect(route('profile.edit'));

    expect($user->fresh())->not->toBeNull();
});
