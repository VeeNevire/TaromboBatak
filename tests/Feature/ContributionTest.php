<?php

use App\Models\ContributionRequest;
use App\Models\Marga;
use App\Models\Person;
use App\Models\User;

test('matching an existing father creates a pending contribution and notifies contributors', function () {
    $marga = Marga::factory()->create();
    $user = User::factory()->withMarga($marga->id)->create();
    $main = User::factory()->asMainContributor()->withMarga($marga->id)->create();
    $member = User::factory()->asContributorMember()->withMarga($marga->id)->create();
    $father = Person::factory()->create([
        'name' => 'Ayah Terdaftar',
        'gender' => 'L',
        'marga_id' => $marga->id,
    ]);

    $this->actingAs($user)->post(route('people.store'), [
        'name' => 'Anak Pengaju',
        'gender' => 'L',
        'marga_id' => $marga->id,
        'birth_order' => 1,
        'sibling_count' => 1,
        'father' => ['name' => $father->name],
        'children' => [['name' => 'Anak Pengaju', 'gender' => 'L']],
    ])->assertRedirect(route('people.index'))
        ->assertSessionHasNoErrors();

    $child = Person::where('name', 'Anak Pengaju')->firstOrFail();
    $contribution = ContributionRequest::firstOrFail();

    expect($child->father_id)->toBeNull()
        ->and($child->pending_father)->toBeTrue()
        ->and($contribution->requester_id)->toBe($user->id)
        ->and($contribution->matched_father_id)->toBe($father->id)
        ->and($contribution->subject_person_id)->toBe($child->id)
        ->and($contribution->status)->toBe('pending')
        ->and($main->notifications()->count())->toBe(1)
        ->and($member->notifications()->count())->toBe(1);
});

test('a contributor for the same marga can approve a pending father match', function () {
    $marga = Marga::factory()->create();
    $user = User::factory()->withMarga($marga->id)->create();
    $contributor = User::factory()->asMainContributor()->withMarga($marga->id)->create();
    $father = Person::factory()->create(['marga_id' => $marga->id]);
    $child = Person::factory()->create([
        'marga_id' => $marga->id,
        'created_by' => $user->id,
        'father_id' => null,
        'pending_father' => true,
    ]);
    $request = ContributionRequest::factory()->create([
        'requester_id' => $user->id,
        'matched_father_id' => $father->id,
        'subject_person_id' => $child->id,
        'affected_person_ids' => [$child->id],
    ]);

    $this->actingAs($contributor)
        ->post(route('contributions.approve', $request))
        ->assertRedirect(route('contributions.index'))
        ->assertSessionHasNoErrors();

    expect($child->fresh()->father_id)->toBe($father->id)
        ->and($child->fresh()->pending_father)->toBeFalse()
        ->and($request->fresh()->status)->toBe('approved')
        ->and($request->fresh()->reviewed_by)->toBe($contributor->id);
});

test('an ordinary user cannot edit the approved matched father or ancestors above him', function () {
    $marga = Marga::factory()->create();
    $user = User::factory()->withMarga($marga->id)->create();
    $grandfather = Person::factory()->create(['marga_id' => $marga->id]);
    $father = Person::factory()->create([
        'marga_id' => $marga->id,
        'father_id' => $grandfather->id,
    ]);
    $child = Person::factory()->create([
        'marga_id' => $marga->id,
        'created_by' => $user->id,
        'father_id' => $father->id,
    ]);
    ContributionRequest::factory()->approved()->create([
        'requester_id' => $user->id,
        'matched_father_id' => $father->id,
        'subject_person_id' => $child->id,
        'affected_person_ids' => [$child->id],
    ]);

    expect($user->can('update', $child))->toBeTrue()
        ->and($user->can('update', $father))->toBeFalse()
        ->and($user->can('update', $grandfather))->toBeFalse();
});

test('matched father and ancestors are locked for ordinary users while approval is pending', function () {
    $marga = Marga::factory()->create();
    $requester = User::factory()->withMarga($marga->id)->create();
    $otherUser = User::factory()->withMarga($marga->id)->create();
    $grandfather = Person::factory()->create(['marga_id' => $marga->id]);
    $father = Person::factory()->create(['marga_id' => $marga->id, 'father_id' => $grandfather->id]);
    $child = Person::factory()->create(['marga_id' => $marga->id, 'created_by' => $requester->id]);
    ContributionRequest::factory()->create([
        'requester_id' => $requester->id,
        'matched_father_id' => $father->id,
        'subject_person_id' => $child->id,
        'affected_person_ids' => [$child->id],
    ]);

    expect($otherUser->can('update', $father))->toBeFalse()
        ->and($otherUser->can('update', $grandfather))->toBeFalse()
        ->and($otherUser->can('update', $child))->toBeTrue();
});

test('a contributor cannot review a contribution for another marga', function () {
    $marga = Marga::factory()->create();
    $otherMarga = Marga::factory()->create();
    $contributor = User::factory()->asContributorMember()->withMarga($otherMarga->id)->create();
    $father = Person::factory()->create(['marga_id' => $marga->id]);
    $child = Person::factory()->create(['marga_id' => $marga->id]);
    $request = ContributionRequest::factory()->create([
        'matched_father_id' => $father->id,
        'subject_person_id' => $child->id,
        'affected_person_ids' => [$child->id],
    ]);

    $this->actingAs($contributor)
        ->post(route('contributions.approve', $request))
        ->assertForbidden();

    expect($request->fresh()->status)->toBe('pending')
        ->and($child->fresh()->father_id)->toBeNull();
});

test('only an admin can create contributor accounts', function () {
    $marga = Marga::factory()->create();
    $admin = User::factory()->asAdmin()->create();
    $ordinaryUser = User::factory()->withMarga($marga->id)->create();
    $payload = [
        'name' => 'Kontributor Baru',
        'email' => 'kontributor@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'marga_id' => $marga->id,
        'role' => 'contributor_main',
    ];

    $this->actingAs($ordinaryUser)
        ->post(route('contributions.contributors.store'), $payload)
        ->assertForbidden();

    $this->actingAs($admin)
        ->post(route('contributions.contributors.store'), $payload)
        ->assertRedirect(route('contributions.index'));

    $this->assertDatabaseHas('users', [
        'email' => 'kontributor@example.com',
        'marga_id' => $marga->id,
        'role' => 'contributor_main',
    ]);
});
