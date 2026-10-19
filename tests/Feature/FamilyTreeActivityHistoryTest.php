<?php

use App\Models\FamilyTree;
use App\Models\FamilyTreeActivity;
use App\Models\Person;
use App\Models\User;
use App\Services\FamilyTreeActivityLogger;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

function historicalActivity(User $actor, string $time, ?User $owner = null): FamilyTreeActivity
{
    $activity = new FamilyTreeActivity([
        'actor_id' => $actor->id,
        'owner_id' => ($owner ?? $actor)->id,
        'tree_name' => 'Keluarga Uji',
        'action' => 'added',
        'description' => 'Menambahkan anggota.',
    ]);
    $activity->created_at = $time;
    $activity->save();

    return $activity;
}

test('history starts with the original account creation and includes entries older than the latest hundred', function () {
    $owner = User::factory()->create(['created_at' => '2025-01-01 00:00:00']);
    for ($day = 0; $day < 105; $day++) {
        historicalActivity($owner, CarbonImmutable::parse('2025-02-01')->addDays($day)->toDateTimeString());
    }

    $this->actingAs($owner)->get(route('family-tree-activities.index', ['order' => 'oldest']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('pagination.total', 106)
        ->where('pagination.last_page', 5)
        ->has('activities', 25)
        ->where('activities.0.action', 'account_created')
        ->where('activities.0.created_at', '01 Jan 2025, 07:00 WIB')
        ->where('activities.1.action', 'added'));

    $this->get(route('family-tree-activities.index', ['order' => 'oldest', 'page' => 5]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('pagination.current_page', 5)
        ->has('activities', 6)
        ->where('filters.order', 'oldest'));
});

test('staff can combine actor and Jakarta date filters including midnight boundaries', function () {
    $admin = User::factory()->asAdmin()->create();
    $actor = User::factory()->create(['created_at' => '2025-01-01']);
    historicalActivity($actor, '2026-01-01 16:59:59');
    $first = historicalActivity($actor, '2026-01-01 17:00:00');
    $last = historicalActivity($actor, '2026-01-02 16:59:59');
    historicalActivity($actor, '2026-01-02 17:00:00');
    historicalActivity($admin, '2026-01-02 05:00:00');

    $this->actingAs($admin)->get(route('family-tree-activities.index', [
        'account_id' => $actor->id, 'date' => '2026-01-02', 'order' => 'oldest',
    ]))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('activities', 2)
        ->where('activities.0.id', 'tree-'.$first->id)
        ->where('activities.1.id', 'tree-'.$last->id)
        ->where('activities.0.created_at', '02 Jan 2026, 00:00 WIB')
        ->where('activities.1.created_at', '02 Jan 2026, 23:59 WIB'));
});

test('account filters cannot expose another accounts private history or account choices', function () {
    $viewer = User::factory()->create();
    $other = User::factory()->create();
    historicalActivity($other, '2026-01-01 01:00:00');

    $this->actingAs($viewer)->get(route('family-tree-activities.index', ['account_id' => $other->id]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('activities', 0)
        ->has('accounts', 1)
        ->where('accounts.0.id', $viewer->id));
});

test('actors retain their own activities even when another account owns the tree', function () {
    $actor = User::factory()->create();
    $owner = User::factory()->create();
    $activity = historicalActivity($actor, '2026-01-02 05:00:00', $owner);

    $this->actingAs($actor)->get(route('family-tree-activities.index', ['date' => '2026-01-02']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('activities', 1)
        ->where('activities.0.id', 'tree-'.$activity->id));
});

test('invalid activity filters are rejected', function (array $filter, string $field) {
    $this->actingAs(User::factory()->create())->get(route('family-tree-activities.index', $filter))
        ->assertSessionHasErrors($field);
})->with([
    [['date' => '2026-02-30'], 'date'],
    [['account_id' => -1], 'account_id'],
    [['order' => 'unknown'], 'order'],
    [['search' => str_repeat('x', 256)], 'search'],
    [['search' => ['invalid']], 'search'],
]);

test('activity search matches member family description and actor while keeping filters', function (string $term) {
    $admin = User::factory()->asAdmin()->create();
    $actor = User::factory()->create(['name' => 'Petugas Marbun', 'created_at' => '2025-01-01']);
    $match = historicalActivity($actor, '2026-01-02 05:00:00');
    $match->update(['tree_name' => 'Keluarga Sihombing', 'member_name' => 'Tuan Samosir', 'father_name' => 'Raja Lontung', 'description' => 'Memperbarui tahun kelahiran.']);
    historicalActivity($admin, '2026-01-02 05:00:00');
    historicalActivity($actor, '2026-01-03 05:00:00');

    $this->actingAs($admin)->get(route('family-tree-activities.index', [
        'search' => $term, 'account_id' => $actor->id, 'date' => '2026-01-02',
    ]))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('filters.search', trim($term))
        ->where('pagination.total', 1)
        ->has('activities', 1)
        ->where('activities.0.id', 'tree-'.$match->id));
})->with(['member' => 'Samosir', 'father' => 'Lontung', 'family' => 'Sihombing', 'description' => 'kelahiran', 'actor' => 'Marbun', 'trimmed search' => '  Samosir  ']);

test('search cannot expose private activity and finds account creation by actor name', function () {
    $viewer = User::factory()->create(['name' => 'Viewer Search']);
    $other = User::factory()->create();
    $private = historicalActivity($other, '2026-01-02 05:00:00');
    $private->update(['description' => 'Rahasia Samosir']);
    $this->actingAs($viewer)->get(route('family-tree-activities.index', ['search' => 'Samosir']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->has('activities', 0));
    $this->get(route('family-tree-activities.index', ['search' => 'Viewer Search']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('activities', 1)->where('activities.0.action', 'account_created'));
});

test('activity log records and shows the father name of the member', function () {
    $admin = User::factory()->asAdmin()->create();
    $father = Person::factory()->create(['name' => 'Raja Ayah']);
    $member = Person::factory()->create(['name' => 'Anak Uji', 'father_id' => $father->id, 'pending_father' => false]);
    $tree = FamilyTree::create(['name' => 'Keluarga Uji', 'user_id' => $admin->id, 'root_person_id' => $member->id]);

    app(FamilyTreeActivityLogger::class)->log($tree, $admin, 'updated', 'Memperbarui anggota.', $member);

    $this->actingAs($admin)->get(route('family-tree-activities.index', ['search' => 'Anak']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('activities', 1)
            ->where('activities.0.member_name', 'Anak Uji')
            ->where('activities.0.father_name', 'Raja Ayah'));
});

test('older activity logs without a stored father name fall back to the member in that tree', function () {
    $admin = User::factory()->asAdmin()->create();
    $father = Person::factory()->create(['name' => 'Raja Lama']);
    $member = Person::factory()->create(['name' => 'Anak Lama', 'father_id' => $father->id, 'pending_father' => false]);
    $tree = FamilyTree::create(['name' => 'Keluarga Uji', 'user_id' => $admin->id, 'root_person_id' => $father->id]);
    $tree->people()->attach([$father->id, $member->id]);
    FamilyTreeActivity::create([
        'family_tree_id' => $tree->id, 'owner_id' => $admin->id, 'actor_id' => $admin->id,
        'tree_name' => 'Keluarga Lama', 'member_name' => 'Anak Lama', 'action' => 'updated', 'description' => 'Mengedit Anak Lama.',
    ]);

    $this->actingAs($admin)->get(route('family-tree-activities.index', ['search' => 'Anak']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('activities', 1)
            ->where('activities.0.member_name', 'Anak Lama')
            ->where('activities.0.father_name', 'Raja Lama'));
});
