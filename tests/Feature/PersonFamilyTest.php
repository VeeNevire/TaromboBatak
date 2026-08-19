<?php

use App\Models\FamilyTree;
use App\Models\Marga;
use App\Models\Person;
use App\Models\User;
use App\Services\ChainNumberingService;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->asAdmin()->create();
});

afterEach(function () {
    Carbon::setTestNow();
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
        ->and($father->gender)->toBe('L')
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

test('changing a father path does not move or renumber his siblings', function () {
    $marga = Marga::factory()->create();
    $oldRoot = Person::factory()->create([
        'name' => 'Root Lama',
        'gender' => 'L',
        'marga_id' => $marga->id,
    ]);
    $newRoot = Person::factory()->create([
        'name' => 'Root Baru',
        'gender' => 'L',
        'marga_id' => $marga->id,
    ]);
    $father = Person::factory()->create([
        'name' => 'Ayah Fokus',
        'gender' => 'L',
        'marga_id' => $marga->id,
        'father_id' => $oldRoot->id,
        'birth_order' => 1,
        'sibling_count' => 2,
    ]);
    $fathersSibling = Person::factory()->create([
        'name' => 'Saudara Ayah',
        'gender' => 'L',
        'marga_id' => $marga->id,
        'father_id' => $oldRoot->id,
        'birth_order' => 2,
        'sibling_count' => 2,
    ]);
    $child = Person::factory()->create([
        'name' => 'Anak Ayah',
        'gender' => 'L',
        'marga_id' => $marga->id,
        'father_id' => $father->id,
        'birth_order' => 1,
    ]);

    $numbering = app(ChainNumberingService::class);
    $numbering->recomputeFromAncestor($oldRoot);
    $numbering->recomputeFromAncestor($newRoot);

    expect($oldRoot->fresh()->chain)->toBe('1')
        ->and($newRoot->fresh()->chain)->toBeNull()
        ->and($father->fresh()->chain)->toBe('1-1')
        ->and($fathersSibling->fresh()->chain)->toBe('1-2')
        ->and($child->fresh()->chain)->toBe('1-1-1');

    $this->actingAs($this->admin)
        ->put(route('people.update', $father), [
            'name' => $father->name,
            'gender' => 'L',
            'marga_id' => $marga->id,
            'birth_order' => 1,
            'sibling_count' => 2,
            'father' => ['name' => $newRoot->name],
            'children' => [
                ['id' => $father->id, 'name' => $father->name, 'gender' => 'L'],
                ['id' => $fathersSibling->id, 'name' => $fathersSibling->name, 'gender' => 'L'],
            ],
            'ownChildren' => [
                ['id' => $child->id, 'name' => $child->name, 'gender' => 'L'],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($father->fresh()->father_id)->toBe($newRoot->id)
        ->and($father->fresh()->chain)->toBe('2-1')
        ->and($fathersSibling->fresh()->father_id)->toBe($oldRoot->id)
        ->and($fathersSibling->fresh()->birth_order)->toBe(2)
        ->and($fathersSibling->fresh()->chain)->toBe('1-2')
        ->and($child->fresh()->chain)->toBe('2-1-1');
});

test('removing a saved sibling deletes the person and their whole descendant branch', function () {
    $marga = Marga::factory()->create();
    $father = Person::factory()->create(['marga_id' => $marga->id]);

    $focused = Person::factory()->create([
        'name' => 'Fokus',
        'marga_id' => $marga->id,
        'father_id' => $father->id,
        'birth_order' => 1,
        'sibling_count' => 2,
    ]);

    $sibling = Person::factory()->create([
        'name' => 'Saudara',
        'marga_id' => $marga->id,
        'father_id' => $father->id,
        'birth_order' => 2,
    ]);

    $child = Person::factory()->create([
        'name' => 'Keponakan',
        'marga_id' => $marga->id,
        'father_id' => $sibling->id,
        'birth_order' => 1,
    ]);

    $grandchild = Person::factory()->create([
        'name' => 'Cicit Keponakan',
        'marga_id' => $marga->id,
        'father_id' => $child->id,
        'birth_order' => 1,
    ]);

    $this->actingAs($this->admin)
        ->put(route('people.update', $focused), [
            'name' => $focused->name,
            'marga_id' => $marga->id,
            'birth_order' => 1,
            'sibling_count' => 1,
            'father' => ['name' => $father->name],
            'children' => [
                ['id' => $focused->id, 'name' => $focused->name, 'gender' => 'L'],
            ],
            'removed_child_ids' => [$sibling->id],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(Person::find($sibling->id))->toBeNull()
        ->and(Person::find($child->id))->toBeNull()
        ->and(Person::find($grandchild->id))->toBeNull()
        ->and(Person::find($focused->id))->not->toBeNull()
        ->and(Person::find($father->id))->not->toBeNull();
});

test('removing a saved own child deletes the child and their descendants', function () {
    $marga = Marga::factory()->create();
    $focused = Person::factory()->create(['name' => 'Fokus', 'marga_id' => $marga->id]);

    $anak = Person::factory()->create([
        'name' => 'Anak',
        'marga_id' => $marga->id,
        'father_id' => $focused->id,
        'birth_order' => 1,
    ]);

    $cucu = Person::factory()->create([
        'name' => 'Cucu',
        'marga_id' => $marga->id,
        'father_id' => $anak->id,
        'birth_order' => 1,
    ]);

    $this->actingAs($this->admin)
        ->put(route('people.update', $focused), [
            'name' => $focused->name,
            'marga_id' => $marga->id,
            'father' => null,
            'children' => [],
            'ownChildren' => [],
            'removed_own_child_ids' => [$anak->id],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(Person::find($anak->id))->toBeNull()
        ->and(Person::find($cucu->id))->toBeNull()
        ->and(Person::find($focused->id))->not->toBeNull();
});

test('the focused person is never removed even when listed as removed', function () {
    $marga = Marga::factory()->create();
    $focused = Person::factory()->create(['name' => 'Fokus', 'marga_id' => $marga->id]);

    $this->actingAs($this->admin)
        ->put(route('people.update', $focused), [
            'name' => $focused->name,
            'marga_id' => $marga->id,
            'father' => null,
            'children' => [['id' => $focused->id, 'name' => $focused->name, 'gender' => 'L']],
            'removed_child_ids' => [$focused->id],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(Person::find($focused->id))->not->toBeNull();
});

test('the show route exposes descendant counts and names for the delete preview', function () {
    $marga = Marga::factory()->create();
    $father = Person::factory()->create(['marga_id' => $marga->id]);

    $focused = Person::factory()->create([
        'name' => 'Fokus',
        'marga_id' => $marga->id,
        'father_id' => $father->id,
        'birth_order' => 1,
    ]);

    $sibling = Person::factory()->create([
        'name' => 'Saudara',
        'marga_id' => $marga->id,
        'father_id' => $father->id,
        'birth_order' => 2,
    ]);

    Person::factory()->create([
        'name' => 'Keponakan',
        'marga_id' => $marga->id,
        'father_id' => $sibling->id,
        'birth_order' => 1,
    ]);

    $this->actingAs($this->admin)
        ->get(route('people.show', $focused))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('people/show')
            ->where('person.children.1.descendant_count', 1)
            ->where('person.children.1.descendant_names.0', 'Keponakan')
            ->where('person.children.0.descendant_count', 0));
});

test('a non-staff user cannot delete a sibling they do not own', function () {
    $marga = Marga::factory()->create();
    $user = User::factory()->withMarga($marga->id)->create();
    $father = Person::factory()->create(['marga_id' => $marga->id]);

    $focused = Person::factory()->create([
        'name' => 'Fokus',
        'marga_id' => $marga->id,
        'father_id' => $father->id,
        'birth_order' => 1,
        'created_by' => $user->id,
    ]);

    $sibling = Person::factory()->create([
        'name' => 'Saudara Admin',
        'marga_id' => $marga->id,
        'father_id' => $father->id,
        'birth_order' => 2,
    ]);

    $this->actingAs($user)
        ->put(route('people.update', $focused), [
            'name' => $focused->name,
            'marga_id' => $marga->id,
            'birth_order' => 1,
            'sibling_count' => 1,
            'father' => ['name' => $father->name],
            'children' => [
                ['id' => $focused->id, 'name' => $focused->name, 'gender' => 'L'],
            ],
            'removed_child_ids' => [$sibling->id],
        ])
        ->assertInertia(fn (Assert $page) => $page->component('Error/Page'));

    expect(Person::find($sibling->id))->not->toBeNull();
});

test('a family store persists the alias for father, mother, siblings, and own children', function () {
    $marga = Marga::factory()->create(['name' => 'Sitorus']);
    $focused = Person::factory()->create(['name' => 'Ompu Sitorus', 'marga_id' => $marga->id]);

    $this->actingAs($this->admin)
        ->put(route('people.update', $focused), [
            'name' => $focused->name,
            'alias' => 'Tuan Sorba Dibanua',
            'marga_id' => $marga->id,
            'birth_order' => 1,
            'father' => ['name' => 'Si Raja Batak', 'alias' => 'Siraja Batak'],
            'mother' => ['name' => 'Borbor', 'alias' => 'Sorbajandi'],
            'children' => [
                ['id' => $focused->id, 'name' => $focused->name, 'gender' => 'L'],
                ['name' => 'Adik', 'gender' => 'L', 'alias' => 'Si Adik'],
            ],
            'ownChildren' => [
                ['name' => 'Anak', 'gender' => 'L', 'alias' => 'Si Anak'],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(Person::where('name', 'Si Raja Batak')->first()->alias)->toBe('Siraja Batak')
        ->and(Person::where('name', 'Borbor')->first()->alias)->toBe('Sorbajandi')
        ->and(Person::where('name', 'Adik')->first()->alias)->toBe('Si Adik')
        ->and(Person::where('name', 'Anak')->first()->alias)->toBe('Si Anak')
        ->and($focused->fresh()->alias)->toBe('Tuan Sorba Dibanua');
});

test('the show route exposes the alias for father, mother, siblings, and own children', function () {
    $marga = Marga::factory()->create();
    $father = Person::factory()->create(['name' => 'Si Raja Batak', 'alias' => 'Siraja Batak', 'marga_id' => $marga->id]);
    $mother = Person::factory()->create(['name' => 'Borbor', 'alias' => 'Sorbajandi', 'marga_id' => $marga->id]);

    $focused = Person::factory()->create([
        'name' => 'Ompu Sitorus',
        'alias' => 'Tuan Sorba Dibanua',
        'marga_id' => $marga->id,
        'father_id' => $father->id,
        'mother_id' => $mother->id,
        'birth_order' => 1,
    ]);

    $sibling = Person::factory()->create([
        'name' => 'Adik',
        'alias' => 'Si Adik',
        'marga_id' => $marga->id,
        'father_id' => $father->id,
        'birth_order' => 2,
    ]);

    $ownChild = Person::factory()->create([
        'name' => 'Anak',
        'alias' => 'Si Anak',
        'marga_id' => $marga->id,
        'father_id' => $focused->id,
        'birth_order' => 1,
    ]);

    $this->actingAs($this->admin)
        ->get(route('people.show', $focused))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('people/show')
            ->where('person.alias', 'Tuan Sorba Dibanua')
            ->where('person.father.alias', 'Siraja Batak')
            ->where('person.mother.alias', 'Sorbajandi')
            ->where('person.children.1.alias', 'Si Adik')
            ->where('person.ownChildren.0.alias', 'Si Anak'));

    expect($sibling->fresh()->id)->not->toBeNull()
        ->and($ownChild->fresh()->id)->not->toBeNull();
});

test('the create form lists each family tree created by the signed in account', function () {
    $marga = Marga::factory()->create(['name' => 'Sitorus']);
    $user = User::factory()->withMarga($marga->id)->create();
    $otherUser = User::factory()->withMarga($marga->id)->create();

    Carbon::setTestNow('2026-08-18 10:00:00');
    $this->actingAs($user)->post(route('people.store'), [
        'name' => 'Anak Pertama',
        'gender' => 'L',
        'marga_id' => $marga->id,
        'birth_order' => 1,
        'sibling_count' => 1,
        'father' => ['name' => 'Si Raja Batak'],
        'children' => [['name' => 'Anak Pertama', 'gender' => 'L']],
    ])->assertRedirect(route('people.index'));

    Carbon::setTestNow('2026-08-19 10:00:00');
    $this->actingAs($user)->post(route('people.store'), [
        'name' => 'Anak Kedua',
        'gender' => 'L',
        'marga_id' => $marga->id,
        'birth_order' => 1,
        'sibling_count' => 1,
        'father' => ['name' => 'Ayah Kakek Tunggul'],
        'children' => [['name' => 'Anak Kedua', 'gender' => 'L']],
    ])->assertRedirect(route('people.index'));

    $otherRoot = Person::factory()->create(['name' => 'Pohon Akun Lain', 'marga_id' => $marga->id]);
    FamilyTree::create([
        'user_id' => $otherUser->id,
        'root_person_id' => $otherRoot->id,
    ]);

    $this->actingAs($user)
        ->get(route('people.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('people/form')
            ->has('familyTrees', 2)
            ->where('familyTrees.0.root_name', 'Ayah Kakek Tunggul')
            ->where('familyTrees.1.root_name', 'Si Raja Batak')
            ->where('familyTrees.0.updated_at', '2026-08-19T10:00:00.000000Z')
            ->missing('familyTrees.2'));
});

test('updating a member refreshes its family tree without creating another item', function () {
    $marga = Marga::factory()->create(['name' => 'Sitorus']);
    $user = User::factory()->withMarga($marga->id)->create();

    Carbon::setTestNow('2026-08-18 10:00:00');
    $this->actingAs($user)->post(route('people.store'), [
        'name' => 'Anak',
        'gender' => 'L',
        'marga_id' => $marga->id,
        'birth_order' => 1,
        'sibling_count' => 1,
        'father' => ['name' => 'Ompu Sitorus'],
        'children' => [['name' => 'Anak', 'gender' => 'L']],
    ])->assertRedirect(route('people.index'));

    $focus = Person::query()->where('name', 'Anak')->firstOrFail();
    $tree = FamilyTree::query()->whereBelongsTo($user)->firstOrFail();

    Carbon::setTestNow('2026-08-19 11:30:00');
    $this->actingAs($user)->put(route('people.update', $focus), [
        'name' => 'Anak Diperbarui',
        'gender' => 'L',
        'marga_id' => $marga->id,
        'birth_order' => 1,
        'sibling_count' => 1,
        'father' => ['name' => 'Ompu Sitorus'],
        'children' => [['id' => $focus->id, 'name' => 'Anak Diperbarui', 'gender' => 'L']],
    ])->assertRedirect();

    expect(FamilyTree::query()->whereBelongsTo($user)->count())->toBe(1)
        ->and($tree->fresh()->updated_at->toISOString())->toBe('2026-08-19T11:30:00.000000Z');
});

test('an account can open its family tree without exposing unrelated trees', function () {
    $marga = Marga::factory()->create(['name' => 'Sitorus']);
    $user = User::factory()->withMarga($marga->id)->create();

    $this->actingAs($user)->post(route('people.store'), [
        'name' => 'Anak',
        'gender' => 'L',
        'marga_id' => $marga->id,
        'birth_order' => 1,
        'sibling_count' => 1,
        'father' => ['name' => 'Root Milik Saya'],
        'children' => [['name' => 'Anak', 'gender' => 'L']],
    ])->assertRedirect(route('people.index'));

    Person::factory()->create(['name' => 'Pohon Tidak Terkait', 'marga_id' => $marga->id]);
    $tree = FamilyTree::query()->whereBelongsTo($user)->firstOrFail();

    $this->actingAs($user)
        ->get(route('family-trees.show', $tree))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('people/silsilah')
            ->has('people', 2)
            ->where('person.name', 'Root Milik Saya'));
});

test('the create form suggests only male people as fathers', function () {
    Person::factory()->create(['name' => 'Calon Ayah', 'gender' => 'L']);
    Person::factory()->create(['name' => 'Perempuan', 'gender' => 'P']);
    Person::factory()->create(['name' => 'Belum Diketahui', 'gender' => null]);

    $this->actingAs($this->admin)
        ->get(route('people.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('fatherSuggestions', ['Calon Ayah']));
});

test('father suggestions exclude the focused person siblings and descendants', function () {
    $marga = Marga::factory()->create();
    $father = Person::factory()->create([
        'name' => 'Ayah Saat Ini',
        'gender' => null,
        'marga_id' => $marga->id,
    ]);
    $focus = Person::factory()->create([
        'name' => 'Fokus',
        'gender' => 'L',
        'marga_id' => $marga->id,
        'father_id' => $father->id,
    ]);
    Person::factory()->create([
        'name' => 'Saudara Laki Laki',
        'gender' => 'L',
        'marga_id' => $marga->id,
        'father_id' => $father->id,
    ]);
    $child = Person::factory()->create([
        'name' => 'Anak Laki Laki',
        'gender' => 'L',
        'marga_id' => $marga->id,
        'father_id' => $focus->id,
    ]);
    Person::factory()->create([
        'name' => 'Cucu Laki Laki',
        'gender' => 'L',
        'marga_id' => $marga->id,
        'father_id' => $child->id,
    ]);
    Person::factory()->create([
        'name' => 'Kandidat Ayah',
        'gender' => 'L',
        'marga_id' => $marga->id,
    ]);
    Person::factory()->create([
        'name' => 'Kandidat Perempuan',
        'gender' => 'P',
        'marga_id' => $marga->id,
    ]);

    foreach (['people.edit', 'people.show'] as $routeName) {
        $this->actingAs($this->admin)
            ->get(route($routeName, $focus))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('fatherSuggestions', ['Ayah Saat Ini', 'Kandidat Ayah']));
    }
});

test('a descendant cannot be submitted manually as the father', function () {
    $marga = Marga::factory()->create();
    $focus = Person::factory()->create([
        'name' => 'Fokus',
        'gender' => 'L',
        'marga_id' => $marga->id,
        'birth_order' => 1,
    ]);
    $child = Person::factory()->create([
        'name' => 'Anak Fokus',
        'gender' => 'L',
        'marga_id' => $marga->id,
        'father_id' => $focus->id,
    ]);

    $this->actingAs($this->admin)
        ->from(route('people.edit', $focus))
        ->put(route('people.update', $focus), [
            'name' => $focus->name,
            'gender' => 'L',
            'marga_id' => $marga->id,
            'birth_order' => 1,
            'sibling_count' => 1,
            'father' => ['name' => $child->name],
            'children' => [[
                'id' => $focus->id,
                'name' => $focus->name,
                'gender' => 'L',
            ]],
        ])
        ->assertRedirect(route('people.edit', $focus))
        ->assertSessionHasErrors('father.name');
});
