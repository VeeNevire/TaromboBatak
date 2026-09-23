<?php

use App\Models\FamilyTree;
use App\Models\FamilyTreeNode;
use App\Models\Person;
use App\Models\User;
use App\Services\TaromboTreeService;

test('rows shows the husband as spouse and the shared children on the wife row', function () {
    $viewer = User::factory()->create(['role' => 'user']);
    $husband = Person::factory()->create(['name' => 'Ayah Darma Silaban', 'gender' => 'L']);
    $wife = Person::factory()->create(['name' => 'anakpertamasayapurba', 'gender' => 'P']);
    $husband->wives()->attach($wife->id, ['position' => 1]);

    $son = Person::factory()->create([
        'name' => 'Anak Laki-laki Bersama',
        'gender' => 'L',
        'birth_year' => null,
        'father_id' => $husband->id,
        'mother_id' => $wife->id,
    ]);
    $daughter = Person::factory()->create([
        'name' => 'Anak Perempuan Bersama',
        'gender' => 'P',
        'birth_year' => null,
        'father_id' => $husband->id,
        'mother_id' => $wife->id,
    ]);

    $this->actingAs($viewer);

    $rows = collect(app(TaromboTreeService::class)->rows(
        Person::query()->whereIn('id', [$husband->id, $wife->id]),
    ))->keyBy('id');

    $husbandRow = $rows[(string) $husband->id];
    $wifeRow = $rows[(string) $wife->id];

    expect($wifeRow['spouses'])->toHaveCount(1)
        ->and($wifeRow['spouses'][0]['id'])->toBe((string) $husband->id)
        ->and($wifeRow['sonsNames'])->toBe($husbandRow['sonsNames'])
        ->and($wifeRow['daughtersNames'])->toBe($husbandRow['daughtersNames'])
        ->and($wifeRow['childrenNames'])->toContain('Anak Laki-laki Bersama')
        ->and($wifeRow['childrenNames'])->toContain('Anak Perempuan Bersama');
});

test('rowsForFamilyTree shows the husband as spouse and the shared children on the wife node', function () {
    $viewer = User::factory()->create(['role' => 'user']);
    $husband = Person::factory()->create(['name' => 'Ayah Darma Silaban', 'gender' => 'L']);
    $wife = Person::factory()->create(['name' => 'anakpertamasayapurba', 'gender' => 'P']);
    $husband->wives()->attach($wife->id, ['position' => 1]);

    $son = Person::factory()->create([
        'name' => 'Anak Laki-laki Bersama',
        'gender' => 'L',
        'birth_year' => null,
        'father_id' => $husband->id,
        'mother_id' => $wife->id,
    ]);

    $tree = FamilyTree::create([
        'user_id' => $viewer->id,
        'root_person_id' => $husband->id,
        'name' => 'Keluarga Uji',
    ]);
    $husbandNode = FamilyTreeNode::create(['family_tree_id' => $tree->id, 'person_id' => $husband->id, 'chain' => '1']);
    FamilyTreeNode::create(['family_tree_id' => $tree->id, 'person_id' => $wife->id]);
    FamilyTreeNode::create([
        'family_tree_id' => $tree->id,
        'person_id' => $son->id,
        'father_node_id' => $husbandNode->id,
        'birth_order' => 1,
        'chain' => '1-1',
    ]);

    $this->actingAs($viewer);

    $rows = collect(app(TaromboTreeService::class)->rowsForFamilyTree($tree))->keyBy('id');

    $wifeRow = $rows[(string) $wife->id];

    expect($wifeRow['spouses'])->toHaveCount(1)
        ->and($wifeRow['spouses'][0]['id'])->toBe((string) $husband->id)
        ->and($wifeRow['sonsNames'])->toBe(['Anak Laki-laki Bersama'])
        ->and($wifeRow['childrenNames'])->toBe(['Anak Laki-laki Bersama']);
});
