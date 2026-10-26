<?php

use App\Models\FamilyTree;
use App\Models\FamilyTreeNode;
use App\Models\Person;
use App\Models\User;
use App\Services\TaromboTreeService;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array{0: FamilyTree, 1: Person, 2: Person}
 */
function summaryTree(User $owner): array
{
    $root = Person::factory()->create(['name' => 'Ompu Akar', 'gender' => 'L', 'bio' => 'Riwayat akar']);
    $child = Person::factory()->create(['name' => 'Anak Akar', 'gender' => 'L', 'father_id' => $root->id]);
    $tree = FamilyTree::create([
        'user_id' => $owner->id,
        'root_person_id' => $root->id,
        'name' => 'Silsilah Akar',
        'is_primary' => true,
    ]);
    $rootNode = FamilyTreeNode::create([
        'family_tree_id' => $tree->id,
        'person_id' => $root->id,
        'chain' => '1',
    ]);
    FamilyTreeNode::create([
        'family_tree_id' => $tree->id,
        'person_id' => $child->id,
        'father_node_id' => $rootNode->id,
        'birth_order' => 1,
        'chain' => '1-1',
    ]);

    return [$tree, $root, $child];
}

test('the account tree sends light summary rows', function () {
    $owner = User::factory()->asAdmin()->create();
    [, $root, $child] = summaryTree($owner);
    User::factory()->create(['current_person_id' => $child->id]);

    $this->actingAs($owner)
        ->get(route('tarombo.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('people', 2)
            ->where('people', fn ($people) => collect($people)->every(
                fn (array $row) => $row['summary'] === true
                    && ! array_key_exists('bio', $row)
                    && ! array_key_exists('mothers', $row)
                    && ! array_key_exists('claimedAccounts', $row),
            ))
            ->where('people', function ($people) use ($root, $child) {
                $rows = collect($people)->keyBy('id');

                return $rows[(string) $root->id]['hasChildren'] === true
                    && $rows[(string) $child->id]['hasChildren'] === false
                    && $rows[(string) $child->id]['parentId'] === (string) $root->id
                    && $rows[(string) $child->id]['isClaimed'] === true
                    && $rows[(string) $root->id]['isClaimed'] === false;
            }));
});

test('summary rows match the full rows on every field the tree draws', function () {
    $owner = User::factory()->asAdmin()->create();
    [$tree] = summaryTree($owner);
    $this->actingAs($owner);
    $service = app(TaromboTreeService::class);

    $full = collect($service->rowsForFamilyTree($tree))->keyBy('id');
    $summary = collect($service->summaryRowsForFamilyTree($tree))->keyBy('id');

    expect($summary->keys()->all())->toBe($full->keys()->all());

    foreach ($summary as $id => $row) {
        foreach (['treeNodeId', 'name', 'marga', 'parentId', 'birthOrder', 'chain', 'pending', 'gender', 'canEdit'] as $field) {
            expect($row[$field])->toBe($full[$id][$field]);
        }

        expect($row['hasChildren'])->toBe($full[$id]['childrenNames'] !== []);
    }
});

test('the profile detail endpoint returns the full row of one person', function () {
    $owner = User::factory()->asAdmin()->create();
    [$tree, $root, $child] = summaryTree($owner);

    $this->actingAs($owner)
        ->getJson(route('tarombo.family-trees.people.show', [$tree, $root]))
        ->assertOk()
        ->assertJsonPath('person.id', (string) $root->id)
        ->assertJsonPath('person.bio', 'Riwayat akar')
        ->assertJsonCount(1, 'person.childrenNames');

    $this->actingAs($owner)
        ->getJson(route('tarombo.family-trees.people.show', [$tree, $child]))
        ->assertOk()
        ->assertJsonPath('person.parentId', (string) $root->id)
        ->assertJsonPath('person.fatherName', $root->name);
});

test('the profile detail endpoint refuses other accounts and people outside the tree', function () {
    $owner = User::factory()->create(['role' => 'user']);
    [$tree, $root] = summaryTree($owner);
    $outsider = Person::factory()->create();

    $this->actingAs(User::factory()->create(['role' => 'user']))
        ->getJson(route('tarombo.family-trees.people.show', [$tree, $root]))
        ->assertForbidden();

    $this->actingAs($owner)
        ->getJson(route('tarombo.family-trees.people.show', [$tree, $outsider]))
        ->assertNotFound();
});

test('cached summary rows refresh as soon as the tree data changes', function () {
    $owner = User::factory()->asAdmin()->create();
    [$tree, $root, $child] = summaryTree($owner);
    $this->actingAs($owner);
    $service = app(TaromboTreeService::class);
    $name = fn (array $rows, Person $person) => collect($rows)->firstWhere('id', (string) $person->id)['name'] ?? null;

    expect($name($service->cachedSummaryRowsForFamilyTree($tree), $root))->toBe('Ompu Akar');

    // A bulk query skips model events; the cache must still notice it.
    $this->travel(1)->seconds();
    Person::query()->whereKey($root->id)->update(['name' => 'Ompu Baru']);

    expect($name($service->cachedSummaryRowsForFamilyTree($tree), $root))->toBe('Ompu Baru');

    // Removing a node from the tree drops the person on the next read.
    FamilyTreeNode::query()->where('person_id', $child->id)->delete();

    expect($name($service->cachedSummaryRowsForFamilyTree($tree->fresh()), $child))->toBeNull();
});
