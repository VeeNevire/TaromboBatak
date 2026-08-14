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
    expect($father->is_leader)->toBeTrue();

    $children = Person::where('father_id', $father->id)->orderBy('birth_order')->get();
    // Focus person (A, birth_order 1) is now a leader -> flat nomor 2
    // Siblings B, C get dotted numbers from father
    expect($children->pluck('nomor')->all())->toBe(['2', '1.2', '1.3']);
    expect($children->pluck('is_leader')->all())->toBe([true, false, false]);
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

    // Each family adds 2 leaders (father + focus), so numbers: 1,2,3,4
    expect(Person::where('name', 'Root Satu')->first()->nomor)->toBe('1');
    expect(Person::where('name', 'Root Dua')->first()->nomor)->toBe('3');
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

    // First family: Si Raja Batak (1), Anak Satu (2, leader)
    // Second family: Anak Satu is father (already leader 2), Cucu Satu is focus -> leader 3
    expect(Person::where('name', 'Cucu Satu')->first()->nomor)->toBe('3');
    expect(Person::where('name', 'Cucu Satu')->first()->is_leader)->toBeTrue();
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
        ->and($mother->marga_id)->toBeNull()
        ->and($mother->is_leader)->toBeFalse();
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

test('placing the focus leader at slot 9 moves the father to slot 8 and links the grandfather', function () {
    $admin = User::factory()->asAdmin()->create();
    $marga = Marga::factory()->create(['name' => 'Sitorus']);

    // Rantai patrilineal leader 1..7 (ayah -> anak) yang sudah tersimpan.
    $root = Person::factory()->create([
        'name' => 'Si Raja Batak',
        'marga_id' => $marga->id,
        'is_leader' => true,
        'nomor' => '1',
        'nomor_manual' => true,
    ]);

    $prev = $root;

    for ($i = 2; $i <= 7; $i++) {
        $prev = Person::factory()->create([
            'name' => "Pemimpin $i",
            'marga_id' => $marga->id,
            'father_id' => $prev->id,
            'is_leader' => true,
            'nomor' => (string) $i,
            'nomor_manual' => true,
        ]);
    }

    $this->actingAs($admin)->post(route('people.store'), [
        'name' => 'Saya',
        'marga_id' => $marga->id,
        'is_leader' => true,
        'nomor' => '9',
        'birth_order' => 1,
        'sibling_count' => 1,
        'father' => ['name' => 'Si Bapak', 'marga_id' => $marga->id, 'nomor' => '8', 'is_leader' => true],
        'children' => [['name' => 'Saya', 'gender' => 'L']],
    ])->assertRedirect(route('people.index'));

    $father = Person::where('name', 'Si Bapak')->first();
    $focus = Person::where('name', 'Saya')->first();

    expect($father)->not->toBeNull()
        ->and($father->nomor)->toBe('8')
        ->and($father->is_leader)->toBeTrue()
        ->and($father->nomor_manual)->toBeTrue()
        ->and($father->father_id)->toBe($prev->id)
        ->and($focus->father_id)->toBe($father->id)
        ->and($focus->nomor)->toBe('9')
        ->and(Person::where('nomor', '8')->count())->toBe(1);
});

test('typing a father name fills the N/A placeholder leader slot', function () {
    $admin = User::factory()->asAdmin()->create();
    $marga = Marga::factory()->create(['name' => 'Sitorus']);

    $root = Person::factory()->create([
        'name' => 'Si Raja Batak',
        'marga_id' => $marga->id,
        'is_leader' => true,
        'nomor' => '1',
        'nomor_manual' => true,
    ]);

    $placeholder = Person::factory()->create([
        'name' => 'N/A',
        'marga_id' => $marga->id,
        'is_leader' => true,
        'nomor' => '8',
        'nomor_manual' => true,
    ]);

    $this->actingAs($admin)->post(route('people.store'), [
        'name' => 'Saya',
        'marga_id' => $marga->id,
        'is_leader' => true,
        'nomor' => '9',
        'birth_order' => 1,
        'sibling_count' => 1,
        'father' => ['name' => 'Si Bapak', 'marga_id' => $marga->id, 'nomor' => '8', 'is_leader' => true],
        'children' => [['name' => 'Saya', 'gender' => 'L']],
    ])->assertRedirect(route('people.index'));

    $father = Person::where('name', 'Si Bapak')->first();
    $focus = Person::where('name', 'Saya')->first();

    expect($father->id)->toBe($placeholder->id)
        ->and($father->nomor)->toBe('8')
        ->and($focus->father_id)->toBe($placeholder->id)
        ->and(Person::where('nomor', '8')->count())->toBe(1)
        ->and(Person::where('name', 'N/A')->where('nomor', '8')->count())->toBe(0);
});