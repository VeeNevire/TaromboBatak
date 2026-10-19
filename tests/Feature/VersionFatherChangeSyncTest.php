<?php

use App\Jobs\SyncFamilyTreeDescendants;
use App\Models\FamilyTree;
use App\Models\FamilyTreeNode;
use App\Models\Marga;
use App\Models\Person;
use App\Models\User;
use App\Services\FamilyTreeDescendantSyncService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

/**
 * @return array{admin: User, marga: Marga, father: Person, sibling: Person, focus: Person, tree: FamilyTree, otherTree: FamilyTree}
 */
function versionWithUnlinkedFather(): array
{
    $admin = User::factory()->asAdmin()->create();
    $marga = Marga::factory()->create();
    $father = Person::factory()->create(['name' => 'Tuan Sorbadijulu', 'gender' => 'L', 'marga_id' => $marga->id]);
    $sibling = Person::factory()->create(['name' => 'Saudara Tua', 'gender' => 'L', 'marga_id' => $marga->id, 'father_id' => $father->id]);
    $focus = Person::factory()->create(['name' => 'Bolon Tua', 'gender' => 'L', 'marga_id' => $marga->id]);

    $otherTree = FamilyTree::create(['user_id' => User::factory()->create()->id, 'root_person_id' => $father->id, 'name' => 'Pohon Lain']);
    FamilyTreeNode::create(['family_tree_id' => $otherTree->id, 'person_id' => $father->id]);

    $tree = FamilyTree::create(['user_id' => $admin->id, 'root_person_id' => $focus->id, 'name' => 'Keluarga Bolon']);
    FamilyTreeNode::create(['family_tree_id' => $tree->id, 'person_id' => $focus->id, 'chain' => '1']);

    return compact('admin', 'marga', 'father', 'sibling', 'focus', 'tree', 'otherTree');
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function versionFatherPayload(Person $focus, Person $father, Marga $marga, array $overrides = []): array
{
    return [
        'name' => $focus->name,
        'gender' => 'L',
        'marga_id' => $marga->id,
        'birth_order' => 1,
        'sibling_count' => 1,
        'father' => ['id' => $father->id, 'name' => $father->name, 'marga_id' => $marga->id],
        'mothers' => [],
        'children' => [],
        'ownChildren' => [],
        ...$overrides,
    ];
}

test('choosing a new father on a version queues the tree syncs instead of running them in the request', function () {
    Queue::fake([SyncFamilyTreeDescendants::class]);
    ['admin' => $admin, 'marga' => $marga, 'father' => $father, 'sibling' => $sibling, 'focus' => $focus, 'tree' => $tree, 'otherTree' => $otherTree] = versionWithUnlinkedFather();

    $this->actingAs($admin)
        ->put(route('people.update', ['person' => $focus, 'version_tree' => $tree->id]), versionFatherPayload($focus, $father, $marga))
        ->assertRedirect(route('people.edit', ['person' => $focus, 'version_tree' => $tree->id]))
        ->assertSessionHasNoErrors();

    $fatherNode = $tree->nodes()->where('person_id', $father->id)->firstOrFail();

    expect($focus->fresh()->father_id)->toBe($father->id)
        ->and($tree->nodes()->where('person_id', $focus->id)->value('father_node_id'))->toBe($fatherNode->id)
        // Materializing the father's other descendants is left to the queue.
        ->and($tree->nodes()->where('person_id', $sibling->id)->exists())->toBeFalse();

    Queue::assertPushed(SyncFamilyTreeDescendants::class, fn (SyncFamilyTreeDescendants $job) => $job->familyTreeId === $tree->id);
    Queue::assertPushed(SyncFamilyTreeDescendants::class, fn (SyncFamilyTreeDescendants $job) => $job->familyTreeId === $otherTree->id);
});

test('the queued sync adds the new fathers other descendants to the version', function () {
    ['admin' => $admin, 'marga' => $marga, 'father' => $father, 'sibling' => $sibling, 'focus' => $focus, 'tree' => $tree] = versionWithUnlinkedFather();

    $this->actingAs($admin)
        ->put(route('people.update', ['person' => $focus, 'version_tree' => $tree->id]), versionFatherPayload($focus, $father, $marga))
        ->assertSessionHasNoErrors();

    $fatherNode = $tree->nodes()->where('person_id', $father->id)->firstOrFail();

    expect($tree->nodes()->where('person_id', $sibling->id)->value('father_node_id'))->toBe($fatherNode->id);
});

test('the sync job ignores a tree that no longer exists', function () {
    (new SyncFamilyTreeDescendants(999999))->handle(app(FamilyTreeDescendantSyncService::class));
})->throwsNoExceptions();

test('choosing a new father on a version keeps the wives already recorded for him', function () {
    Queue::fake([SyncFamilyTreeDescendants::class]);
    ['admin' => $admin, 'marga' => $marga, 'father' => $father, 'focus' => $focus, 'tree' => $tree] = versionWithUnlinkedFather();
    $wife = Person::factory()->create(['name' => 'Istri Tercatat', 'gender' => 'P']);
    $father->wives()->attach($wife->id, ['position' => 1]);

    $this->actingAs($admin)
        ->put(route('people.update', ['person' => $focus, 'version_tree' => $tree->id]), versionFatherPayload($focus, $father, $marga, [
            'mothers' => [['name' => 'Istri Baru', 'marga_id' => Marga::factory()->create()->id]],
        ]))
        ->assertSessionHasNoErrors();

    expect($father->wives()->pluck('people.name')->all())->toBe(['Istri Tercatat', 'Istri Baru']);
});

test('adding a member to a family tree queues the owners tree syncs instead of running them in the request', function () {
    Queue::fake([SyncFamilyTreeDescendants::class]);
    $marga = Marga::factory()->create();
    $owner = User::factory()->withMarga($marga->id)->create();
    $root = Person::factory()->create(['name' => 'Tuan Sorbadijulu', 'gender' => 'L', 'marga_id' => $marga->id]);

    $tree = FamilyTree::create(['user_id' => $owner->id, 'root_person_id' => $root->id, 'name' => 'Silsilah Dibagikan']);
    $tree->people()->attach($root->id);
    $rootNode = FamilyTreeNode::create(['family_tree_id' => $tree->id, 'person_id' => $root->id, 'chain' => '1']);

    $otherTree = FamilyTree::create(['user_id' => $owner->id, 'root_person_id' => $root->id, 'name' => 'Silsilah Lain']);
    FamilyTreeNode::create(['family_tree_id' => $otherTree->id, 'person_id' => $root->id, 'chain' => '1']);

    $this->actingAs($owner)
        ->post(route('family-trees.people.store', $tree), ['name' => 'Anggota Baru', 'father_node_id' => $rootNode->id])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $newcomer = Person::query()->where('name', 'Anggota Baru')->firstOrFail();

    expect($tree->nodes()->where('person_id', $newcomer->id)->value('father_node_id'))->toBe($rootNode->id);

    Queue::assertPushed(SyncFamilyTreeDescendants::class, fn (SyncFamilyTreeDescendants $job) => $job->familyTreeId === $tree->id);
    Queue::assertPushed(SyncFamilyTreeDescendants::class, fn (SyncFamilyTreeDescendants $job) => $job->familyTreeId === $otherTree->id);
});

test('sync jobs for different trees share one lock so workers never sync in parallel', function () {
    $first = (new SyncFamilyTreeDescendants(1))->middleware()[0];
    $second = (new SyncFamilyTreeDescendants(2))->middleware()[0];

    expect($first->key)->toBe($second->key)
        ->and((new SyncFamilyTreeDescendants(1))->retryUntil())->toBeGreaterThan(now()->addMinutes(30));
});

test('attaching a person survives a concurrent sync inserting the same node first', function () {
    $owner = User::factory()->create();
    $father = Person::factory()->create(['gender' => 'L']);
    $child = Person::factory()->create(['gender' => 'L', 'father_id' => $father->id, 'birth_order' => 1]);
    $tree = FamilyTree::create(['user_id' => $owner->id, 'root_person_id' => $father->id]);
    $fatherNode = FamilyTreeNode::create(['family_tree_id' => $tree->id, 'person_id' => $father->id, 'chain' => '1']);

    // Mimic the queue worker inserting the child right after the missing-node check.
    $raced = false;
    DB::listen(function ($query) use (&$raced, $tree, $child, $fatherNode): void {
        if (! $raced && str_contains($query->sql, 'family_tree_nodes') && str_contains($query->sql, 'not in')) {
            $raced = true;
            DB::table('family_tree_nodes')->insert([
                'family_tree_id' => $tree->id, 'person_id' => $child->id, 'father_node_id' => $fatherNode->id,
                'is_removed' => false, 'pending_father' => false, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    });

    app(FamilyTreeDescendantSyncService::class)->attachToTreesHoldingFather($child);

    expect($raced)->toBeTrue()
        ->and($tree->nodes()->where('person_id', $child->id)->count())->toBe(1);
});
