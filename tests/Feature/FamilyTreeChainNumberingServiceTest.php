<?php

use App\Models\FamilyTree;
use App\Models\FamilyTreeNode;
use App\Models\Person;
use App\Models\User;
use App\Services\FamilyTreeChainNumberingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

test('numbering a wide tree batches changed chains and skips unchanged labels', function () {
    $tree = FamilyTree::create(['user_id' => User::factory()->create()->id, 'root_person_id' => Person::factory()->create()->id]);
    $root = FamilyTreeNode::create(['family_tree_id' => $tree->id, 'person_id' => $tree->root_person_id]);
    foreach (Person::factory()->count(250)->create(['marga_id' => null]) as $index => $person) {
        FamilyTreeNode::create(['family_tree_id' => $tree->id, 'person_id' => $person->id, 'father_node_id' => $root->id, 'birth_order' => $index + 1]);
    }

    DB::enableQueryLog();
    DB::flushQueryLog();
    app(FamilyTreeChainNumberingService::class)->recompute($tree);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect(count($queries))->toBeLessThan(8)
        ->and($root->fresh()->chain)->toBe('1')
        ->and($tree->nodes()->where('birth_order', 250)->value('chain'))->toBe('1-250');

    DB::enableQueryLog();
    DB::flushQueryLog();
    app(FamilyTreeChainNumberingService::class)->recompute($tree);
    $writes = collect(DB::getQueryLog())->filter(fn ($query) => preg_match('/^(insert|update)/i', $query['query']));
    DB::disableQueryLog();
    expect($writes)->toBeEmpty();
});

test('numbering rejects a disconnected cycle before writing any chains', function () {
    $tree = FamilyTree::create(['user_id' => User::factory()->create()->id, 'root_person_id' => Person::factory()->create()->id]);
    $root = FamilyTreeNode::create(['family_tree_id' => $tree->id, 'person_id' => $tree->root_person_id, 'chain' => '9']);
    $a = FamilyTreeNode::create(['family_tree_id' => $tree->id, 'person_id' => Person::factory()->create()->id]);
    $b = FamilyTreeNode::create(['family_tree_id' => $tree->id, 'person_id' => Person::factory()->create()->id, 'father_node_id' => $a->id]);
    $a->update(['father_node_id' => $b->id]);

    expect(fn () => app(FamilyTreeChainNumberingService::class)->recompute($tree))->toThrow(ValidationException::class);
    expect($root->fresh()->chain)->toBe('9');
});
