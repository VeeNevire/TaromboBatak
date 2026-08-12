<?php

use App\Models\Marga;
use App\Models\Person;
use App\Models\User;
use App\Services\TaromboNumberingService;

test('auto numbering assigns dotted numbers to a new family', function () {
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
    expect($father->nomor)->toBe('1');

    $children = Person::where('father_id', $father->id)->orderBy('birth_order')->get();
    expect($children->pluck('nomor')->all())->toBe(['1.1', '1.2', '1.3']);
});

test('a second root lineage gets the next root number', function () {
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

    expect(Person::where('name', 'Root Satu')->first()->nomor)->toBe('1');
    expect(Person::where('name', 'Root Dua')->first()->nomor)->toBe('2');
});

test('grandchildren inherit the dotted number of their father', function () {
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

    expect(Person::where('name', 'Cucu Satu')->first()->nomor)->toBe('1.1.1');
});

test('a manual number is preserved and never overwritten', function () {
    $admin = User::factory()->asAdmin()->create();
    $marga = Marga::factory()->create();

    $this->actingAs($admin)->post(route('people.store'), [
        'name' => 'Ompu',
        'marga_id' => $marga->id,
        'nomor' => '7',
        'birth_order' => 1,
        'sibling_count' => 1,
        'father' => ['name' => 'Si Raja Batak'],
        'children' => [['name' => 'Ompu', 'gender' => 'L']],
    ])->assertRedirect(route('people.index'));

    $person = Person::where('name', 'Ompu')->first();
    expect($person->nomor)->toBe('7')
        ->and($person->nomor_manual)->toBeTrue();

    app(TaromboNumberingService::class)->recomputeFromAncestor($person->father);
    expect($person->fresh()->nomor)->toBe('7');
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

test('the mother does not receive an auto number or marga', function () {
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
    expect($mother->nomor)->toBeNull()
        ->and($mother->marga_id)->toBeNull();
});

test('recompute reflects birth order changes down the lineage', function () {
    $root = Person::factory()->create(['name' => 'R', 'father_id' => null]);
    $a = Person::factory()->create(['name' => 'A', 'father_id' => $root->id, 'birth_order' => 1]);
    $b = Person::factory()->create(['name' => 'B', 'father_id' => $root->id, 'birth_order' => 2]);
    $c = Person::factory()->create(['name' => 'C', 'father_id' => $a->id, 'birth_order' => 1]);

    $service = app(TaromboNumberingService::class);
    $service->recomputeFromAncestor($root);

    expect($root->fresh()->nomor)->toBe('1')
        ->and($a->fresh()->nomor)->toBe('1.1')
        ->and($b->fresh()->nomor)->toBe('1.2')
        ->and($c->fresh()->nomor)->toBe('1.1.1');

    $a->update(['birth_order' => 2]);
    $b->update(['birth_order' => 1]);

    $service->recomputeFromAncestor($root);

    expect($a->fresh()->nomor)->toBe('1.2')
        ->and($b->fresh()->nomor)->toBe('1.1')
        ->and($c->fresh()->nomor)->toBe('1.2.1');
});

test('leaders get flat lineage numbers while children follow the father number', function () {
    $service = app(TaromboNumberingService::class);

    $root = Person::factory()->create(['name' => 'Si Raja Batak', 'father_id' => null, 'is_leader' => true]);
    $line = Person::factory()->create(['name' => 'Pemimpin Kedua', 'father_id' => $root->id, 'birth_order' => 1, 'is_leader' => true]);
    $side = Person::factory()->create(['name' => 'Anak Samping', 'father_id' => $root->id, 'birth_order' => 2]);
    $line2 = Person::factory()->create(['name' => 'Pemimpin Ketiga', 'father_id' => $line->id, 'birth_order' => 1, 'is_leader' => true]);
    $leaf = Person::factory()->create(['name' => 'Cucu', 'father_id' => $line2->id, 'birth_order' => 2]);

    $service->recomputeFromAncestor($root);

    expect($root->fresh()->nomor)->toBe('1')
        ->and($line->fresh()->nomor)->toBe('2')
        ->and($side->fresh()->nomor)->toBe('1.2')
        ->and($line2->fresh()->nomor)->toBe('3')
        ->and($leaf->fresh()->nomor)->toBe('3.2');
});
