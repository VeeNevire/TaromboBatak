<?php

use App\Http\Controllers\PersonController;
use App\Models\FamilyTree;
use App\Models\FamilyTreeNode;
use App\Models\Marga;
use App\Models\Person;
use App\Models\User;
use Illuminate\Support\Facades\DB;

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
