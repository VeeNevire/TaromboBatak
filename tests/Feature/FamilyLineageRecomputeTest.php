<?php

use App\Jobs\RecomputeFamilyLineage;
use App\Models\Marga;
use App\Models\Person;
use App\Models\User;
use App\Services\ChainNumberingService;
use App\Services\FamilyEntryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

/**
 * Root "1" with son A ("1-1") holding a large branch and son B ("1-2").
 *
 * @return array{root: Person, a: Person, b: Person, marga: Marga}
 */
function largeLineage(int $grandchildren = 20, int $greatGrandchildren = 10): array
{
    $marga = Marga::factory()->create(['name' => 'Sitorus']);
    $make = fn (array $attributes) => Person::factory()->create([
        'gender' => 'L',
        'marga_id' => $marga->id,
        'pending_father' => false,
        ...$attributes,
    ]);

    $root = $make(['name' => 'Root', 'father_id' => null, 'chain' => '1']);
    $a = $make(['name' => 'A', 'father_id' => $root->id, 'birth_order' => 1, 'chain' => '1-1']);
    $b = $make(['name' => 'B', 'father_id' => $root->id, 'birth_order' => 2, 'chain' => '1-2']);

    for ($i = 1; $i <= $grandchildren; $i++) {
        $grandchild = $make(['name' => "A{$i}", 'father_id' => $a->id, 'birth_order' => $i, 'chain' => "1-1-{$i}"]);

        for ($j = 1; $j <= $greatGrandchildren; $j++) {
            $make(['name' => "A{$i}-{$j}", 'father_id' => $grandchild->id, 'birth_order' => $j, 'chain' => "1-1-{$i}-{$j}"]);
        }
    }

    return compact('root', 'a', 'b', 'marga');
}

test('adding a child queues the lineage renumbering and keeps the request small', function () {
    Queue::fake();
    ['b' => $b, 'marga' => $marga] = largeLineage();
    $admin = User::factory()->asAdmin()->create();

    DB::enableQueryLog();

    $this->actingAs($admin)->post(route('people.store'), [
        'name' => 'Anak B',
        'gender' => 'L',
        'marga_id' => $marga->id,
        'birth_order' => 1,
        'sibling_count' => 1,
        'father_id' => $b->id,
        'father' => ['id' => $b->id, 'name' => 'B'],
        'children' => [['name' => 'Anak B', 'gender' => 'L']],
    ])->assertRedirect(route('people.index'))
        ->assertSessionHasNoErrors();

    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect(Person::where('name', 'Anak B')->firstOrFail()->chain)->toBe('1-2-1')
        ->and($queries)->toBeLessThan(250);

    Queue::assertPushed(RecomputeFamilyLineage::class, fn (RecomputeFamilyLineage $job) => $job->branchPersonIds === [$b->id]);
});

test('the queued renumbering derives the whole branch from its father', function () {
    ['root' => $root, 'a' => $a] = largeLineage(3, 2);

    // Stale chains everywhere below A, a pending child and an unordered child.
    Person::where('chain', 'like', '1-1-%')->update(['chain' => 'stale']);
    $pending = Person::factory()->create(['father_id' => $a->id, 'birth_order' => 4, 'pending_father' => true, 'chain' => '1-1-4']);
    $unordered = Person::factory()->create(['father_id' => $a->id, 'birth_order' => null, 'pending_father' => false, 'chain' => 'kept']);
    $underUnordered = Person::factory()->create(['father_id' => $unordered->id, 'birth_order' => 1, 'pending_father' => false]);

    (new RecomputeFamilyLineage(branchPersonIds: [$a->id]))->handle(
        app(ChainNumberingService::class),
        app(FamilyEntryService::class),
    );

    expect(Person::where('name', 'A2')->value('chain'))->toBe('1-1-2')
        ->and(Person::where('name', 'A3-2')->value('chain'))->toBe('1-1-3-2')
        ->and($pending->fresh()->chain)->toBeNull()
        ->and($unordered->fresh()->chain)->toBe('kept')
        ->and($underUnordered->fresh()->chain)->toBe('kept-1')
        ->and($root->fresh()->chain)->toBe('1');
});

test('recomputing from the ancestor matches the per-person chain rules', function () {
    ['root' => $root] = largeLineage(2, 2);
    Person::where('id', '!=', $root->id)->update(['chain' => null]);

    app(ChainNumberingService::class)->recomputeFromAncestor(Person::where('name', 'A2-1')->firstOrFail());

    expect(Person::where('name', 'B')->value('chain'))->toBe('1-2')
        ->and(Person::where('name', 'A1-2')->value('chain'))->toBe('1-1-1-2');
});
