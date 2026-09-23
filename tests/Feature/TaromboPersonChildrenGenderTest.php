<?php

use App\Models\FamilyTree;
use App\Models\FamilyTreeNode;
use App\Models\Marga;
use App\Models\Person;
use App\Models\User;
use App\Services\TaromboTreeService;

test('rows splits children into sons and daughters, treating an unset gender as a son', function () {
    $viewer = User::factory()->create(['role' => 'user']);
    $father = Person::factory()->create(['name' => 'Ayah']);
    $son = Person::factory()->create(['name' => 'Anak L', 'gender' => 'L', 'birth_year' => null, 'father_id' => $father->id]);
    $daughter = Person::factory()->create(['name' => 'Anak P', 'gender' => 'P', 'birth_year' => null, 'father_id' => $father->id]);
    $unset = Person::factory()->create(['name' => 'Anak Tanpa Gender', 'gender' => null, 'birth_year' => null, 'father_id' => $father->id]);

    $this->actingAs($viewer);

    $rows = collect(app(TaromboTreeService::class)->rows(
        Person::query()->whereKey($father->id),
    ))->keyBy('id');

    expect($rows[(string) $father->id]['sonsNames'])->toContain('Anak L')
        ->and($rows[(string) $father->id]['sonsNames'])->toContain('Anak Tanpa Gender')
        ->and($rows[(string) $father->id]['sonsNames'])->not->toContain('Anak P')
        ->and($rows[(string) $father->id]['daughtersNames'])->toBe(['Anak P']);
});

test('rowsForFamilyTree splits children into sons and daughters, treating an unset gender as a son', function () {
    $viewer = User::factory()->create(['role' => 'user']);
    $father = Person::factory()->create(['name' => 'Ayah Node']);
    $son = Person::factory()->create(['name' => 'Anak L', 'gender' => 'L', 'birth_year' => null]);
    $daughter = Person::factory()->create(['name' => 'Anak P', 'gender' => 'P', 'birth_year' => null]);
    $unset = Person::factory()->create(['name' => 'Anak Tanpa Gender', 'gender' => null, 'birth_year' => null]);

    $tree = FamilyTree::create([
        'user_id' => $viewer->id,
        'root_person_id' => $father->id,
        'name' => 'Keluarga Uji',
    ]);
    $fatherNode = FamilyTreeNode::create(['family_tree_id' => $tree->id, 'person_id' => $father->id, 'chain' => '1']);
    foreach ([$son, $daughter, $unset] as $index => $child) {
        FamilyTreeNode::create([
            'family_tree_id' => $tree->id,
            'person_id' => $child->id,
            'father_node_id' => $fatherNode->id,
            'birth_order' => $index + 1,
            'chain' => '1-'.($index + 1),
        ]);
    }

    $this->actingAs($viewer);

    $rows = collect(app(TaromboTreeService::class)->rowsForFamilyTree($tree))->keyBy('id');

    expect($rows[(string) $father->id]['sonsNames'])->toContain('Anak L')
        ->and($rows[(string) $father->id]['sonsNames'])->toContain('Anak Tanpa Gender')
        ->and($rows[(string) $father->id]['sonsNames'])->not->toContain('Anak P')
        ->and($rows[(string) $father->id]['daughtersNames'])->toBe(['Anak P']);
});
