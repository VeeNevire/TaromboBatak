<?php

use App\Models\Marga;
use App\Models\Person;
use App\Models\User;
use App\Services\ChainNumberingService;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->asAdmin()->create();
    $this->marga = Marga::factory()->create(['name' => 'PendingMarga']);
});

function pendingStore(array $payload): void
{
    test()->actingAs(test()->admin)
        ->post(route('people.store'), [...$payload, 'marga_id' => test()->marga->id])
        ->assertRedirect(route('people.index'));
}

test('a family without a father stays its own pending root and does not join the main lineage', function () {
    pendingStore([
        'name' => 'Anak Asuh',
        'birth_order' => 1,
        'sibling_count' => 1,
        'father' => ['name' => 'Si Raja Batak'],
        'children' => [['name' => 'Anak Asuh', 'gender' => 'L']],
    ]);

    pendingStore([
        'name' => 'Yatim',
        'birth_order' => 1,
        'sibling_count' => 2,
        'children' => [
            ['name' => 'Yatim', 'gender' => 'L'],
            ['name' => 'Yatim Dua', 'gender' => 'L'],
        ],
    ]);

    $root = Person::where('name', 'Si Raja Batak')->first();
    $yatim = Person::where('name', 'Yatim')->first();
    $yatimDua = Person::where('name', 'Yatim Dua')->first();

    expect($root->chain)->toBe('1')
        ->and($yatim->father_id)->toBeNull()
        ->and($yatimDua->father_id)->toBeNull()
        ->and($yatim->pending_father)->toBeTrue()
        ->and($yatimDua->pending_father)->toBeTrue()
        ->and($yatim->chain)->toBeNull()
        ->and($yatimDua->chain)->toBeNull()
        ->and(Person::where('father_id', $root->id)->count())->toBe(1);
});

test('a pending family with descendants gets a reserved pending label, not a new root number', function () {
    pendingStore([
        'name' => 'Yatim',
        'birth_order' => 1,
        'sibling_count' => 1,
        'children' => [['name' => 'Yatim', 'gender' => 'L']],
    ]);

    pendingStore([
        'name' => 'Yatim Anak',
        'birth_order' => 1,
        'sibling_count' => 1,
        'father' => ['name' => 'Yatim'],
        'children' => [['name' => 'Yatim Anak', 'gender' => 'L']],
    ]);

    expect(Person::where('name', 'Yatim')->first()->chain)->toBe('-1')
        ->and(Person::where('name', 'Yatim Anak')->first()->chain)->toBe('-1-1');
});

test('each pending family gets its own reserved label without taking flat root numbers', function () {
    pendingStore([
        'name' => 'Anak Satu',
        'birth_order' => 1,
        'sibling_count' => 1,
        'father' => ['name' => 'Si Raja Batak'],
        'children' => [['name' => 'Anak Satu', 'gender' => 'L']],
    ]);

    foreach (['Yatim Satu', 'Yatim Dua'] as $name) {
        pendingStore([
            'name' => $name,
            'birth_order' => 1,
            'sibling_count' => 1,
            'children' => [['name' => $name, 'gender' => 'L']],
        ]);
    }

    pendingStore([
        'name' => 'Yatim Satu Anak',
        'birth_order' => 1,
        'sibling_count' => 1,
        'father' => ['name' => 'Yatim Satu'],
        'children' => [['name' => 'Yatim Satu Anak', 'gender' => 'L']],
    ]);

    pendingStore([
        'name' => 'Yatim Dua Anak',
        'birth_order' => 1,
        'sibling_count' => 1,
        'father' => ['name' => 'Yatim Dua'],
        'children' => [['name' => 'Yatim Dua Anak', 'gender' => 'L']],
    ]);

    expect(Person::where('name', 'Si Raja Batak')->first()->chain)->toBe('1')
        ->and(Person::where('name', 'Yatim Satu')->first()->chain)->toBe('-1')
        ->and(Person::where('name', 'Yatim Satu Anak')->first()->chain)->toBe('-1-1')
        ->and(Person::where('name', 'Yatim Dua')->first()->chain)->toBe('-2')
        ->and(Person::where('name', 'Yatim Dua Anak')->first()->chain)->toBe('-2-1');
});

test('recomputing a pending root replaces stale chains with a pending label', function () {
    $service = app(ChainNumberingService::class);

    $yatim = Person::factory()->create([
        'name' => 'Yatim',
        'father_id' => null,
        'pending_father' => true,
        'chain' => '2',
    ]);
    Person::factory()->create([
        'name' => 'Yatim Anak',
        'father_id' => $yatim->id,
        'birth_order' => 1,
        'chain' => '2-1',
    ]);

    $service->recomputeFromAncestor($yatim);

    expect($yatim->fresh()->chain)->toBe('-1')
        ->and(Person::where('name', 'Yatim Anak')->first()->chain)->toBe('-1-1');
});

test('filling in an existing father connects a pending family and its descendants follow', function () {
    pendingStore([
        'name' => 'Anak Satu',
        'birth_order' => 1,
        'sibling_count' => 1,
        'father' => ['name' => 'Si Raja Batak'],
        'children' => [['name' => 'Anak Satu', 'gender' => 'L']],
    ]);

    pendingStore([
        'name' => 'Yatim',
        'birth_order' => 1,
        'sibling_count' => 1,
        'children' => [['name' => 'Yatim', 'gender' => 'L']],
    ]);

    pendingStore([
        'name' => 'Yatim Anak',
        'birth_order' => 1,
        'sibling_count' => 1,
        'father' => ['name' => 'Yatim'],
        'children' => [['name' => 'Yatim Anak', 'gender' => 'L']],
    ]);

    $fokus = Person::where('name', 'Yatim')->first();

    $this->actingAs($this->admin)->put(route('people.update', $fokus), [
        'name' => $fokus->name,
        'marga_id' => $this->marga->id,
        'birth_order' => 1,
        'sibling_count' => 1,
        'father' => ['name' => 'Anak Satu'],
        'children' => [
            ['id' => $fokus->id, 'name' => $fokus->name, 'gender' => 'L'],
        ],
    ])->assertRedirect();

    $anakSatu = Person::where('name', 'Anak Satu')->first();
    $yatim = Person::where('name', 'Yatim')->first();
    $yatimAnak = Person::where('name', 'Yatim Anak')->first();

    expect($yatim->fresh()->chain)->toBe('1-1-1')
        ->and($yatim->fresh()->father_id)->toBe($anakSatu->id)
        ->and($yatim->fresh()->pending_father)->toBeFalse()
        ->and($yatimAnak->fresh()->chain)->toBe('1-1-1-1');
});

test('the jejak keluarga of a pending person shows only their own family', function () {
    pendingStore([
        'name' => 'Anak Satu',
        'birth_order' => 1,
        'sibling_count' => 1,
        'father' => ['name' => 'Si Raja Batak'],
        'children' => [['name' => 'Anak Satu', 'gender' => 'L']],
    ]);

    pendingStore([
        'name' => 'Yatim',
        'birth_order' => 1,
        'sibling_count' => 1,
        'children' => [['name' => 'Yatim', 'gender' => 'L']],
    ]);

    $root = Person::where('name', 'Si Raja Batak')->first();
    $yatim = Person::where('name', 'Yatim')->first();
    $yatim->update(['father_id' => $root->id]);

    $this->actingAs($this->admin)
        ->get(route('people.show', $yatim))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('people/show')
            ->where('person.pending', true)
            ->where('person.father', null)
            ->where('person.father_id', null)
            ->has('person.children', 1)
            ->where('person.children.0.name', 'Yatim')
            ->has('person.lineage', 1)
            ->where('person.lineage.0.is_self', true));
});

test('the jejak keluarga of a real sibling group excludes pending entries', function () {
    pendingStore([
        'name' => 'Anak Asli',
        'birth_order' => 1,
        'sibling_count' => 2,
        'father' => ['name' => 'Si Raja Batak'],
        'children' => [
            ['name' => 'Anak Asli', 'gender' => 'L'],
            ['name' => 'Anak Dua', 'gender' => 'L'],
        ],
    ]);

    pendingStore([
        'name' => 'Yatim',
        'birth_order' => 1,
        'sibling_count' => 1,
        'children' => [['name' => 'Yatim', 'gender' => 'L']],
    ]);

    $root = Person::where('name', 'Si Raja Batak')->first();
    Person::where('name', 'Yatim')->first()->update(['father_id' => $root->id]);

    $asli = Person::where('name', 'Anak Asli')->first();

    $this->actingAs($this->admin)
        ->get(route('people.show', $asli))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('people/show')
            ->has('person.children', 2)
            ->where('person.children.0.name', 'Anak Asli')
            ->where('person.children.1.name', 'Anak Dua'));
});

test('the unlink command detaches glued pending families from their temporary root', function () {
    pendingStore([
        'name' => 'Anak Satu',
        'birth_order' => 1,
        'sibling_count' => 1,
        'father' => ['name' => 'Si Raja Batak'],
        'children' => [['name' => 'Anak Satu', 'gender' => 'L']],
    ]);

    pendingStore([
        'name' => 'Yatim',
        'birth_order' => 1,
        'sibling_count' => 1,
        'children' => [['name' => 'Yatim', 'gender' => 'L']],
    ]);

    $root = Person::where('name', 'Si Raja Batak')->first();
    Person::where('name', 'Yatim')->first()->update(['father_id' => $root->id]);

    $this->artisan('people:unlink-pending-fathers')->assertSuccessful();

    expect(Person::where('name', 'Yatim')->first()->father_id)->toBeNull();
});
