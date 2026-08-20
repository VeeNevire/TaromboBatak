<?php

use App\Models\FamilyTree;
use App\Models\Person;
use App\Models\User;
use Database\Seeders\WikipediaTaromboSeeder;

test('the Wikipedia tarombo seeder creates a primary family tree with contextual nodes', function () {
    $admin = User::factory()->asAdmin()->create();

    $this->seed(WikipediaTaromboSeeder::class);

    $tree = FamilyTree::query()->where('name', 'Wikipedia Tarombo')->firstOrFail();
    $child = Person::query()->whereNotNull('father_id')->firstOrFail();
    $childNode = $tree->nodes()->where('person_id', $child->id)->firstOrFail();

    expect($tree->user_id)->toBe($admin->id)
        ->and($tree->is_primary)->toBeTrue()
        ->and($tree->nodes()->count())->toBe(Person::query()->count())
        ->and($childNode->fatherNode?->person_id)->toBe($child->father_id);
});
