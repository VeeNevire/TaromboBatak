<?php

use App\Models\Marga;
use App\Models\Person;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('accounts are ordered by name ascending by default', function () {
    $admin = User::factory()->asAdmin()->create(['name' => 'Budi']);
    User::factory()->create(['name' => 'Ana']);
    User::factory()->create(['name' => 'Citra']);

    $this->actingAs($admin)
        ->get(route('accounts.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.sort', 'name')
            ->where('filters.direction', 'asc')
            ->where('accounts.data.0.name', 'Ana')
            ->where('accounts.data.1.name', 'Budi')
            ->where('accounts.data.2.name', 'Citra'));
});

test('every sortable column works in both directions', function (string $column) {
    $admin = User::factory()->asAdmin()->create();
    User::factory()->count(3)->create();

    foreach (['asc', 'desc'] as $direction) {
        $this->actingAs($admin)
            ->get(route('accounts.index', ['sort' => $column, 'direction' => $direction]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.sort', $column)
                ->where('filters.direction', $direction)
                ->has('accounts.data', 4));
    }
})->with(['name', 'email', 'role', 'current_person', 'marga', 'created_at', 'last_active_at']);

test('sorting by the related marga name orders accounts alphabetically', function () {
    $admin = User::factory()->asAdmin()->create(['name' => 'Admin']);
    $zeta = Marga::factory()->create(['name' => 'Zeta']);
    $alpha = Marga::factory()->create(['name' => 'Alpha']);
    User::factory()->withMarga($zeta->id)->create(['name' => 'Pemilik Zeta']);
    User::factory()->withMarga($alpha->id)->create(['name' => 'Pemilik Alpha']);

    $this->actingAs($admin)
        ->get(route('accounts.index', ['sort' => 'marga', 'direction' => 'desc']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('accounts.data.0.marga', 'Zeta')
            ->where('accounts.data.1.marga', 'Alpha'));
});

test('sorting by the linked person orders accounts by that person name', function () {
    $admin = User::factory()->asAdmin()->create();
    $zulkarnain = Person::factory()->create(['name' => 'Zulkarnain']);
    $abdul = Person::factory()->create(['name' => 'Abdul']);
    User::factory()->create(['name' => 'Akun Zulkarnain', 'current_person_id' => $zulkarnain->id]);
    User::factory()->create(['name' => 'Akun Abdul', 'current_person_id' => $abdul->id]);

    $this->actingAs($admin)
        ->get(route('accounts.index', ['sort' => 'current_person', 'direction' => 'desc']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('accounts.data.0.current_person', 'Zulkarnain'));
});

test('an unknown sort column falls back to name ascending', function () {
    $admin = User::factory()->asAdmin()->create();

    $this->actingAs($admin)
        ->get(route('accounts.index', ['sort' => 'password', 'direction' => 'desc']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.sort', 'name')
            ->where('filters.direction', 'asc'));
});

test('last activity is displayed in WIB and can be sorted', function () {
    $admin = User::factory()->asAdmin()->create();
    $older = User::factory()->create(['name' => 'Akses Lama']);
    $recent = User::factory()->create(['name' => 'Akses Baru']);
    $older->forceFill(['last_active_at' => '2026-10-08 03:00:00'])->save();
    $recent->forceFill(['last_active_at' => '2026-10-09 03:00:00'])->save();

    $this->actingAs($admin)->get(route('accounts.index', ['search' => 'Akses', 'sort' => 'last_active_at', 'direction' => 'desc']))
        ->assertSuccessful()->assertInertia(fn (Assert $page) => $page
        ->where('accounts.data.0.id', $recent->id)
        ->where('accounts.data.0.last_active_at', '09 Oct 2026 10:00 WIB')
        ->where('accounts.data.1.id', $older->id));
});

test('browsing records account access without changing the profile edit timestamp', function () {
    $user = User::factory()->create();
    $updatedAt = $user->updated_at->toDateTimeString();
    $this->travelTo(now()->addHour());

    $this->actingAs($user)->get(route('marga.index'))->assertSuccessful();
    expect($user->fresh()->last_active_at->toDateTimeString())->toBe(now()->toDateTimeString())
        ->and($user->fresh()->updated_at->toDateTimeString())->toBe($updatedAt);
    $this->travelBack();
});
