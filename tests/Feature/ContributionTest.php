<?php

use App\Models\ContributionRequest;
use App\Models\FamilyTree;
use App\Models\FamilyTreeNode;
use App\Models\Marga;
use App\Models\MargaAccessRequest;
use App\Models\Person;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

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

test('a user can request marga access and its managers can approve it', function () {
    $marga = Marga::factory()->create(['name' => 'Silaban']);
    $user = User::factory()->withMarga($marga->id)->create();
    $contributor = User::factory()->asContributorMember()->create();
    $contributor->managedMargas()->attach($marga);
    $admin = User::factory()->asAdmin()->create();

    $this->actingAs($user)
        ->post(route('marga-access-requests.store'))
        ->assertRedirect(route('people.index'));

    $accessRequest = MargaAccessRequest::firstOrFail();

    expect($accessRequest->requester_id)->toBe($user->id)
        ->and($accessRequest->marga_id)->toBe($marga->id)
        ->and($accessRequest->status)->toBe(MargaAccessRequest::STATUS_PENDING)
        ->and($contributor->notifications()->count())->toBe(1)
        ->and($admin->notifications()->count())->toBe(1);

    $this->actingAs($contributor)
        ->get(route('contributions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('margaAccessRequests', 1)
            ->where('margaAccessRequests.0.marga', 'Silaban'));

    $this->actingAs($contributor)
        ->post(route('contributions.marga-access.approve', $accessRequest))
        ->assertRedirect(route('contributions.index'));

    expect($accessRequest->fresh()->status)->toBe(MargaAccessRequest::STATUS_APPROVED);

    $this->actingAs($user)
        ->post(route('marga-access-requests.store'))
        ->assertStatus(409);
});

test('approved marga access unlocks another users approved family tree as read-only', function () {
    $marga = Marga::factory()->create();
    $owner = User::factory()->withMarga($marga->id)->create();
    $viewer = User::factory()->withMarga($marga->id)->create();
    $root = Person::factory()->create(['marga_id' => $marga->id, 'name' => 'Akar Silaban']);
    $tree = FamilyTree::create([
        'user_id' => $owner->id,
        'root_person_id' => $root->id,
        'name' => 'Silsilah Silaban',
    ]);
    FamilyTreeNode::create(['family_tree_id' => $tree->id, 'person_id' => $root->id]);
    ContributionRequest::factory()->approved()->create([
        'requester_id' => $owner->id,
        'matched_father_id' => $root->id,
        'subject_person_id' => $root->id,
        'family_tree_id' => $tree->id,
    ]);

    $this->actingAs($viewer)
        ->get(route('people.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('approvedMargaTrees', 0));

    MargaAccessRequest::create([
        'requester_id' => $viewer->id,
        'marga_id' => $marga->id,
        'status' => MargaAccessRequest::STATUS_APPROVED,
    ]);

    $this->actingAs($viewer)
        ->get(route('people.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('approvedMargaTrees', 1)
            ->where('approvedMargaTrees.0.id', $tree->id));

    $this->actingAs($viewer)
        ->get(route('family-trees.show', $tree))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('canEditFamilyTree', false));

});

test('approved marga access exposes a staff tree without contribution approval and preserves person ownership', function () {
    $marga = Marga::factory()->create(['name' => 'Borbor']);
    $admin = User::factory()->asAdmin()->create();
    $viewer = User::factory()->withMarga($marga->id)->create();
    $root = Person::factory()->create([
        'name' => 'Siraja Borbor',
        'marga_id' => $marga->id,
        'created_by' => $admin->id,
    ]);
    $tree = FamilyTree::create([
        'user_id' => $admin->id,
        'root_person_id' => $root->id,
        'name' => 'Tarombo Borbor Admin',
    ]);
    FamilyTreeNode::create([
        'family_tree_id' => $tree->id,
        'person_id' => $root->id,
    ]);
    $tree->people()->attach($root);
    MargaAccessRequest::create([
        'requester_id' => $viewer->id,
        'marga_id' => $marga->id,
        'status' => MargaAccessRequest::STATUS_APPROVED,
    ]);

    $this->actingAs($viewer)
        ->get(route('people.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('approvedMargaTrees.0.id', $tree->id));

    $this->actingAs($viewer)
        ->get(route('family-trees.show', $tree))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('canEditFamilyTree', false));

    expect($viewer->can('append', $tree))->toBeTrue();

    $addedPerson = Person::factory()->create([
        'marga_id' => $marga->id,
        'created_by' => $viewer->id,
        'father_id' => $root->id,
    ]);
    $tree->people()->attach($addedPerson);

    expect($viewer->can('update', $root))->toBeFalse()
        ->and($viewer->can('update', $addedPerson))->toBeTrue();
});

test('a contributor sees trees from every managed marga in the family form', function () {
    $accountMarga = Marga::factory()->create(['name' => 'Marga Akun']);
    $managedMarga = Marga::factory()->create(['name' => 'Marga Kelolaan']);
    $contributor = User::factory()->asMainContributor()->withMarga($accountMarga->id)->create();
    $contributor->managedMargas()->attach($managedMarga);
    $admin = User::factory()->asAdmin()->create();
    $root = Person::factory()->create(['marga_id' => $managedMarga->id]);
    $tree = FamilyTree::create([
        'user_id' => $admin->id,
        'root_person_id' => $root->id,
        'name' => 'Silsilah Marga Kelolaan',
    ]);
    FamilyTreeNode::create([
        'family_tree_id' => $tree->id,
        'person_id' => $root->id,
    ]);

    $this->actingAs($contributor)
        ->get(route('people.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('margas', fn ($margas) => collect($margas)->pluck('id')->contains($managedMarga->id))
            ->where('approvedMargaTrees.0.id', $tree->id));
});
