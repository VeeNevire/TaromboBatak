<?php

use App\Models\Marga;
use App\Models\User;

test('debug profile update with the exact browser payload', function () {
    $marga = Marga::factory()->create();
    $user = User::factory()->create(['name' => 'Nama Lama']);

    // The form always submits every hidden input, empty ones included.
    $response = $this->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), [
            'name' => 'Nama Baru',
            'email' => $user->email,
            'marga_id' => (string) $marga->id,
            'province_code' => '',
            'regency_code' => '',
            'district_code' => '',
            'village_code' => '',
        ]);

    dump([
        'status' => $response->getStatusCode(),
        'errors' => session('errors')?->getBag('default')->toArray(),
        'name' => $user->fresh()->name,
        'marga_id' => $user->fresh()->marga_id,
    ]);
});

test('debug profile update with full region payload', function () {
    $user = User::factory()->create(['name' => 'Nama Lama']);
    $province = \App\Support\IndonesiaRegions::all()[0];
    $regency = $province['regencies'][0];
    $districts = \App\Support\IndonesiaRegions::districtsFor($regency['code']);
    $district = $districts[0] ?? null;
    $villages = $district ? \App\Support\IndonesiaRegions::villagesFor($district['code']) : [];
    $village = $villages[0] ?? null;

    $response = $this->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), [
            'name' => 'Nama Wilayah',
            'email' => $user->email,
            'marga_id' => '',
            'province_code' => $province['code'],
            'regency_code' => $regency['code'],
            'district_code' => $district['code'] ?? '',
            'village_code' => $village['code'] ?? '',
        ]);

    dump([
        'status' => $response->getStatusCode(),
        'errors' => session('errors')?->getBag('default')->toArray(),
        'name' => $user->fresh()->name,
        'province' => $user->fresh()->province_code,
        'village' => $user->fresh()->village_code,
    ]);
});
