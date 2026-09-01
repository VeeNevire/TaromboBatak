<?php

use App\Models\Marga;
use App\Models\Person;
use App\Models\User;
use App\Models\ActivityLog;
use Inertia\Testing\AssertableInertia as Assert;

function margaWithLowerTree(string $name): Marga
{
    $marga = Marga::factory()->create(['name' => $name]);
    $identity = Person::factory()->create(['marga_id' => $marga->id]);
    $marga->update(['identity_person_id' => $identity->id]);

    return $marga;
}

test('account form exposes only margas with lower-tree data for contributor management', function () {
    $admin = User::factory()->asAdmin()->create();
    $available = margaWithLowerTree('Sitorus');
    Marga::factory()->create(['name' => 'Tanpa Pohon']);

    $this->actingAs($admin)
        ->get(route('accounts.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('managedMargaOptions.0.id', $available->id)
            ->where('managedMargaOptions.0.name', 'Sitorus')
            ->where('managedMargaIds', []));
});

test('admin can assign multiple lower-tree margas to a contributor', function () {
    $admin = User::factory()->asAdmin()->create();
    $first = margaWithLowerTree('Sitorus');
    $second = margaWithLowerTree('Silaban');

    $this->actingAs($admin)
        ->post(route('accounts.store'), [
            'name' => 'Kontributor Marga',
            'email' => 'kontributor-marga@example.com',
            'role' => 'contributor_main',
            'marga_id' => $first->id,
            'managed_marga_ids' => [$first->id, $second->id],
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
        ->assertRedirect(route('accounts.index'))
        ->assertSessionHasNoErrors();

    $account = User::query()->where('email', 'kontributor-marga@example.com')->firstOrFail();

    expect($account->managedMargas()->pluck('margas.id')->sort()->values()->all())
        ->toBe([$first->id, $second->id]);
});

test('changing a contributor to another role removes marga management assignments', function () {
    $admin = User::factory()->asAdmin()->create();
    $marga = margaWithLowerTree('Sitorus');
    $account = User::factory()->asMainContributor()->withMarga($marga->id)->create();
    $account->managedMargas()->attach($marga);

    $this->actingAs($admin)
        ->put(route('accounts.update', $account), [
            'name' => $account->name,
            'email' => $account->email,
            'role' => 'user',
            'marga_id' => $marga->id,
            'password' => '',
            'password_confirmation' => '',
        ])
        ->assertRedirect(route('accounts.index'))
        ->assertSessionHasNoErrors();

    expect($account->fresh()->managedMargas()->count())->toBe(0);
});

test('account changes are recorded and visible to admins', function () {
    $admin = User::factory()->asAdmin()->create();
    $account = User::factory()->create();

    $this->actingAs($admin)
        ->put(route('accounts.update', $account), [
            'name' => 'Nama Diperbarui',
            'email' => $account->email,
            'role' => 'user',
            'marga_id' => null,
            'password' => '',
            'password_confirmation' => '',
        ])
        ->assertRedirect(route('accounts.index'));

    $this->actingAs($admin)
        ->get(route('accounts.activity-log', $account))
        ->assertOk()
        ->assertJsonPath('logs.0.description', 'Data akun diperbarui.')
        ->assertJsonPath('logs.0.actor', $admin->name);

    expect(ActivityLog::query()->where('account_id', $account->id)->count())->toBe(1);
});

test('account form provides cascading district and village options', function () {
    $admin = User::factory()->asAdmin()->create();

    $this->actingAs($admin)
        ->get(route('accounts.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('regions', fn ($regions) => collect($regions)->contains(
                fn (array $province) => $province['code'] === '12'
                    && collect($province['regencies'])->contains(
                        fn (array $regency) => $regency['code'] === '12.71',
                    ),
            )));

    $this->getJson(route('regions.districts', ['regencyCode' => '12.71']))
        ->assertOk()
        ->assertJsonPath('data.0.code', '12.71.01');

    $this->getJson(route('regions.villages', ['districtCode' => '12.71.01']))
        ->assertOk()
        ->assertJsonPath('data.0.code', '12.71.01.1001');
});

test('admin can save a complete hierarchical domicile on an account', function () {
    $admin = User::factory()->asAdmin()->create();

    $this->actingAs($admin)
        ->post(route('accounts.store'), [
            'name' => 'Pengguna Berdomisili',
            'email' => 'domisili@example.com',
            'role' => 'user',
            'marga_id' => null,
            'province_code' => '12',
            'regency_code' => '12.71',
            'district_code' => '12.71.01',
            'village_code' => '12.71.01.1001',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
        ->assertRedirect(route('accounts.index'))
        ->assertSessionHasNoErrors();

    $account = User::query()->where('email', 'domisili@example.com')->firstOrFail();

    expect($account->province_code)->toBe('12')
        ->and($account->regency_code)->toBe('12.71')
        ->and($account->district_code)->toBe('12.71.01')
        ->and($account->village_code)->toBe('12.71.01.1001');
});

test('account domicile rejects a district or village outside its parent region', function () {
    $admin = User::factory()->asAdmin()->create();

    $this->actingAs($admin)
        ->post(route('accounts.store'), [
            'name' => 'Alamat Tidak Sesuai',
            'email' => 'alamat-salah@example.com',
            'role' => 'user',
            'marga_id' => null,
            'province_code' => '12',
            'regency_code' => '12.71',
            'district_code' => '13.71.01',
            'village_code' => '13.71.02.1001',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
        ->assertSessionHasErrors(['district_code', 'village_code']);
});
