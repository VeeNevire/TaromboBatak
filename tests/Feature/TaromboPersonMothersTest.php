<?php

use App\Models\FamilyTree;
use App\Models\FamilyTreeNode;
use App\Models\Person;
use App\Models\User;
use App\Services\TaromboTreeService;

test('rows lists the father wives as mothers, not the person own spouse', function () {
    $viewer = User::factory()->create(['role' => 'user']);
    $father = Person::factory()->create(['name' => 'Kitab', 'gender' => 'L']);
    $mother = Person::factory()->create(['name' => 'S Br', 'gender' => 'P']);
    $father->wives()->attach($mother->id, ['position' => 1]);
    $son = Person::factory()->create([
        'name' => 'Anggiat',
        'gender' => 'L',
        'father_id' => $father->id,
    ]);
    $wife = Person::factory()->create(['name' => 'D Br', 'gender' => 'P']);
    $son->wives()->attach($wife->id, ['position' => 1]);

    $this->actingAs($viewer);

    $row = collect(app(TaromboTreeService::class)->rows(
        Person::query()->whereKey($son->id),
    ))->first();

    expect($row['mothers'])->toHaveCount(1)
        ->and($row['mothers'][0]['id'])->toBe((string) $mother->id)
        ->and($row['spouses'][0]['id'])->toBe((string) $wife->id);
});

test('rows includes the recorded mother even when she is not a wife of the father', function () {
    $viewer = User::factory()->create(['role' => 'user']);
    $father = Person::factory()->create(['gender' => 'L']);
    $mother = Person::factory()->create(['name' => 'Ibu Tercatat', 'gender' => 'P']);
    $son = Person::factory()->create([
        'gender' => 'L',
        'father_id' => $father->id,
        'mother_id' => $mother->id,
    ]);

    $this->actingAs($viewer);

    $row = collect(app(TaromboTreeService::class)->rows(
        Person::query()->whereKey($son->id),
    ))->first();

    expect(collect($row['mothers'])->pluck('id')->all())->toBe([(string) $mother->id]);
});

test('rowsForFamilyTree lists the father wives as mothers on the child node', function () {
    $viewer = User::factory()->create(['role' => 'user']);
    $father = Person::factory()->create(['gender' => 'L']);
    $mother = Person::factory()->create(['name' => 'S Br', 'gender' => 'P']);
    $father->wives()->attach($mother->id, ['position' => 1]);
    $son = Person::factory()->create(['gender' => 'L', 'father_id' => $father->id]);

    $tree = FamilyTree::create([
        'user_id' => $viewer->id,
        'root_person_id' => $father->id,
        'name' => 'Keluarga Uji',
    ]);
    $fatherNode = FamilyTreeNode::create(['family_tree_id' => $tree->id, 'person_id' => $father->id, 'chain' => '1']);
    FamilyTreeNode::create([
        'family_tree_id' => $tree->id,
        'person_id' => $son->id,
        'father_node_id' => $fatherNode->id,
        'birth_order' => 1,
        'chain' => '1-1',
    ]);

    $this->actingAs($viewer);

    $rows = collect(app(TaromboTreeService::class)->rowsForFamilyTree($tree))->keyBy('id');

    expect(collect($rows[(string) $son->id]['mothers'])->pluck('id')->all())->toBe([(string) $mother->id])
        ->and($rows[(string) $father->id]['mothers'])->toBe([]);
});

test('father spouses include a child recorded mother missing from his wives', function () {
    $viewer = User::factory()->create(['role' => 'user']);
    $father = Person::factory()->create(['gender' => 'L']);
    $firstWife = Person::factory()->create(['gender' => 'P']);
    $secondWife = Person::factory()->create(['gender' => 'P']);
    $father->wives()->attach($firstWife->id, ['position' => 1]);
    $daughter = Person::factory()->create([
        'gender' => 'P',
        'father_id' => $father->id,
        'mother_id' => $secondWife->id,
    ]);
    Person::factory()->create([
        'gender' => 'L',
        'father_id' => $father->id,
        'mother_id' => $firstWife->id,
    ]);

    $this->actingAs($viewer);

    $rows = collect(app(TaromboTreeService::class)->rows(
        Person::query()->whereKey([$father->id, $daughter->id]),
    ))->keyBy('id');

    $expected = [(string) $firstWife->id, (string) $secondWife->id];

    expect(collect($rows[(string) $father->id]['spouses'])->pluck('id')->all())->toBe($expected)
        ->and(collect($rows[(string) $daughter->id]['mothers'])->pluck('id')->all())->toBe($expected);
});
