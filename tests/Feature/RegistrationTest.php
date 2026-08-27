<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('registration form provides Indonesian provinces and regencies', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/login')
            ->has('regions', 38)
            ->where('regions.0.code', '11')
            ->where('regions.0.name', 'Aceh')
            ->where('regions.0.regencies.0.code', '11.01')
            ->where('regions.0.regencies.0.name', 'Kabupaten Aceh Selatan'));
});

test('users can register with a domicile province and regency', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Boru Sitorus',
        'email' => 'boru@example.com',
        'province_code' => '12',
        'regency_code' => '12.71',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $response->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticated();

    $user = User::query()->where('email', 'boru@example.com')->firstOrFail();

    expect($user->province_code)->toBe('12')
        ->and($user->regency_code)->toBe('12.71');
});

test('registration rejects a regency outside the selected province', function () {
    $this->post(route('register.store'), [
        'name' => 'Boru Sitorus',
        'email' => 'boru@example.com',
        'province_code' => '12',
        'regency_code' => '13.01',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ])->assertSessionHasErrors('regency_code');

    $this->assertGuest();
});

test('registration requires a domicile province and regency', function () {
    $this->post(route('register.store'), [
        'name' => 'Boru Sitorus',
        'email' => 'boru@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ])->assertSessionHasErrors(['province_code', 'regency_code']);

    $this->assertGuest();
});
