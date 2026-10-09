<?php

use App\Http\Controllers\PersonController;
use App\Models\FamilyTree;
use App\Models\FamilyTreeNode;
use App\Models\Marga;
use App\Models\Person;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

test('loading family relations and edit permissions does not query each extra child', function (bool $staff) {
    $marga = Marga::factory()->create();
    $user = $staff ? User::factory()->asAdmin()->create() : User::factory()->withMarga($marga->id)->create();
    $father = Person::factory()->create(['marga_id' => $marga->id, 'gender' => 'L', 'created_by' => $user->id]);
    $mother = Person::factory()->create(['marga_id' => $marga->id, 'gender' => 'P']);
    $father->wives()->attach($mother->id, ['position' => 1]);
    $tree = FamilyTree::create(['user_id' => $user->id, 'root_person_id' => $father->id]);
    $tree->people()->attach($father->id);
    FamilyTreeNode::create(['family_tree_id' => $tree->id, 'person_id' => $father->id]);
    $makeChildren = fn (int $count) => Person::factory()->count($count)->create([
        'marga_id' => $marga->id, 'gender' => 'L', 'father_id' => $father->id, 'mother_id' => $mother->id,
    ]);
    $makeChildren(2);

    $measure = function () use ($father, $user): array {
        auth()->setUser($user->fresh());
        $controller = new PersonController;
        $focus = $father->fresh();
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $payload = (new ReflectionMethod($controller, 'familyPayload'))->invoke($controller, $focus);
            $queries = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
        }

        return ['payload' => $payload, 'queries' => $queries];
    };
    $small = $measure();
    $makeChildren(20);
    $large = $measure();

    expect(count($large['queries']))->toBeLessThanOrEqual(count($small['queries']) + 2)
        ->and($large['payload']['ownChildren'])->toHaveCount(22)
        ->and(collect($large['payload']['ownChildren'])->pluck('marga')->unique()->all())->toBe([$marga->name])
        ->and($large['payload']['wives'][0]['id'])->toBe($mother->id)
        ->and($large['payload']['lineage'][0]['editable'])->toBeTrue();
})->with(['staff' => true, 'regular owner' => false]);

test('a deep lineage is loaded in one query in ancestor order', function () {
    $marga = Marga::factory()->create();
    $ancestors = collect();
    $father = null;

    for ($generation = 0; $generation < 20; $generation++) {
        $father = Person::factory()->create([
            'marga_id' => $marga->id,
            'father_id' => $father?->id,
            'gender' => 'L',
        ]);
        $ancestors->push($father->id);
    }

    $focus = Person::factory()->create(['marga_id' => $marga->id, 'father_id' => $father->id]);
    DB::enableQueryLog();
    DB::flushQueryLog();
    try {
        $lineage = $focus->lineage();
        $queries = DB::getQueryLog();
    } finally {
        DB::disableQueryLog();
    }

    expect($lineage->pluck('id')->all())->toBe($ancestors->all())
        ->and($queries)->toHaveCount(1);
});

test('father exclusions include deep descendants and siblings with bounded queries', function () {
    $marga = Marga::factory()->create();
    $father = Person::factory()->create(['marga_id' => $marga->id]);
    $focus = Person::factory()->create(['marga_id' => $marga->id, 'father_id' => $father->id]);
    $sibling = Person::factory()->create(['marga_id' => $marga->id, 'father_id' => $father->id]);
    $excluded = [$focus->id, $sibling->id];
    $parent = $focus;

    for ($generation = 0; $generation < 20; $generation++) {
        $parent = Person::factory()->create(['marga_id' => $marga->id, 'father_id' => $parent->id]);
        $excluded[] = $parent->id;
    }

    DB::enableQueryLog();
    DB::flushQueryLog();
    try {
        $ids = $focus->ineligibleFatherIds();
        $queries = DB::getQueryLog();
    } finally {
        DB::disableQueryLog();
    }

    expect($ids)->toEqualCanonicalizing($excluded)
        ->and($queries)->toHaveCount(2);
});

test('recursive family queries terminate even with a legacy parent cycle', function () {
    $marga = Marga::factory()->create();
    $father = Person::factory()->create(['marga_id' => $marga->id]);
    $child = Person::factory()->create(['marga_id' => $marga->id, 'father_id' => $father->id]);
    DB::table('people')->where('id', $father->id)->update(['father_id' => $child->id]);

    expect($child->lineage()->pluck('id')->all())->toEqualCanonicalizing([$father->id, $child->id])
        ->and($child->ineligibleFatherIds())->toEqualCanonicalizing([$father->id, $child->id]);
});

test('edit loads family tree history once and preserves selectable versions', function () {
    $user = User::factory()->asAdmin()->create();
    $marga = Marga::factory()->create();
    $person = Person::factory()->create(['marga_id' => $marga->id]);
    $tree = FamilyTree::create(['user_id' => $user->id, 'root_person_id' => $person->id]);
    FamilyTreeNode::create(['family_tree_id' => $tree->id, 'person_id' => $person->id]);
    $this->actingAs($user);

    DB::enableQueryLog();
    DB::flushQueryLog();
    try {
        $response = $this->get(route('people.edit', $person));
        $queries = DB::getQueryLog();
    } finally {
        DB::disableQueryLog();
    }

    $response->assertSuccessful()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('people/form')
        ->has('familyTrees', 1)
        ->has('versionTrees', 1)
        ->where('familyTrees.0.id', $tree->id)
        ->where('versionTrees.0.id', $tree->id));
    expect(collect($queries)->filter(fn (array $query) => str_contains($query['query'], 'has_approved_marga_node')))->toHaveCount(1);
});

test('descendant summaries use two queries regardless of depth and reuse the loaded graph', function () {
    $marga = Marga::factory()->create();
    $root = Person::factory()->create(['marga_id' => $marga->id, 'father_id' => null]);
    $parent = $root;
    $names = [];
    $firstChild = null;

    for ($generation = 1; $generation <= 60; $generation++) {
        $parent = Person::factory()->create([
            'marga_id' => $marga->id,
            'father_id' => $parent->id,
            'name' => "Generation {$generation}",
        ]);
        $firstChild ??= $parent;
        $names[] = $parent->name;
    }

    $controller = new PersonController;
    $method = new ReflectionMethod($controller, 'descendantMap');
    DB::enableQueryLog();
    DB::flushQueryLog();
    try {
        $summary = $method->invoke($controller, [$root->id]);
        $queries = DB::getQueryLog();
        DB::flushQueryLog();
        $childSummary = $method->invoke($controller, [$firstChild->id]);
        $cachedQueries = DB::getQueryLog();
    } finally {
        DB::disableQueryLog();
    }

    expect($queries)->toHaveCount(2)
        ->and($summary[$root->id])->toBe(['count' => 60, 'names' => array_slice($names, 0, 50)])
        ->and($cachedQueries)->toHaveCount(1)
        ->and($childSummary[$firstChild->id]['count'])->toBe(59);
});

test('father suggestions retain parent context and chains without hydrating relations', function () {
    $marga = Marga::factory()->create();
    $otherMarga = Marga::factory()->create();
    $grandfather = Person::factory()->create(['marga_id' => $marga->id, 'gender' => 'L']);
    $candidate = Person::factory()->create([
        'marga_id' => $marga->id, 'gender' => 'L', 'father_id' => $grandfather->id, 'chain' => '1-2',
    ]);
    $otherCandidate = Person::factory()->create(['marga_id' => $otherMarga->id, 'gender' => 'L']);
    $female = Person::factory()->create(['marga_id' => $marga->id, 'gender' => 'P']);

    DB::enableQueryLog();
    DB::flushQueryLog();
    try {
        $suggestions = (new ReflectionMethod(PersonController::class, 'fatherSuggestions'))
            ->invoke(new PersonController, null, $marga->id);
        $queries = DB::getQueryLog();
    } finally {
        DB::disableQueryLog();
    }

    $row = collect($suggestions)->firstWhere('id', $candidate->id);
    expect($queries)->toHaveCount(1)
        ->and($row['father_name'])->toBe($grandfather->name)
        ->and($row['marga'])->toBe($marga->name)
        ->and($row['chain'])->toBe('1-2')
        ->and(array_column($suggestions, 'id'))->not->toContain($otherCandidate->id, $female->id);
});
