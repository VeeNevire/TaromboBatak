<?php

use App\Models\ContributionRequest;
use App\Models\Marga;
use App\Models\Person;
use App\Models\TreeActivityLog;
use App\Models\TreeChangeRequest;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('a protected big-tree name requires contributor approval before deletion', function () {
    $marga = Marga::factory()->create();
    $requester = User::factory()->asSubAdmin()->create();
    $contributor = User::factory()
        ->asContributorMember()
        ->withMarga($marga->id)
        ->create();
    $root = Person::factory()->create(['marga_id' => $marga->id]);
    $person = Person::factory()->create([
        'name' => 'Nama Terlindungi',
        'marga_id' => $marga->id,
        'father_id' => $root->id,
    ]);
    ContributionRequest::factory()->approved()->create([
        'matched_father_id' => $root->id,
        'subject_person_id' => $person->id,
    ]);

    $this->actingAs($requester)
        ->from(route('people.show', $person))
        ->delete(route('people.destroy', $person))
        ->assertRedirect(route('people.show', $person));

    $change = TreeChangeRequest::query()->sole();

    expect($change->action)->toBe(TreeChangeRequest::ACTION_DELETE)
        ->and($change->protection_scope)->toBe(TreeChangeRequest::SCOPE_CONTRIBUTOR)
        ->and($person->fresh())->not->toBeNull();
    expect(TreeActivityLog::query()->where('action', 'change_requested')->exists())->toBeTrue();

    $this->actingAs($contributor)
        ->post(route('tree-activity-logs.approve', $change))
        ->assertRedirect();

    $this->assertModelMissing($person);
    expect($change->fresh()->status)->toBe(TreeChangeRequest::STATUS_APPROVED)
        ->and(TreeActivityLog::query()->where('action', 'removed')->exists())->toBeTrue();
});

test('a contributor can view big tree logs and pending changes', function () {
    $marga = Marga::factory()->create();
    $contributor = User::factory()
        ->asContributorMember()
        ->withMarga($marga->id)
        ->create();
    $person = Person::factory()->create([
        'name' => 'Nama Baru',
        'marga_id' => $marga->id,
        'birth_year' => '2000',
        'bio' => 'Biografi baru',
    ]);
    $requester = User::factory()->create();
    TreeActivityLog::query()->create([
        'person_id' => $person->id,
        'actor_id' => $requester->id,
        'marga_id' => $marga->id,
        'action' => 'added',
        'protection_scope' => TreeChangeRequest::SCOPE_CONTRIBUTOR,
        'summary' => 'Nama ditambahkan.',
        'details' => [
            'before' => [
                'name' => 'Nama Lama',
                'birth_year' => '1990',
                'bio' => 'Biografi lama',
            ],
        ],
    ]);
    TreeChangeRequest::query()->create([
        'person_id' => $person->id,
        'requester_id' => $requester->id,
        'marga_id' => $marga->id,
        'action' => TreeChangeRequest::ACTION_UPDATE,
        'protection_scope' => TreeChangeRequest::SCOPE_CONTRIBUTOR,
    ]);

    $this->actingAs($contributor)
        ->get(route('tree-activity-logs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tree-activity-logs/index')
            ->has('logs.data', 1)
            ->where('logs.data.0.changed_fields', ['Nama', 'Tahun lahir', 'Biografi'])
            ->has('logs.data.0.date')
            ->has('logs.data.0.time')
            ->has('changeRequests', 1)
            ->where('changeRequests.0.person', $person->name));
});
