<?php

use App\Models\Marga;
use App\Models\Person;
use App\Models\User;
use App\Services\ChainNumberingService;

test('auto chain assigns dash-based chains to a new family', function () {
    $admin = User::factory()->asAdmin()->create();
    $marga = Marga::factory()->create(['name' => 'Sitorus']);

    $this->actingAs($admin)->post(route('people.store'), [
        'name' => 'Ompu Sitorus',
        'marga_id' => $marga->id,
        'birth_order' => 1,
        'sibling_count' => 3,
        'father' => ['name' => 'Si Raja Batak'],
        'children' => [
            ['name' => 'A', 'gender' => 'L'],
            ['name' => 'B', 'gender' => 'L'],
            ['name' => 'C', 'gender' => 'L'],
        ],
    ])->assertRedirect(route('people.index'));

    $father = Person::where('name', 'Si Raja Batak')->first();
    expect($father->chain)->toBe('1');

    $children = Person::where('father_id', $father->id)->orderBy('birth_order')->get();
    expect($children->pluck('chain')->all())->toBe(['1-1', '1-2', '1-3']);
});

test('a second root lineage gets the next root chain', function () {
    $admin = User::factory()->asAdmin()->create();
    $marga = Marga::factory()->create();

    $store = fn (string $fatherName) => $this->actingAs($admin)->post(route('people.store'), [
        'name' => 'Anak',
        'marga_id' => $marga->id,
        'birth_order' => 1,
        'sibling_count' => 1,
        'father' => ['name' => $fatherName],
        'children' => [['name' => 'Anak', 'gender' => 'L']],
    ])->assertRedirect(route('people.index'));

    $store('Root Satu');
    $store('Root Dua');

    expect(Person::where('name', 'Root Satu')->first()->chain)->toBe('1');
    expect(Person::where('name', 'Root Dua')->first()->chain)->toBe('2');
});

test('grandchildren inherit the chain of their father', function () {
    $admin = User::factory()->asAdmin()->create();
    $marga = Marga::factory()->create();

    $this->actingAs($admin)->post(route('people.store'), [
        'name' => 'Anak Satu',
        'marga_id' => $marga->id,
        'birth_order' => 1,
        'sibling_count' => 1,
        'father' => ['name' => 'Si Raja Batak'],
        'children' => [['name' => 'Anak Satu', 'gender' => 'L']],
    ])->assertRedirect(route('people.index'));

    $this->actingAs($admin)->post(route('people.store'), [
        'name' => 'Cucu Satu',
        'marga_id' => $marga->id,
        'birth_order' => 1,
        'sibling_count' => 1,
        'father' => ['name' => 'Anak Satu'],
        'children' => [['name' => 'Cucu Satu', 'gender' => 'L']],
    ])->assertRedirect(route('people.index'));

    expect(Person::where('name', 'Si Raja Batak')->first()->chain)->toBe('1')
        ->and(Person::where('name', 'Anak Satu')->first()->chain)->toBe('1-1')
        ->and(Person::where('name', 'Cucu Satu')->first()->chain)->toBe('1-1-1');
});

test('each child can carry its own marga', function () {
    $admin = User::factory()->asAdmin()->create();
    $margaA = Marga::factory()->create(['name' => 'MargaA']);
    $margaB = Marga::factory()->create(['name' => 'MargaB']);

    $this->actingAs($admin)->post(route('people.store'), [
        'name' => 'Ompu',
        'marga_id' => $margaA->id,
        'birth_order' => 1,
        'sibling_count' => 2,
        'father' => ['name' => 'Si Raja Batak'],
        'children' => [
            ['name' => 'Punya Marga Baru', 'gender' => 'L', 'marga_id' => $margaB->id],
            ['name' => 'Ikut Ayah', 'gender' => 'L'],
        ],
    ])->assertRedirect(route('people.index'));

    $father = Person::where('name', 'Si Raja Batak')->first();
    $baru = Person::where('name', 'Punya Marga Baru')->first();
    $ikut = Person::where('name', 'Ikut Ayah')->first();

    expect($baru->marga_id)->toBe($margaB->id)
        ->and($ikut->marga_id)->toBe($margaA->id)
        ->and($father->marga_id)->toBe($margaA->id);
});

test('the mother does not receive an auto chain or marga', function () {
    $admin = User::factory()->asAdmin()->create();
    $marga = Marga::factory()->create(['name' => 'Sitorus']);

    $this->actingAs($admin)->post(route('people.store'), [
        'name' => 'Ompu',
        'marga_id' => $marga->id,
        'birth_order' => 1,
        'sibling_count' => 1,
        'father' => ['name' => 'Si Raja Batak'],
        'mother' => ['name' => 'Ibu Sitorus'],
        'children' => [['name' => 'Ompu', 'gender' => 'L']],
    ])->assertRedirect(route('people.index'));

    $mother = Person::where('name', 'Ibu Sitorus')->first();
    expect($mother->chain)->toBeNull()
        ->and($mother->marga_id)->toBeNull();
});

test('a root without children never receives a chain', function () {
    $root = Person::factory()->create(['name' => 'Janda', 'father_id' => null]);

    app(ChainNumberingService::class)->recomputeAll();

    expect($root->fresh()->chain)->toBeNull();
});

test('recompute reflects birth order changes down the lineage', function () {
    $root = Person::factory()->create(['name' => 'R', 'father_id' => null]);
    $a = Person::factory()->create(['name' => 'A', 'father_id' => $root->id, 'birth_order' => 1]);
    $b = Person::factory()->create(['name' => 'B', 'father_id' => $root->id, 'birth_order' => 2]);
    $c = Person::factory()->create(['name' => 'C', 'father_id' => $a->id, 'birth_order' => 1]);

    $service = app(ChainNumberingService::class);
    $service->recomputeFromAncestor($root);

    expect($root->fresh()->chain)->toBe('1')
        ->and($a->fresh()->chain)->toBe('1-1')
        ->and($b->fresh()->chain)->toBe('1-2')
        ->and($c->fresh()->chain)->toBe('1-1-1');

    $a->update(['birth_order' => 2]);
    $b->update(['birth_order' => 1]);

    $service->recomputeFromAncestor($root);

    expect($a->fresh()->chain)->toBe('1-2')
        ->and($b->fresh()->chain)->toBe('1-1')
        ->and($c->fresh()->chain)->toBe('1-2-1');
});

test('children of a person without a chain stay without a chain', function () {
    $service = app(ChainNumberingService::class);

    $root = Person::factory()->create(['name' => 'Si Raja Batak', 'father_id' => null]);
    $line = Person::factory()->create(['name' => 'Anak', 'father_id' => $root->id, 'birth_order' => 1]);
    $side = Person::factory()->create(['name' => 'Cucu', 'father_id' => $line->id, 'birth_order' => 1]);

    $service->recomputeFromAncestor($root);

    expect($root->fresh()->chain)->toBe('1')
        ->and($line->fresh()->chain)->toBe('1-1')
        ->and($side->fresh()->chain)->toBe('1-1-1');
});

test('inserting an older sibling shifts the focus person and its descendants down the chain', function () {
    $admin = User::factory()->asAdmin()->create();
    $marga = Marga::factory()->create();

    $this->actingAs($admin)->post(route('people.store'), [
        'name' => 'Anak Satu',
        'marga_id' => $marga->id,
        'birth_order' => 1,
        'sibling_count' => 1,
        'father' => ['name' => 'Si Raja Batak'],
        'children' => [['name' => 'Anak Satu', 'gender' => 'L']],
    ])->assertRedirect(route('people.index'));

    $this->actingAs($admin)->post(route('people.store'), [
        'name' => 'Cucu Satu',
        'marga_id' => $marga->id,
        'birth_order' => 1,
        'sibling_count' => 1,
        'father' => ['name' => 'Anak Satu'],
        'children' => [['name' => 'Cucu Satu', 'gender' => 'L']],
    ])->assertRedirect(route('people.index'));

    expect(Person::where('name', 'Anak Satu')->first()->chain)->toBe('1-1')
        ->and(Person::where('name', 'Cucu Satu')->first()->chain)->toBe('1-1-1');

    $fokus = Person::where('name', 'Anak Satu')->first();

    $this->actingAs($admin)->put(route('people.update', $fokus), [
        'name' => $fokus->name,
        'marga_id' => $marga->id,
        'birth_order' => 2,
        'sibling_count' => 2,
        'father' => ['name' => 'Si Raja Batak'],
        'children' => [
            ['name' => 'Kakak', 'gender' => 'L'],
            ['id' => $fokus->id, 'name' => $fokus->name, 'gender' => 'L'],
        ],
    ])->assertRedirect();

    expect(Person::where('name', 'Kakak')->first()->chain)->toBe('1-1')
        ->and(Person::where('name', 'Anak Satu')->first()->chain)->toBe('1-2')
        ->and(Person::where('name', 'Cucu Satu')->first()->chain)->toBe('1-2-1');
});
