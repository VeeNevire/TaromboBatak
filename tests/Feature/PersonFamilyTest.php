<?php

use App\Models\Marga;
use App\Models\Person;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->asAdmin()->create();
});

test('a family store creates the father, mother, and all sibling rows as people', function () {
    $marga = Marga::factory()->create(['name' => 'Sitorus']);

    $response = $this->actingAs($this->admin)->post(route('people.store'), [
        'name' => 'Ompu Sitorus',
        'gender' => 'L',
        'alias' => 'Tuan Sorba Dibanua',
        'marga_id' => $marga->id,
        'birth_order' => 2,
        'sibling_count' => 3,
        'father' => ['name' => 'Si Raja Batak', 'birth_year' => '1850', 'death_year' => '1920'],
        'mother' => ['name' => 'Borbor', 'birth_year' => '1855'],
        'children' => [
            ['name' => 'A', 'gender' => 'L', 'spouse' => 'X', 'spouse_marga' => 'Hutapea'],
            ['name' => 'Ompu Sitorus', 'gender' => 'L', 'spouse' => 'Y', 'spouse_marga' => 'Nababan'],
            ['name' => 'N/A', 'gender' => 'P', 'spouse' => '', 'spouse_marga' => ''],
        ],
    ]);

    $response->assertRedirect(route('people.index'));

    $father = Person::where('name', 'Si Raja Batak')->first();
    $mother = Person::where('name', 'Borbor')->first();

    expect($father)->not->toBeNull()
        ->and($mother)->not->toBeNull()
        ->and($father->marga_id)->toBe($marga->id)
        ->and($father->chain)->toBe('1');

    $children = Person::where('father_id', $father->id)->orderBy('birth_order')->get();

    expect($children)->toHaveCount(3)
        ->and($children->every(fn (Person $child) => $child->mother_id === $mother->id))->toBeTrue()
        ->and($children->every(fn (Person $child) => $child->marga_id === $marga->id))->toBeTrue()
        ->and($children[1]->name)->toBe('Ompu Sitorus')
        ->and($children[1]->birth_order)->toBe(2)
        ->and($children[1]->sibling_count)->toBe(3)
        ->and($children[1]->chain)->toBe('1-2')
        ->and($children[0]->spouse_marga)->toBe('Hutapea')
        ->and($children[2]->name)->toBe('N/A');
});

test('the show route exposes the whole family sheet', function () {
    $marga = Marga::factory()->create();
    $father = Person::factory()->create(['marga_id' => $marga->id]);

    $focused = Person::factory()->create([
        'name' => 'Ompu Sitorus',
        'marga_id' => $marga->id,
        'father_id' => $father->id,
        'birth_order' => 1,
        'sibling_count' => 2,
    ]);

    Person::factory()->create([
        'name' => 'Adik',
        'marga_id' => $marga->id,
        'father_id' => $father->id,
        'birth_order' => 2,
    ]);

    $this->actingAs($this->admin)
        ->get(route('people.show', $focused))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('people/show')
            ->where('person.id', $focused->id)
            ->has('person.children', 2)
            ->has('person.father', fn (Assert $parent) => $parent
                ->where('name', $father->name)
                ->etc()));
});

test('updating a family persists changes and new siblings', function () {
    $marga = Marga::factory()->create();
    $father = Person::factory()->create(['marga_id' => $marga->id]);

    $focused = Person::factory()->create([
        'name' => 'Ompu Sitorus',
        'marga_id' => $marga->id,
        'father_id' => $father->id,
        'birth_order' => 1,
        'sibling_count' => 1,
    ]);

    $sibling = Person::factory()->create([
        'name' => 'Saudara',
        'marga_id' => $marga->id,
        'father_id' => $father->id,
        'birth_order' => 2,
    ]);

    $this->actingAs($this->admin)->put(route('people.update', $focused), [
        'name' => 'Ompu Sitorus',
        'marga_id' => $marga->id,
        'birth_order' => 1,
        'sibling_count' => 3,
        'father' => ['name' => $father->name],
        'children' => [
            ['id' => $focused->id, 'name' => 'Ompu Sitorus', 'gender' => 'L'],
            ['id' => $sibling->id, 'name' => 'Saudara', 'gender' => 'P'],
            ['name' => 'Baru', 'gender' => 'L'],
        ],
    ])->assertRedirect();

    $children = Person::where('father_id', $father->id)->orderBy('birth_order')->get();

    expect($children)->toHaveCount(3)
        ->and($children[2]->name)->toBe('Baru')
        ->and($children[2]->birth_order)->toBe(3)
        ->and($children[2]->sibling_count)->toBe(3);
});

test('the preview returns the close family with fathers mothers branches and ordered children', function () {
    $marga = Marga::factory()->create(['name' => 'Sitorus']);

    $kakek = Person::factory()->create(['name' => 'Kakek', 'marga_id' => $marga->id]);
    $nenek = Person::factory()->create(['name' => 'Nenek', 'marga_id' => $marga->id]);
    $ayah = Person::factory()->create([
        'name' => 'Ayah',
        'marga_id' => $marga->id,
        'father_id' => $kakek->id,
        'mother_id' => $nenek->id,
    ]);
    $om = Person::factory()->create([
        'name' => 'Om',
        'marga_id' => $marga->id,
        'father_id' => $kakek->id,
    ]);

    $ibuKakek = Person::factory()->create(['name' => 'IbuKakek', 'marga_id' => $marga->id]);
    $ibuNenek = Person::factory()->create(['name' => 'IbuNenek', 'marga_id' => $marga->id]);
    $ibu = Person::factory()->create([
        'name' => 'Ibu',
        'marga_id' => $marga->id,
        'father_id' => $ibuKakek->id,
        'mother_id' => $ibuNenek->id,
    ]);
    Person::factory()->create(['name' => 'Bibi', 'marga_id' => $marga->id, 'father_id' => $ibuKakek->id]);

    $person = Person::factory()->create([
        'name' => 'Anak Ke Dua',
        'marga_id' => $marga->id,
        'father_id' => $ayah->id,
        'mother_id' => $ibu->id,
        'birth_order' => 2,
        'birth_year' => '2000',
    ]);
    $adik = Person::factory()->create([
        'name' => 'Adik',
        'marga_id' => $marga->id,
        'father_id' => $ayah->id,
        'mother_id' => $ibu->id,
        'birth_order' => 3,
    ]);

    Person::factory()->create(['name' => 'Tidak Terkait', 'marga_id' => $marga->id]);

    $this->actingAs($this->admin)
        ->getJson(route('people.preview', $person))
        ->assertOk()
        ->assertJsonPath('centerId', (string) $person->id)
        ->assertJsonPath('person.birthOrder', 2)
        ->assertJsonPath('father.id', (string) $ayah->id)
        ->assertJsonPath('father.parents.0.name', 'Kakek')
        ->assertJsonPath('father.parents.1.name', 'Nenek')
        ->assertJsonPath('father.siblings.0.name', 'Om')
        ->assertJsonPath('mother.parents.0.id', (string) $ibuKakek->id)
        ->assertJsonPath('mother.parents.1.id', (string) $ibuNenek->id)
        ->assertJsonPath('mother.siblings.0.name', 'Bibi')
        ->assertJsonCount(2, 'children')
        ->assertJsonPath('children.0.id', (string) $person->id)
        ->assertJsonPath('children.1.id', (string) $adik->id)
        ->assertJsonPath('children.0.birthOrder', 2)
        ->assertJsonMissing(['name' => 'Tidak Terkait']);
});

test('the silsilah page exposes the descendant tree data', function () {
    $marga = Marga::factory()->create(['name' => 'Sitorus']);
    $person = Person::factory()->create([
        'name' => 'Ompu Sitorus',
        'marga_id' => $marga->id,
        'birth_order' => 2,
    ]);
    Person::factory()->create(['name' => 'Lain', 'marga_id' => $marga->id]);

    $this->actingAs($this->admin)
        ->get(route('people.silsilah', $person))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('people/silsilah')
            ->where('centerPersonId', (string) $person->id)
            ->where('person.name', 'Ompu Sitorus')
            ->has('people', 1));
});

test('the preview gracefully falls back when the person has no parents', function () {
    $marga = Marga::factory()->create();
    $person = Person::factory()->create(['name' => 'Yatim', 'marga_id' => $marga->id, 'birth_order' => 1]);

    $this->actingAs($this->admin)
        ->getJson(route('people.preview', $person))
        ->assertOk()
        ->assertJsonPath('father', null)
        ->assertJsonPath('mother', null)
        ->assertJsonCount(1, 'children')
        ->assertJsonPath('children.0.id', (string) $person->id);
});

test('the show route exposes the patrilineal lineage chain ordered root to self', function () {
    $marga = Marga::factory()->create();
    $buyut = Person::factory()->create(['name' => 'Buyut', 'marga_id' => $marga->id, 'chain' => '1']);
    $kakek = Person::factory()->create(['name' => 'Kakek', 'marga_id' => $marga->id, 'father_id' => $buyut->id]);
    $ayah = Person::factory()->create(['name' => 'Ayah', 'marga_id' => $marga->id, 'father_id' => $kakek->id]);
    $fokus = Person::factory()->create(['name' => 'Fokus', 'marga_id' => $marga->id, 'father_id' => $ayah->id, 'birth_order' => 1]);

    $this->actingAs($this->admin)
        ->get(route('people.show', $fokus))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('people/show')
            ->where('person.lineage.0.name', 'Buyut')
            ->where('person.lineage.1.name', 'Kakek')
            ->where('person.lineage.2.name', 'Ayah')
            ->where('person.lineage.3.name', 'Fokus')
            ->where('person.lineage.3.is_self', true)
            ->where('person.lineage.0.is_self', false));
});

test('updating a family recomputes chains for the patrilineal lineage', function () {
    $marga = Marga::factory()->create();
    $father = Person::factory()->create(['name' => 'Si Bapak', 'marga_id' => $marga->id, 'father_id' => null]);

    $focused = Person::factory()->create([
        'name' => 'Guru Tatea Bulan',
        'marga_id' => $marga->id,
        'father_id' => $father->id,
        'birth_order' => 1,
        'sibling_count' => 3,
    ]);
    $second = Person::factory()->create([
        'name' => 'Raja Biakbiak',
        'marga_id' => $marga->id,
        'father_id' => $father->id,
        'birth_order' => 2,
    ]);
    $third = Person::factory()->create([
        'name' => 'Limbong Mulana',
        'marga_id' => $marga->id,
        'father_id' => $father->id,
        'birth_order' => 3,
    ]);

    $this->actingAs($this->admin)
        ->put(route('people.update', $focused), [
            'name' => $focused->name,
            'marga_id' => $marga->id,
            'birth_order' => 1,
            'sibling_count' => 3,
            'father' => ['name' => $father->name],
            'children' => [
                ['id' => $focused->id, 'name' => $focused->name, 'gender' => 'L'],
                ['id' => $second->id, 'name' => $second->name, 'gender' => 'L'],
                ['id' => $third->id, 'name' => $third->name, 'gender' => 'L'],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($father->fresh()->chain)->toBe('1')
        ->and($focused->fresh()->chain)->toBe('1-1')
        ->and($second->fresh()->chain)->toBe('1-2')
        ->and($third->fresh()->chain)->toBe('1-3');
});
