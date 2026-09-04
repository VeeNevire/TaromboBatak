<?php

use App\Models\OAuthAccount;
use App\Models\User;

test('a google registration can be completed with the required domicile', function () {
    $this->withSession([
        'google_oauth' => [
            'provider' => 'google',
            'provider_id' => 'google-123',
            'email' => 'boru@gmail.com',
            'name' => 'Boru Google',
            'avatar' => null,
        ],
    ])->post(route('google.registration.store'), [
        'province_code' => '12',
        'regency_code' => '12.71',
        'district_code' => '12.71.01',
        'village_code' => '12.71.01.1001',
    ])->assertRedirect(route('dashboard', absolute: false));

    $user = User::query()->where('email', 'boru@gmail.com')->firstOrFail();

    $this->assertAuthenticatedAs($user);
    $this->assertDatabaseHas('oauth_accounts', [
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_id' => 'google-123',
    ]);
    expect($user->email_verified_at)->not->toBeNull();
});

test('google registration cannot be completed without a pending oauth session', function () {
    $this->post(route('google.registration.store'), [
        'province_code' => '12',
        'regency_code' => '12.71',
        'district_code' => '12.71.01',
        'village_code' => '12.71.01.1001',
    ])->assertForbidden();

    $this->assertGuest();
});

test('an oauth provider identity is unique', function () {
    $user = User::factory()->create();

    OAuthAccount::create([
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_id' => 'google-unique',
        'provider_email' => 'user@gmail.com',
    ]);

    expect(fn () => OAuthAccount::create([
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_id' => 'google-unique',
        'provider_email' => 'user@gmail.com',
    ]))->toThrow('UNIQUE constraint failed');

});
