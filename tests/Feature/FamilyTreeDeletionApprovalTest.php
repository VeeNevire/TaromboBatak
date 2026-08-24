<?php

use App\Models\FamilyTree;
use App\Models\FamilyTreeDeletionRequest;
use App\Models\FamilyTreeNode;
use App\Models\Marga;
use App\Models\Person;
use App\Models\User;
use App\Notifications\FamilyTreeDeletionSubmitted;
use Inertia\Testing\AssertableInertia as Assert;

function deletionTree(User $user, Person $root, array $people = []): FamilyTree
{
    $tree = FamilyTree::create([
        'user_id' => $user->id,
        'root_person_id' => $root->id,
        'name' => 'Silsilah '.$root->name,
    ]);
    $tree->people()->attach(collect([$root, ...$people])->pluck('id'));

    return $tree;
}

test('an owner directly deletes an unconnected tree without deleting people', function () {
    $marga = Marga::factory()->create();
    $user = User::factory()->withMarga($marga->id)->create();
    $root = Person::factory()->create(['marga_id' => $marga->id]);
    $child = Person::factory()->create(['marga_id' => $marga->id]);
    $tree = deletionTree($user, $root, [$child]);

    $this->actingAs($user)
        ->delete(route('family-trees.destroy', $tree))
        ->assertRedirect();

    expect(FamilyTree::find($tree->id))->toBeNull()
        ->and(Person::whereKey([$root->id, $child->id])->count())->toBe(2)
        ->and(FamilyTreeDeletionRequest::count())->toBe(0);
});

test('deleting a tree connected to another account creates one pending request', function () {
    $marga = Marga::factory()->create();
    $owner = User::factory()->withMarga($marga->id)->create();
    $otherOwner = User::factory()->withMarga($marga->id)->create();
    $main = User::factory()->asMainContributor()->withMarga($marga->id)->create();
    $member = User::factory()->asContributorMember()->withMarga($marga->id)->create();
    $outsider = User::factory()->asMainContributor()->withMarga(Marga::factory()->create()->id)->create();
    $root = Person::factory()->create(['marga_id' => $marga->id]);
    $shared = Person::factory()->create(['marga_id' => $marga->id]);
    $tree = deletionTree($owner, $root, [$shared]);
    $otherTree = deletionTree($otherOwner, $shared);

    $this->actingAs($owner)->delete(route('family-trees.destroy', $tree));
    $this->actingAs($owner)->delete(route('family-trees.destroy', $tree));

    $deletion = FamilyTreeDeletionRequest::firstOrFail();
    expect($deletion->status)->toBe(FamilyTreeDeletionRequest::STATUS_PENDING)
        ->and($deletion->family_tree_id)->toBe($tree->id)
        ->and(FamilyTree::find($tree->id))->not->toBeNull()
        ->and(FamilyTree::find($otherTree->id))->not->toBeNull()
        ->and(FamilyTreeDeletionRequest::count())->toBe(1)
        ->and($main->notifications()->where('type', FamilyTreeDeletionSubmitted::class)->count())->toBe(1)
        ->and($member->notifications()->where('type', FamilyTreeDeletionSubmitted::class)->count())->toBe(1)
        ->and($outsider->notifications()->where('type', FamilyTreeDeletionSubmitted::class)->count())->toBe(0);
});

test('contributors and admins can approve tree deletion requests in their scope', function (string $role) {
    $marga = Marga::factory()->create();
    $owner = User::factory()->withMarga($marga->id)->create();
    $otherOwner = User::factory()->withMarga($marga->id)->create();
    $reviewer = User::factory()->state(['role' => $role, 'marga_id' => $marga->id])->create();
    $root = Person::factory()->create(['marga_id' => $marga->id]);
    $tree = deletionTree($owner, $root);
    $otherTree = deletionTree($otherOwner, $root);
    $this->actingAs($owner)->delete(route('family-trees.destroy', $tree));
    $deletion = FamilyTreeDeletionRequest::firstOrFail();

    $this->actingAs($reviewer)
        ->post(route('family-tree-deletions.approve', $deletion))
        ->assertRedirect(route('contributions.index', ['tab' => 'deletions']));

    expect(FamilyTree::find($tree->id))->toBeNull()
        ->and(FamilyTree::find($otherTree->id))->not->toBeNull()
        ->and(Person::find($root->id))->not->toBeNull()
        ->and($deletion->fresh()->status)->toBe(FamilyTreeDeletionRequest::STATUS_APPROVED)
        ->and($deletion->fresh()->reviewed_by)->toBe($reviewer->id)
        ->and($deletion->fresh()->tree_name)->toBe($tree->name);
})->with(['contributor_main', 'contributor_member', 'admin']);

test('an outsider cannot review while admin sees all tree deletion requests', function () {
    $marga = Marga::factory()->create();
    $owner = User::factory()->withMarga($marga->id)->create();
    $otherOwner = User::factory()->withMarga($marga->id)->create();
    $outsider = User::factory()->asMainContributor()->withMarga(Marga::factory()->create()->id)->create();
    $admin = User::factory()->asAdmin()->create();
    $root = Person::factory()->create(['marga_id' => $marga->id]);
    $tree = deletionTree($owner, $root);
    deletionTree($otherOwner, $root);
    $this->actingAs($owner)->delete(route('family-trees.destroy', $tree));
    $deletion = FamilyTreeDeletionRequest::firstOrFail();

    $this->actingAs($outsider)
        ->post(route('family-tree-deletions.approve', $deletion))
        ->assertForbidden();

    $this->actingAs($admin)
        ->get(route('contributions.index', ['tab' => 'deletions']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('activeTab', 'deletions')
            ->has('deletionRequests.data', 1));
});

test('rejection preserves the tree and stores the reason', function () {
    $marga = Marga::factory()->create();
    $owner = User::factory()->withMarga($marga->id)->create();
    $otherOwner = User::factory()->withMarga($marga->id)->create();
    $reviewer = User::factory()->asContributorMember()->withMarga($marga->id)->create();
    $root = Person::factory()->create(['marga_id' => $marga->id]);
    $tree = deletionTree($owner, $root);
    deletionTree($otherOwner, $root);
    $this->actingAs($owner)->delete(route('family-trees.destroy', $tree));
    $deletion = FamilyTreeDeletionRequest::firstOrFail();

    $this->actingAs($reviewer)->post(route('family-tree-deletions.reject', $deletion), [
        'reason' => 'Perlu dikonfirmasi dengan pemilik silsilah lain.',
    ])->assertRedirect(route('contributions.index', ['tab' => 'deletions']));

    expect(FamilyTree::find($tree->id))->not->toBeNull()
        ->and($deletion->fresh()->status)->toBe(FamilyTreeDeletionRequest::STATUS_REJECTED)
        ->and($deletion->fresh()->rejection_reason)->toBe('Perlu dikonfirmasi dengan pemilik silsilah lain.');
});

test('node-only cross-account usage also requires approval', function () {
    $marga = Marga::factory()->create();
    $owner = User::factory()->withMarga($marga->id)->create();
    $otherOwner = User::factory()->withMarga($marga->id)->create();
    $root = Person::factory()->create(['marga_id' => $marga->id]);
    $shared = Person::factory()->create(['marga_id' => $marga->id]);
    $tree = deletionTree($owner, $root, [$shared]);
    $otherTree = FamilyTree::create(['user_id' => $otherOwner->id]);
    FamilyTreeNode::create(['family_tree_id' => $otherTree->id, 'person_id' => $shared->id]);

    $this->actingAs($owner)->delete(route('family-trees.destroy', $tree));

    expect(FamilyTree::find($tree->id))->not->toBeNull()
        ->and(FamilyTreeDeletionRequest::where('family_tree_id', $tree->id)->count())->toBe(1);
});

test('another user cannot delete a tree they do not own', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $root = Person::factory()->create();
    $tree = deletionTree($owner, $root);

    $this->actingAs($otherUser)
        ->delete(route('family-trees.destroy', $tree))
        ->assertForbidden();

    expect(FamilyTree::find($tree->id))->not->toBeNull();
});

test('reviewing a request whose tree is missing closes it as rejected', function () {
    $marga = Marga::factory()->create();
    $owner = User::factory()->withMarga($marga->id)->create();
    $admin = User::factory()->asAdmin()->create();
    $root = Person::factory()->create(['marga_id' => $marga->id]);
    $tree = deletionTree($owner, $root);
    $deletion = FamilyTreeDeletionRequest::create([
        'family_tree_id' => $tree->id,
        'requester_id' => $owner->id,
        'marga_id' => $marga->id,
        'tree_name' => $tree->name,
        'root_name' => $root->name,
    ]);
    $tree->delete();

    $this->actingAs($admin)
        ->post(route('family-tree-deletions.approve', $deletion))
        ->assertRedirect(route('contributions.index', ['tab' => 'deletions']));

    expect($deletion->fresh()->status)->toBe(FamilyTreeDeletionRequest::STATUS_REJECTED)
        ->and($deletion->fresh()->rejection_reason)->toBe('Silsilah sudah tidak tersedia.');
});
