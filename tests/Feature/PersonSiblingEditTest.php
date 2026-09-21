<?php

use App\Models\FamilyTree;
use App\Models\Marga;
use App\Models\Person;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('a marga owner can see the father and save a new sibling without errors', function () {
    $marga = Marga::factory()->create(['name' => 'Silaban']);
    $user = User::factory()->withMarga($marga->id)->create();

    $father = Person::factory()->create([
        'name' => 'St. Lukas',
        'gender' => 'L',
        'marga_id' => $marga->id,
        'created_by' => $user->id,
    ]);
    $focus = Person::factory()->create([
        'name' => 'Osmar Silaban',
        'gender' => 'L',
        'marga_id' => $marga->id,
        'father_id' => $father->id,
        'created_by' => $user->id,
        'birth_order' => 3,
        'sibling_count' => 3,
    ]);
    $sibling = Person::factory()->create([
        'name' => 'St. Wilson',
        'gender' => 'L',
        'marga_id' => $marga->id,
        'father_id' => $father->id,
        'created_by' => $user->id,
        'birth_order' => 2,
    ]);

    $tree = FamilyTree::create([
        'user_id' => $user->id,
        'root_person_id' => $father->id,
        'name' => 'Keluarga Silaban',
    ]);
    $fatherNode = $tree->nodes()->create(['person_id' => $father->id, 'chain' => '1']);
    $tree->nodes()->create(['person_id' => $sibling->id, 'father_node_id' => $fatherNode->id, 'birth_order' => 2]);
    $tree->nodes()->create(['person_id' => $focus->id, 'father_node_id' => $fatherNode->id, 'birth_order' => 3]);
    $tree->people()->sync([$father->id, $sibling->id, $focus->id]);

    $this->actingAs($user)
        ->get(route('people.edit', $focus))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('person.father.id', $father->id)
            ->where('person.father.name', 'St. Lukas'));

    $this->actingAs($user)
        ->put(route('people.update', $focus), [
            'name' => $focus->name,
            'gender' => 'L',
            'marga_id' => $marga->id,
            'birth_order' => 3,
            'sibling_count' => 4,
            'father' => [
                'id' => $father->id,
                'name' => $father->name,
                'marga_id' => $marga->id,
            ],
            'children' => [
                ['id' => $sibling->id, 'name' => $sibling->name, 'gender' => 'L', 'marga_id' => $marga->id],
                ['id' => $focus->id, 'name' => $focus->name, 'gender' => 'L', 'marga_id' => $marga->id],
                ['name' => 'Saudara Baru', 'gender' => 'L', 'marga_id' => $marga->id],
            ],
            'ownChildren' => [],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $created = Person::query()->where('name', 'Saudara Baru')->firstOrFail();

    expect($created->father_id)->toBe($father->id)
        ->and($created->marga_id)->toBe($marga->id);
});
