<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('registration form provides cascading Indonesian domicile options to guests', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/login')
            ->has('regions', 38)
            ->where('regions.0.code', '11')
            ->where('regions.0.name', 'Aceh')
            ->where('regions.0.regencies.0.code', '11.01')
            ->where('regions.0.regencies.0.name', 'Kabupaten Aceh Selatan'));

    $this->getJson(route('regions.districts', ['regencyCode' => '12.71']))
        ->assertOk()
        ->assertJsonPath('data.0.code', '12.71.01');

    $this->getJson(route('regions.villages', ['districtCode' => '12.71.01']))
        ->assertOk()
        ->assertJsonPath('data.0.code', '12.71.01.1001');
});

test('users can register with a complete hierarchical domicile', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Boru Sitorus',
        'email' => 'boru@example.com',
        'province_code' => '12',
        'regency_code' => '12.71',
        'district_code' => '12.71.01',
        'village_code' => '12.71.01.1001',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $response->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticated();

    $user = User::query()->where('email', 'boru@example.com')->firstOrFail();

    expect($user->province_code)->toBe('12')
        ->and($user->regency_code)->toBe('12.71')
        ->and($user->district_code)->toBe('12.71.01')
        ->and($user->village_code)->toBe('12.71.01.1001');
});

test('registration rejects a regency outside the selected province', function () {
    $this->post(route('register.store'), [
        'name' => 'Boru Sitorus',
        'email' => 'boru@example.com',
        'province_code' => '12',
        'regency_code' => '13.01',
        'district_code' => '13.01.01',
        'village_code' => '13.01.01.2001',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ])->assertSessionHasErrors('regency_code');

    $this->assertGuest();
});

test('registration rejects a district or village outside its selected parent', function () {
    $this->post(route('register.store'), [
        'name' => 'Boru Sitorus',
        'email' => 'boru@example.com',
        'province_code' => '12',
        'regency_code' => '12.71',
        'district_code' => '13.71.01',
        'village_code' => '13.71.02.1001',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ])->assertSessionHasErrors(['district_code', 'village_code']);

    $this->assertGuest();
});

test('registration requires a complete domicile hierarchy', function () {
    $this->post(route('register.store'), [
        'name' => 'Boru Sitorus',
        'email' => 'boru@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ])->assertSessionHasErrors([
        'province_code',
        'regency_code',
        'district_code',
        'village_code',
    ]);

    $this->assertGuest();
});
