<?php

use App\Models\ContributionRequest;
use App\Models\FamilyTree;
use App\Models\FamilyTreeNode;
use App\Models\Marga;
use App\Models\Person;
use App\Models\User;
use App\Services\ChainNumberingService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
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
        'province_code' => '32',
        'regency_code' => '32.01',
        'district_code' => '32.01.02',
        'village_code' => '32.01.02.2001',
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
        ->and($children[1]->province_code)->toBe('32')
        ->and($children[1]->regency_code)->toBe('32.01')
        ->and($children[1]->district_code)->toBe('32.01.02')
        ->and($children[1]->village_code)->toBe('32.01.02.2001')
        ->and($children[0]->spouse_marga)->toBe('Hutapea')
        ->and($children[2]->name)->toBe('N/A');
});

test('related story links are stored and exposed on the person form', function () {
    $marga = Marga::factory()->create();

    $this->actingAs($this->admin)->post(route('people.store'), [
        'name' => 'Sangkar Toba',
        'gender' => 'L',
        'marga_id' => $marga->id,
        'birth_order' => 1,
        'sibling_count' => 1,
        'children' => [['name' => 'Sangkar Toba', 'gender' => 'L']],
        'related_stories' => [
            ['title' => 'Sejarah Sangkar Toba', 'url' => 'https://example.com/sejarah'],
            ['title' => 'Cerita Turun-temurun', 'url' => 'https://example.com/cerita'],
        ],
    ])->assertRedirect(route('people.index'));

    $person = Person::query()->where('name', 'Sangkar Toba')->firstOrFail();

    expect($person->related_stories)->toBe([
        ['title' => 'Sejarah Sangkar Toba', 'url' => 'https://example.com/sejarah'],
        ['title' => 'Cerita Turun-temurun', 'url' => 'https://example.com/cerita'],
    ]);

    $this->actingAs($this->admin)
        ->get(route('people.edit', $person))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('person.related_stories.0.title', 'Sejarah Sangkar Toba')
            ->where('person.related_stories.1.url', 'https://example.com/cerita'));
});

test('related story entries require a valid title and http link', function () {
    $marga = Marga::factory()->create();

    $this->actingAs($this->admin)->post(route('people.store'), [
        'name' => 'Data Cerita Tidak Valid',
        'marga_id' => $marga->id,
        'birth_order' => 1,
        'sibling_count' => 1,
        'children' => [['name' => 'Data Cerita Tidak Valid']],
        'related_stories' => [
            ['title' => 'Cerita', 'url' => 'javascript:alert(1)'],
        ],
    ])->assertSessionHasErrors('related_stories.0.url');

    expect(Person::query()->where('name', 'Data Cerita Tidak Valid')->exists())->toBeFalse();
});

test('a family store supports multiple wives with their own marga', function () {
    $marga = Marga::factory()->create(['name' => 'Sitorus']);
    $wifeMarga = Marga::factory()->create(['name' => 'Panjaitan']);

    $response = $this->actingAs($this->admin)->post(route('people.store'), [
        'name' => 'Ompu Sitorus',
        'gender' => 'L',
        'marga_id' => $marga->id,
        'birth_order' => 1,
        'sibling_count' => 2,
        'father' => ['name' => 'Si Raja Batak'],
        'mothers' => [
            ['name' => 'Borbor', 'birth_year' => '1855'],
            ['name' => 'Gultom', 'birth_year' => '1860', 'marga_id' => $wifeMarga->id],
        ],
        'children' => [
            ['name' => 'A', 'gender' => 'L'],
            ['name' => 'Ompu Sitorus', 'gender' => 'L'],
        ],
    ]);

    $response->assertRedirect(route('people.index'));

    $father = Person::where('name', 'Si Raja Batak')->first();
    $firstWife = Person::where('name', 'Borbor')->first();
    $secondWife = Person::where('name', 'Gultom')->first();

    expect($firstWife)->not->toBeNull()
        ->and($secondWife)->not->toBeNull()
        ->and($secondWife->gender)->toBe('P')
        ->and($secondWife->marga_id)->toBe($wifeMarga->id)
        ->and($secondWife->chain)->toBeNull()
        ->and(Person::where('father_id', $father->id)->get()->every(
            fn (Person $child) => $child->mother_id === $firstWife->id,
        ))->toBeTrue();

    $focus = Person::where('name', 'Ompu Sitorus')->firstOrFail();

    $this->actingAs($this->admin)
        ->get(route('people.show', $focus))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('person.mothers', 2)
            ->where('person.mothers.0.id', $firstWife->id)
            ->where('person.mothers.1.id', $secondWife->id));
});

test('a family store assigns each own child to a wife and saves her father', function () {
    $marga = Marga::factory()->create(['name' => 'Sitorus']);
    $firstWifeMarga = Marga::factory()->create(['name' => 'Panjaitan']);
    $secondWifeMarga = Marga::factory()->create(['name' => 'Gultom']);

    $this->actingAs($this->admin)->post(route('people.store'), [
        'name' => 'Ompu Sitorus',
        'gender' => 'L',
        'marga_id' => $marga->id,
        'birth_order' => 1,
        'sibling_count' => 1,
        'father' => ['name' => 'Si Raja Batak'],
        'mothers' => [
            [
                'name' => 'Borbor Panjaitan',
                'marga_id' => $firstWifeMarga->id,
                'father_name' => 'Ompu Panjaitan',
            ],
            [
                'name' => 'Borbor Gultom',
                'marga_id' => $secondWifeMarga->id,
                'father_name' => 'Ompu Gultom',
            ],
        ],
        'children' => [
            ['name' => 'Ompu Sitorus', 'gender' => 'L'],
        ],
        'ownChildren' => [
            ['name' => 'Anak Pertama', 'gender' => 'L', 'mother_index' => 0],
            ['name' => 'Anak Kedua', 'gender' => 'P', 'mother_index' => 1],
        ],
    ])->assertRedirect(route('people.index'))
        ->assertSessionHasNoErrors();

    $firstWife = Person::where('name', 'Borbor Panjaitan')->firstOrFail();
    $secondWife = Person::where('name', 'Borbor Gultom')->firstOrFail();
    $firstWifeFather = Person::where('name', 'Ompu Panjaitan')->firstOrFail();
    $secondWifeFather = Person::where('name', 'Ompu Gultom')->firstOrFail();

    expect($firstWife->father_id)->toBe($firstWifeFather->id)
        ->and($firstWifeFather->marga_id)->toBe($firstWifeMarga->id)
        ->and($secondWife->father_id)->toBe($secondWifeFather->id)
        ->and($secondWifeFather->marga_id)->toBe($secondWifeMarga->id)
        ->and(Person::where('name', 'Anak Pertama')->firstOrFail()->mother_id)->toBe($firstWife->id)
        ->and(Person::where('name', 'Anak Kedua')->firstOrFail()->mother_id)->toBe($secondWife->id);
});

test('a family store automatically assigns the sole wife to own children', function () {
    $marga = Marga::factory()->create(['name' => 'Sitorus']);

    $this->actingAs($this->admin)->post(route('people.store'), [
        'name' => 'Ompu Sitorus',
        'gender' => 'L',
        'marga_id' => $marga->id,
        'birth_order' => 1,
        'sibling_count' => 1,
        'father' => ['name' => 'Si Raja Batak'],
        'mothers' => [['name' => 'Borbor']],
        'children' => [['name' => 'Ompu Sitorus', 'gender' => 'L']],
        'ownChildren' => [['name' => 'Anak Tunggal', 'gender' => 'L']],
    ])->assertRedirect(route('people.index'))
        ->assertSessionHasNoErrors();

    expect(Person::where('name', 'Anak Tunggal')->firstOrFail()->mother_id)
        ->toBe(Person::where('name', 'Borbor')->firstOrFail()->id);
});

test('a family store requires a mother selection when there are multiple wives', function () {
    $marga = Marga::factory()->create(['name' => 'Sitorus']);

    $this->actingAs($this->admin)->post(route('people.store'), [
        'name' => 'Ompu Sitorus',
        'gender' => 'L',
        'marga_id' => $marga->id,
        'birth_order' => 1,
        'sibling_count' => 1,
        'father' => ['name' => 'Si Raja Batak'],
        'mothers' => [
            ['name' => 'Borbor Pertama'],
            ['name' => 'Borbor Kedua'],
        ],
        'children' => [['name' => 'Ompu Sitorus', 'gender' => 'L']],
        'ownChildren' => [['name' => 'Anak', 'gender' => 'L']],
    ])->assertSessionHasErrors([
        'ownChildren.0.mother_index',
    ]);
});

test('the family payload exposes every wife of the shared father', function () {
    $marga = Marga::factory()->create();
    $father = Person::factory()->create(['marga_id' => $marga->id]);
    $wifeFather = Person::factory()->create([
        'name' => 'Ayah Gultom',
        'gender' => 'L',
        'marga_id' => Marga::factory()->create(['name' => 'Gultom'])->id,
    ]);
    $ibuPertama = Person::factory()->create([
        'name' => 'Borbor',
        'gender' => 'P',
        'marga_id' => null,
    ]);
    $ibuKedua = Person::factory()->create([
        'name' => 'Gultom',
        'gender' => 'P',
        'marga_id' => $wifeFather->marga_id,
        'father_id' => $wifeFather->id,
    ]);

    $focused = Person::factory()->create([
        'name' => 'Ompu Sitorus',
        'marga_id' => $marga->id,
        'father_id' => $father->id,
        'mother_id' => $ibuKedua->id,
        'birth_order' => 1,
        'sibling_count' => 2,
    ]);

    Person::factory()->create([
        'name' => 'Adik Seibu',
        'marga_id' => $marga->id,
        'father_id' => $father->id,
        'mother_id' => $ibuKedua->id,
        'birth_order' => 2,
    ]);
    Person::factory()->create([
        'name' => 'Kakak Tiri',
        'marga_id' => $marga->id,
        'father_id' => $father->id,
        'mother_id' => $ibuPertama->id,
        'birth_order' => 3,
    ]);
    $ownChild = Person::factory()->create([
        'name' => 'Anak Fokus',
        'marga_id' => $marga->id,
        'father_id' => $focused->id,
        'mother_id' => $ibuKedua->id,
        'birth_order' => 1,
    ]);

    $this->actingAs($this->admin)
        ->get(route('people.show', $focused))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('people/show')
            ->where('person.mother.name', 'Gultom')
            ->has('person.mothers', 2)
            ->where('person.mothers.0.id', $ibuKedua->id)
            ->where('person.mothers.0.father_name', $wifeFather->name)
            ->where('person.mothers.0.father_marga_id', $wifeFather->marga_id)
            ->where('person.mothers.1.id', $ibuPertama->id)
            ->where('person.mothers.1.marga_id', null)
            ->where('person.ownChildren.0.id', $ownChild->id)
            ->where('person.ownChildren.0.mother_id', $ibuKedua->id));
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
            ->has('familyTrees')
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

test('deleting a saved sibling is rejected when it has descendants', function () {
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
        ->assertSessionHasErrors('children');

    expect(Person::find($sibling->id))->not->toBeNull()
        ->and(Person::find($child->id))->not->toBeNull()
        ->and(Person::find($grandchild->id))->not->toBeNull()
        ->and($sibling->fresh()->father_id)->toBe($father->id)
        ->and($child->fresh()->father_id)->toBe($sibling->id)
        ->and($grandchild->fresh()->father_id)->toBe($child->id)
        ->and($focused->fresh()->father_id)->toBe($father->id)
        ->and($sibling->fresh()->chain)->toBeNull()
        ->and($child->fresh()->chain)->toBeNull()
        ->and($grandchild->fresh()->chain)->toBeNull();
});

test('deleting a saved own child is rejected when it has descendants', function () {
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
        ->assertSessionHasErrors('children');

    expect(Person::find($anak->id))->not->toBeNull()
        ->and(Person::find($cucu->id))->not->toBeNull()
        ->and($anak->fresh()->father_id)->toBe($focused->id)
        ->and($cucu->fresh()->father_id)->toBe($anak->id)
        ->and($anak->fresh()->chain)->toBeNull()
        ->and($cucu->fresh()->chain)->toBeNull();
});

test('deleting a saved leaf child removes it from the family permanently', function () {
    $marga = Marga::factory()->create();
    $focused = Person::factory()->create(['name' => 'Bagot Sinta', 'marga_id' => $marga->id]);
    $child = Person::factory()->create([
        'name' => 'Tatap Raja',
        'marga_id' => $marga->id,
        'father_id' => $focused->id,
    ]);

    $this->actingAs($this->admin)
        ->put(route('people.update', $focused), [
            'name' => $focused->name,
            'marga_id' => $marga->id,
            'father' => null,
            'children' => [],
            'ownChildren' => [],
            'removed_own_child_ids' => [$child->id],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(Person::find($child->id))->toBeNull()
        ->and(Person::where('father_id', $focused->id)->whereKey($child->id)->exists())->toBeFalse();
});

test('removing a child from a family tree version detaches its tree node', function () {
    $marga = Marga::factory()->create();
    $focused = Person::factory()->create(['name' => 'Fokus Versi', 'marga_id' => $marga->id]);
    $child = Person::factory()->create([
        'name' => 'Anak Versi',
        'marga_id' => $marga->id,
        'father_id' => $focused->id,
    ]);
    $tree = FamilyTree::create([
        'user_id' => $this->admin->id,
        'root_person_id' => $focused->id,
        'name' => 'Versi Uji Hapus Anak',
    ]);
    $focusNode = FamilyTreeNode::create([
        'family_tree_id' => $tree->id,
        'person_id' => $focused->id,
    ]);
    $childNode = FamilyTreeNode::create([
        'family_tree_id' => $tree->id,
        'person_id' => $child->id,
        'father_node_id' => $focusNode->id,
    ]);

    $this->actingAs($this->admin)
        ->put(route('people.update', $focused), [
            'name' => $focused->name,
            'marga_id' => $marga->id,
            'father' => null,
            'children' => [],
            'ownChildren' => [],
            'removed_own_child_ids' => [$child->id],
            'version_tree' => $tree->id,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($childNode->fresh()->father_node_id)->toBeNull();
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

test('detaching a childless sibling clears their chain and keeps mother data', function () {
    $marga = Marga::factory()->create();
    $mother = Person::factory()->create(['name' => 'Ibu', 'gender' => 'P', 'marga_id' => $marga->id]);
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
        'mother_id' => $mother->id,
        'birth_order' => 2,
        'chain' => '9-9',
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

    expect($sibling->fresh())->not->toBeNull()
        ->and($sibling->fresh()->father_id)->toBeNull()
        ->and($sibling->fresh()->mother_id)->toBe($mother->id)
        ->and($sibling->fresh()->chain)->toBeNull();
});

test('a detach request for a person outside the edited family is rejected', function () {
    $marga = Marga::factory()->create();
    $father = Person::factory()->create(['marga_id' => $marga->id]);

    $focused = Person::factory()->create([
        'name' => 'Fokus',
        'marga_id' => $marga->id,
        'father_id' => $father->id,
        'birth_order' => 1,
    ]);

    $stranger = Person::factory()->create([
        'name' => 'Orang Lain',
        'marga_id' => $marga->id,
    ]);

    $this->actingAs($this->admin)
        ->put(route('people.update', $focused), [
            'name' => $focused->name,
            'marga_id' => $marga->id,
            'birth_order' => 1,
            'father' => ['name' => $father->name],
            'children' => [
                ['id' => $focused->id, 'name' => $focused->name, 'gender' => 'L'],
            ],
            'removed_child_ids' => [$stranger->id],
        ])
        ->assertSessionHasErrors('removed_child_ids');

    expect($stranger->fresh()->father_id)->toBeNull();
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
            ->where('familyTrees.0.name', 'Keluarga Anak Kedua')
            ->where('familyTrees.0.updated_at', '2026-08-19T10:00:00.000000Z')
            ->missing('familyTrees.2'));
});

test('the create form does not expose another accounts approved marga tree', function () {
    $marga = Marga::factory()->create(['name' => 'Simare']);
    $otherMarga = Marga::factory()->create();
    $viewer = User::factory()->withMarga($marga->id)->create();
    $owner = User::factory()->withMarga($marga->id)->create();

    $approvedRoot = Person::factory()->create(['name' => 'Akar Approved', 'marga_id' => $marga->id]);
    $approvedTree = FamilyTree::create([
        'user_id' => $owner->id,
        'root_person_id' => $approvedRoot->id,
        'name' => 'Silsilah Approved',
    ]);
    FamilyTreeNode::create(['family_tree_id' => $approvedTree->id, 'person_id' => $approvedRoot->id]);
    ContributionRequest::factory()->approved()->create([
        'requester_id' => $owner->id,
        'matched_father_id' => $approvedRoot->id,
        'subject_person_id' => $approvedRoot->id,
        'family_tree_id' => $approvedTree->id,
    ]);

    $pendingRoot = Person::factory()->create(['name' => 'Akar Pending', 'marga_id' => $marga->id]);
    $pendingTree = FamilyTree::create([
        'user_id' => $owner->id,
        'root_person_id' => $pendingRoot->id,
        'name' => 'Silsilah Pending',
    ]);
    FamilyTreeNode::create(['family_tree_id' => $pendingTree->id, 'person_id' => $pendingRoot->id]);
    ContributionRequest::factory()->create([
        'requester_id' => $owner->id,
        'matched_father_id' => $pendingRoot->id,
        'subject_person_id' => $pendingRoot->id,
        'family_tree_id' => $pendingTree->id,
    ]);

    $outsideRoot = Person::factory()->create(['marga_id' => $otherMarga->id]);
    $outsideTree = FamilyTree::create([
        'user_id' => $owner->id,
        'root_person_id' => $outsideRoot->id,
    ]);
    FamilyTreeNode::create(['family_tree_id' => $outsideTree->id, 'person_id' => $outsideRoot->id]);
    ContributionRequest::factory()->approved()->create([
        'requester_id' => $owner->id,
        'matched_father_id' => $outsideRoot->id,
        'subject_person_id' => $outsideRoot->id,
        'family_tree_id' => $outsideTree->id,
    ]);

    $this->actingAs($viewer)
        ->get(route('people.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('approvedMargaTrees', 0));

    $this->actingAs($owner)
        ->get(route('people.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('approvedMargaTrees', 0)
            ->has('familyTrees', 3));
});

test('the family history card displays the topmost father node as its root', function () {
    $marga = Marga::factory()->create(['name' => 'Silaban']);
    $user = User::factory()->withMarga($marga->id)->create();

    $this->actingAs($user)->post(route('people.store'), [
        'name' => 'Jaya Silaban',
        'gender' => 'L',
        'marga_id' => $marga->id,
        'birth_order' => 1,
        'sibling_count' => 1,
        'father' => ['name' => 'Ayah Jaya Silaban'],
        'children' => [['name' => 'Jaya Silaban', 'gender' => 'L']],
    ])->assertRedirect(route('people.index'));

    $focus = Person::query()->where('name', 'Jaya Silaban')->firstOrFail();

    $this->actingAs($user)
        ->get(route('people.show', $focus))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('people/show')
            ->where('familyTrees.0.root_name', 'Ayah Jaya Silaban'));
});

test('the admin family tree card lists all trees while version actions stay focused', function () {
    $admin = User::factory()->asAdmin()->create();
    $user = User::factory()->create();
    $focus = Person::factory()->create(['name' => 'Fokus Admin']);
    $otherRoot = Person::factory()->create(['name' => 'Akar Lain']);

    $adminTree = FamilyTree::create([
        'user_id' => $admin->id,
        'root_person_id' => $focus->id,
        'name' => 'Versi Admin',
    ]);
    $userTree = FamilyTree::create([
        'user_id' => $user->id,
        'root_person_id' => $focus->id,
        'name' => 'Versi User',
    ]);
    $otherTree = FamilyTree::create([
        'user_id' => $user->id,
        'root_person_id' => $otherRoot->id,
        'name' => 'Pohon Lain',
    ]);
    $adminTree->nodes()->create(['person_id' => $focus->id]);
    $userTree->nodes()->create(['person_id' => $focus->id]);
    $otherTree->nodes()->create(['person_id' => $otherRoot->id]);

    $this->actingAs($admin)
        ->get(route('people.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('familyTrees', 3));

    $this->actingAs($admin)
        ->get(route('people.edit', $focus))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('familyTrees', 3)
            ->has('versionTrees', 2)
            ->where('versionTrees', fn ($trees) => collect($trees)
                ->every(fn (array $tree) => $tree['root_person_id'] === $focus->id
                    && $tree['root_name'] === 'Fokus Admin')));
});

test('family tree entries expose every member for alternative version actions', function () {
    $user = User::factory()->asSubAdmin()->create();
    $root = Person::factory()->create(['name' => 'Akar Pohon']);
    $member = Person::factory()->create(['name' => 'Anggota Pohon']);
    $tree = FamilyTree::create([
        'user_id' => $user->id,
        'root_person_id' => $root->id,
        'name' => 'Pohon Keluarga',
    ]);

    $rootNode = $tree->nodes()->create(['person_id' => $root->id]);
    $tree->nodes()->create([
        'person_id' => $member->id,
        'father_node_id' => $rootNode->id,
    ]);

    $this->actingAs($user)
        ->get(route('people.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('familyTrees.0.id', $tree->id)
            ->where('familyTrees.0.member_person_ids', fn ($ids) => collect($ids)
                ->contains($root->id) && collect($ids)->contains($member->id)));
});

test('the sub-admin family tree card only lists trees owned by that account', function () {
    $subAdmin = User::factory()->asSubAdmin()->create();
    $otherUser = User::factory()->create();
    $ownRoot = Person::factory()->create();
    $otherRoot = Person::factory()->create();
    $ownTree = FamilyTree::create([
        'user_id' => $subAdmin->id,
        'root_person_id' => $ownRoot->id,
    ]);
    FamilyTree::create([
        'user_id' => $otherUser->id,
        'root_person_id' => $otherRoot->id,
    ]);

    $this->actingAs($subAdmin)
        ->get(route('people.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('familyTrees', 1)
            ->where('familyTrees.0.id', $ownTree->id));
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
            ->where('person.name', 'Anak'));
});

test('the create form suggests only male people as fathers', function () {
    Person::factory()->create(['name' => 'Calon Ayah', 'gender' => 'L']);
    Person::factory()->create(['name' => 'Perempuan', 'gender' => 'P']);
    Person::factory()->create(['name' => 'Belum Diketahui', 'gender' => null]);

    $this->actingAs($this->admin)
        ->get(route('people.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('fatherSuggestions', fn ($suggestions) => collect($suggestions)
                ->pluck('name')->all() === ['Calon Ayah']));
});

test('name suggestions keep duplicate names distinguishable by their father', function () {
    $firstFather = Person::factory()->create(['name' => 'Bapak Pertama', 'gender' => 'L']);
    $secondFather = Person::factory()->create(['name' => 'Bapak Kedua', 'gender' => 'L']);
    $first = Person::factory()->create(['name' => 'Ampunalampak', 'father_id' => $firstFather->id]);
    $second = Person::factory()->create(['name' => 'Ampunalampak', 'father_id' => $secondFather->id]);

    $this->actingAs($this->admin)
        ->get(route('people.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('nameSuggestions', fn ($suggestions) => collect($suggestions)
                ->where('name', 'Ampunalampak')
                ->values()
                ->contains(fn (array $row) => $row['id'] === $first->id && $row['father_name'] === 'Bapak Pertama')
                && collect($suggestions)
                    ->where('name', 'Ampunalampak')
                    ->values()
                    ->contains(fn (array $row) => $row['id'] === $second->id && $row['father_name'] === 'Bapak Kedua')));
});

test('a case-insensitive existing father is reused instead of duplicated', function () {
    $marga = Marga::factory()->create();
    $father = Person::factory()->create([
        'name' => 'Ompu Sitorus',
        'gender' => 'L',
        'marga_id' => $marga->id,
    ]);

    $this->actingAs($this->admin)
        ->post(route('people.store'), [
            'name' => 'Anak Sitorus',
            'gender' => 'L',
            'marga_id' => $marga->id,
            'birth_order' => 1,
            'sibling_count' => 1,
            'father' => [
                'name' => '  ompu  sitorus  ',
                'marga_id' => $marga->id,
            ],
            'children' => [[
                'name' => 'Anak Sitorus',
                'gender' => 'L',
                'marga_id' => $marga->id,
            ]],
        ])
        ->assertRedirect(route('people.index'));

    expect(Person::query()->where('name', 'Ompu Sitorus')->count())->toBe(1)
        ->and(Person::query()->where('name', 'Anak Sitorus')->firstOrFail()->father_id)
        ->toBe($father->id);
});

test('an ambiguous father name blocks saving instead of creating a duplicate', function () {
    $marga = Marga::factory()->create();
    Person::factory()->count(2)->create([
        'name' => 'Ompu Sitorus',
        'gender' => 'L',
        'marga_id' => $marga->id,
    ]);

    $this->actingAs($this->admin)
        ->from(route('people.create'))
        ->post(route('people.store'), [
            'name' => 'Anak Sitorus',
            'gender' => 'L',
            'marga_id' => $marga->id,
            'birth_order' => 1,
            'sibling_count' => 1,
            'father' => [
                'name' => 'Ompu Sitorus',
                'marga_id' => $marga->id,
            ],
            'children' => [[
                'name' => 'Anak Sitorus',
                'gender' => 'L',
                'marga_id' => $marga->id,
            ]],
        ])
        ->assertRedirect(route('people.create'))
        ->assertSessionHasErrors('father.name');

    expect(Person::query()->where('name', 'Anak Sitorus')->exists())->toBeFalse();
});

test('an existing unlinked person can be selected as a child without creating a duplicate', function () {
    $marga = Marga::factory()->create();
    $grandfather = Person::factory()->create(['name' => 'Ompung', 'gender' => 'L', 'marga_id' => $marga->id]);
    $focus = Person::factory()->create([
        'name' => 'Bapak Tujuan',
        'gender' => 'L',
        'marga_id' => $marga->id,
        'father_id' => $grandfather->id,
        'birth_order' => 1,
    ]);
    $existing = Person::factory()->create([
        'name' => 'Ampunalampak',
        'gender' => 'L',
        'marga_id' => $marga->id,
        'father_id' => null,
    ]);

    $this->actingAs($this->admin)->put(route('people.update', $focus), [
        'name' => $focus->name,
        'gender' => 'L',
        'marga_id' => $marga->id,
        'birth_order' => 1,
        'sibling_count' => 1,
        'father' => ['name' => $grandfather->name, 'marga_id' => $marga->id],
        'children' => [['id' => $focus->id, 'name' => $focus->name, 'gender' => 'L']],
        'ownChildren' => [['id' => $existing->id, 'name' => $existing->name, 'gender' => 'L']],
    ])->assertRedirect();

    expect(Person::query()->where('name', 'Ampunalampak')->count())->toBe(1)
        ->and($existing->fresh()->father_id)->toBe($focus->id);
});

test('selecting an existing child from another father never moves its lineage automatically', function () {
    $marga = Marga::factory()->create();
    $firstFather = Person::factory()->create(['name' => 'Bapak Pertama', 'gender' => 'L', 'marga_id' => $marga->id]);
    $secondFather = Person::factory()->create(['name' => 'Bapak Kedua', 'gender' => 'L', 'marga_id' => $marga->id]);
    $existing = Person::factory()->create([
        'name' => 'Ampunalampak',
        'gender' => 'L',
        'marga_id' => $marga->id,
        'father_id' => $firstFather->id,
    ]);

    $this->actingAs($this->admin)
        ->from(route('people.edit', $secondFather))
        ->put(route('people.update', $secondFather), [
            'name' => $secondFather->name,
            'gender' => 'L',
            'marga_id' => $marga->id,
            'birth_order' => 1,
            'sibling_count' => 1,
            'children' => [['id' => $secondFather->id, 'name' => $secondFather->name, 'gender' => 'L']],
            'ownChildren' => [['id' => $existing->id, 'name' => $existing->name, 'gender' => 'L']],
        ])
        ->assertRedirect(route('people.edit', $secondFather))
        ->assertSessionHasErrors('ownChildren.0.id');

    expect($existing->fresh()->father_id)->toBe($firstFather->id)
        ->and(Person::query()->where('name', 'Ampunalampak')->count())->toBe(1);
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
                ->where('fatherSuggestions', fn ($suggestions) => collect($suggestions)
                    ->pluck('name')->all() === ['Kandidat Ayah']));
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

test('a person photo can be uploaded and stored on the public disk', function () {
    Storage::fake('public');
    $marga = Marga::factory()->create();

    $response = $this->actingAs($this->admin)->post(route('people.store'), [
        'name' => 'Fokus Foto',
        'gender' => 'L',
        'marga_id' => $marga->id,
        'birth_order' => 1,
        'sibling_count' => 1,
        'image_mode' => 'upload',
        'image_file' => UploadedFile::fake()->image('foto.jpg', 400, 400),
        'father' => ['name' => 'Ayah Foto'],
        'children' => [[
            'name' => 'Fokus Foto',
            'gender' => 'L',
            'marga_id' => $marga->id,
        ]],
    ]);

    $response->assertRedirect(route('people.index'));

    $person = Person::where('name', 'Fokus Foto')->firstOrFail();

    expect($person->image)->toStartWith('/storage/people/');
    Storage::disk('public')->assertExists(substr($person->image, strlen('/storage/')));
});

test('a person photo upload rejects non image files', function () {
    $marga = Marga::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('people.store'), [
            'name' => 'Fokus Foto',
            'gender' => 'L',
            'marga_id' => $marga->id,
            'birth_order' => 1,
            'sibling_count' => 1,
            'image_mode' => 'upload',
            'image_file' => UploadedFile::fake()->create(
                'dokumen.pdf',
                100,
                'application/pdf',
            ),
            'father' => ['name' => 'Ayah Foto'],
            'children' => [[
                'name' => 'Fokus Foto',
                'gender' => 'L',
                'marga_id' => $marga->id,
            ]],
        ])
        ->assertSessionHasErrors('image_file');
});
