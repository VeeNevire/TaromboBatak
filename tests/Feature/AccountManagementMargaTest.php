<?php

use App\Models\ActivityLog;
use App\Models\FamilyTree;
use App\Models\FamilyTreeActivity;
use App\Models\Marga;
use App\Models\Person;
use App\Models\TaromboSnapshot;
use App\Models\TreeActivityLog;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
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

test('account form exposes public margas without lower-tree data for management', function () {
    $admin = User::factory()->asAdmin()->create();
    $public = Marga::factory()->public()->create(['name' => 'Marga Publik']);

    $this->actingAs($admin)
        ->get(route('accounts.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('managedMargaOptions', fn ($options) => collect($options)
                ->contains('id', $public->id)));
});

test('admin can assign a public marga without lower-tree data to a contributor', function () {
    $admin = User::factory()->asAdmin()->create();
    $public = Marga::factory()->public()->create(['name' => 'Marga Publik']);

    $this->actingAs($admin)
        ->post(route('accounts.store'), [
            'name' => 'Kontributor Publik',
            'email' => 'kontributor-publik@example.com',
            'role' => 'contributor_main',
            'marga_id' => $public->id,
            'managed_marga_ids' => [$public->id],
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
        ->assertRedirect(route('accounts.index'))
        ->assertSessionHasNoErrors();

    $account = User::query()->where('email', 'kontributor-publik@example.com')->firstOrFail();

    expect($account->managedMargas()->pluck('margas.id')->all())->toBe([$public->id]);
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

test('an admin can deactivate an account without deleting its data', function () {
    $admin = User::factory()->asAdmin()->create();
    $account = User::factory()->create();

    $this->actingAs($admin)
        ->patch(route('accounts.deactivate', $account))
        ->assertRedirect(route('accounts.index'))
        ->assertSessionHasNoErrors();

    expect($account->fresh()->isActive())->toBeFalse();
    $this->assertModelExists($account);
    expect(ActivityLog::query()
        ->where('account_id', $account->id)
        ->value('description'))->toBe('Akun dinonaktifkan.');

    $this->actingAs($admin)
        ->get(route('accounts.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('accounts.data.0.is_active', false));
});

test('an admin cannot deactivate their own account', function () {
    $admin = User::factory()->asAdmin()->create();

    $this->actingAs($admin)
        ->patch(route('accounts.deactivate', $admin))
        ->assertForbidden();

    expect($admin->fresh()->isActive())->toBeTrue();
});

test('legacy people activity logs show the account primary family tree without inventing member context', function () {
    $admin = User::factory()->asAdmin()->create();
    $account = User::factory()->asSubAdmin()->create();
    $root = Person::factory()->create(['name' => 'Akar Log Lama']);
    FamilyTree::create([
        'user_id' => $account->id,
        'root_person_id' => $root->id,
        'name' => 'Keluarga Utama Lama',
        'is_primary' => true,
    ]);
    ActivityLog::create([
        'account_id' => $account->id,
        'actor_id' => $account->id,
        'account_name' => $account->name,
        'account_email' => $account->email,
        'action' => 'people.update',
        'description' => 'Melakukan perubahan data melalui people.update.',
        'metadata' => ['method' => 'PUT', 'route' => 'people.update'],
    ]);

    $this->actingAs($admin)
        ->get(route('accounts.activity-log', $account))
        ->assertOk()
        ->assertJsonPath('logs.0.context.is_legacy', true)
        ->assertJsonPath('logs.0.context.person_name', null)
        ->assertJsonPath('logs.0.context.father_name', null)
        ->assertJsonPath('logs.0.context.family_tree_name', 'Keluarga Utama Lama');
});

test('non-person activity logs do not receive people context', function () {
    $admin = User::factory()->asAdmin()->create();
    $account = User::factory()->asSubAdmin()->create();
    ActivityLog::create([
        'account_id' => $account->id,
        'actor_id' => $account->id,
        'account_name' => $account->name,
        'account_email' => $account->email,
        'action' => 'stories.store',
        'description' => 'Membuat cerita.',
        'metadata' => ['method' => 'POST', 'route' => 'stories.store'],
    ]);

    $this->actingAs($admin)
        ->get(route('accounts.activity-log', $account))
        ->assertOk()
        ->assertJsonPath('logs.0.context', null);
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

test('admin can create a non-contributor account with the payload sent by the account form', function () {
    $admin = User::factory()->asAdmin()->create();

    $this->actingAs($admin)
        ->post(route('accounts.store'), [
            'name' => 'Pengguna Biasa',
            'email' => 'pengguna-biasa@example.com',
            'role' => 'user',
            'marga_id' => null,
            'province_code' => '',
            'regency_code' => '',
            'district_code' => '',
            'village_code' => '',
            'managed_marga_ids' => [],
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
        ->assertRedirect(route('accounts.index'))
        ->assertSessionHasNoErrors();

    expect(User::query()->where('email', 'pengguna-biasa@example.com')->exists())->toBeTrue();
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

test('account creation reports all missing requirements in Indonesian at once', function () {
    $this->actingAs(User::factory()->asAdmin()->create())
        ->post(route('accounts.store'), [])
        ->assertSessionHasErrors([
            'name' => 'Nama wajib diisi.',
            'email' => 'Email wajib diisi.',
            'role' => 'Peran wajib diisi.',
            'password' => 'Kata sandi wajib diisi.',
        ]);
});

test('account creation includes every failed password requirement in one message', function () {
    $original = Password::$defaultCallback;
    Password::defaults(fn () => Password::min(12)->mixedCase()->letters()->numbers()->symbols());

    try {
        $this->actingAs(User::factory()->asAdmin()->create())
            ->post(route('accounts.store'), [
                'name' => '',
                'email' => 'bukan-email',
                'role' => 'user',
                'password' => 'abc',
                'password_confirmation' => 'beda',
            ])
            ->assertSessionHasErrors(['name', 'email', 'password']);

        $message = session('errors')->first('password');
        expect($message)
            ->toContain('Konfirmasi kata sandi tidak sama dengan kata sandi.')
            ->toContain('Kata sandi minimal 12 karakter.')
            ->toContain('Kata sandi harus mengandung huruf besar dan huruf kecil.')
            ->toContain('Kata sandi harus mengandung setidaknya satu angka.')
            ->toContain('Kata sandi harus mengandung setidaknya satu simbol.')
            ->not->toContain('The password');
    } finally {
        Password::defaults($original);
    }
});

test('account activity combines actions as actor and historical family and tree logs', function () {
    $admin = User::factory()->asAdmin()->create();
    $account = User::factory()->create();
    $other = User::factory()->create();
    $person = Person::factory()->create();
    ActivityLog::forceCreate([
        'account_id' => $other->id, 'actor_id' => $account->id,
        'account_name' => $other->name, 'account_email' => $other->email,
        'action' => 'updated', 'description' => 'Mengubah akun lain.',
        'created_at' => now()->subHours(3),
    ]);
    FamilyTreeActivity::forceCreate([
        'owner_id' => $other->id, 'actor_id' => $account->id,
        'tree_name' => 'Keluarga Lama', 'member_name' => 'Anggota Lama',
        'action' => 'updated', 'description' => 'Mengubah keluarga lama.',
        'created_at' => now()->subHours(2),
    ]);
    TreeActivityLog::forceCreate([
        'actor_id' => $account->id, 'person_id' => $person->id,
        'action' => 'updated', 'summary' => 'Mengubah pohon besar.',
        'created_at' => now()->subHour(),
    ]);
    ActivityLog::forceCreate([
        'account_id' => $other->id, 'actor_id' => $other->id,
        'account_name' => $other->name, 'account_email' => $other->email,
        'action' => 'updated', 'description' => 'Aktivitas akun lain.',
    ]);

    $this->actingAs($admin)->getJson(route('accounts.activity-log', $account))
        ->assertSuccessful()->assertJsonCount(3, 'logs')
        ->assertJsonPath('logs.0.description', 'Mengubah pohon besar.')
        ->assertJsonPath('logs.1.context.family_tree_name', 'Keluarga Lama')
        ->assertJsonPath('logs.2.action', 'updated');
});

test('successful changes are logged for every account role', function (string $role) {
    Storage::fake('local');
    $actor = User::factory()->create(['role' => $role]);
    $snapshot = TaromboSnapshot::factory()->for($actor)->create();

    $this->actingAs($actor)->delete(route('tarombo.snapshots.destroy', $snapshot))->assertRedirect();
    expect(ActivityLog::where('account_id', $actor->id)->where('action', 'tarombo.snapshots.destroy')->count())->toBe(1);
})->with(['user', 'contributor_main', 'contributor_member', 'admin', 'subadmin']);

test('a rejected account change does not create an activity log', function () {
    $actor = User::factory()->create();
    $this->actingAs($actor)->post(route('tarombo.snapshots.store'), [])->assertSessionHasErrors();
    expect(ActivityLog::where('actor_id', $actor->id)->exists())->toBeFalse();
});
