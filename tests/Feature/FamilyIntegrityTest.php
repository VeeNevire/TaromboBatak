<?php

use App\Models\Marga;
use App\Models\Person;
use App\Models\User;
use App\Services\ChainNumberingService;

test('a regular user cannot update a nested person from another family', function () {
    $marga = Marga::factory()->create();
    $user = User::factory()->withMarga($marga->id)->create();
    $father = Person::factory()->create(['marga_id' => $marga->id]);
    $focus = Person::factory()->create([
        'name' => 'Keluarga Saya',
        'marga_id' => $marga->id,
        'father_id' => $father->id,
        'created_by' => $user->id,
    ]);
    $foreignPerson = Person::factory()->create([
        'name' => 'Keluarga Lain',
        'marga_id' => $marga->id,
        'created_by' => User::factory()->create()->id,
    ]);

    $this->actingAs($user)
        ->put(route('people.update', $focus), [
            'name' => $focus->name,
            'marga_id' => $marga->id,
            'birth_order' => 1,
            'sibling_count' => 2,
            'father_id' => $father->id,
            'children' => [
                ['id' => $focus->id, 'name' => $focus->name],
                ['id' => $foreignPerson->id, 'name' => 'Diubah Paksa'],
            ],
        ])
        ->assertSessionHasErrors('children.1.id');

    expect($foreignPerson->fresh()->name)->toBe('Keluarga Lain')
        ->and($foreignPerson->fresh()->father_id)->toBeNull();
});

test('a descendant cannot become the father of its ancestor', function () {
    $admin = User::factory()->asAdmin()->create();
    $marga = Marga::factory()->create();
    $root = Person::factory()->create(['name' => 'Root', 'marga_id' => $marga->id]);
    $child = Person::factory()->create([
        'name' => 'Child',
        'marga_id' => $marga->id,
        'father_id' => $root->id,
        'birth_order' => 1,
    ]);

    $this->actingAs($admin)
        ->put(route('people.update', $root), [
            'name' => $root->name,
            'marga_id' => $marga->id,
            'father_id' => $child->id,
            'birth_order' => 1,
            'sibling_count' => 1,
            'children' => [
                ['id' => $root->id, 'name' => $root->name],
            ],
        ])
        ->assertSessionHasErrors('father_id');

    expect($root->fresh()->father_id)->toBeNull();
});

test('reparenting recomputes the moved subtree and clears the old root chain', function () {
    $admin = User::factory()->asAdmin()->create();
    $marga = Marga::factory()->create();
    $oldFather = Person::factory()->create(['name' => 'Ayah Lama', 'marga_id' => $marga->id]);
    $focus = Person::factory()->create([
        'name' => 'Fokus',
        'marga_id' => $marga->id,
        'father_id' => $oldFather->id,
        'birth_order' => 1,
    ]);
    $grandchild = Person::factory()->create([
        'name' => 'Cucu',
        'marga_id' => $marga->id,
        'father_id' => $focus->id,
        'birth_order' => 1,
    ]);
    $newFather = Person::factory()->create(['name' => 'Ayah Baru', 'marga_id' => $marga->id]);
    Person::factory()->create([
        'name' => 'Anak Baru Pertama',
        'marga_id' => $marga->id,
        'father_id' => $newFather->id,
        'birth_order' => 1,
    ]);

    app(ChainNumberingService::class)->recomputeAll();

    $this->actingAs($admin)
        ->put(route('people.update', $focus), [
            'name' => $focus->name,
            'marga_id' => $marga->id,
            'father_id' => $newFather->id,
            'birth_order' => 2,
            'sibling_count' => 2,
            'children' => [
                ['id' => $focus->id, 'name' => $focus->name],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($oldFather->fresh()->chain)->toBeNull()
        ->and($focus->fresh()->chain)->toBe($newFather->fresh()->chain.'-1')
        ->and($grandchild->fresh()->chain)->toBe($focus->fresh()->chain.'-1');
});

test('a person referenced as a parent cannot be deleted', function () {
    $admin = User::factory()->asAdmin()->create();
    $parent = Person::factory()->create();
    Person::factory()->create(['father_id' => $parent->id]);

    $this->actingAs($admin)
        ->delete(route('people.destroy', $parent))
        ->assertSessionHasErrors('person');

    $this->assertDatabaseHas('people', ['id' => $parent->id]);
});
