<?php

use App\Models\FamilyTree;
use App\Models\Person;
use App\Models\User;
use Database\Seeders\WikipediaTaromboSeeder;

test('the Wikipedia tarombo seeder creates primary and alternative family trees with independent nodes', function () {
    $admin = User::factory()->asAdmin()->create();

    $this->seed(WikipediaTaromboSeeder::class);

    $tree = FamilyTree::query()->where('name', 'Wikipedia Tarombo')->firstOrFail();
    $alternative = FamilyTree::query()
        ->where('name', 'Wikipedia Tarombo - Versi alternatif')
        ->firstOrFail();
    $child = Person::query()->whereNotNull('father_id')->firstOrFail();
    $childNode = $tree->nodes()->where('person_id', $child->id)->firstOrFail();
    $alternativeChildNode = $alternative->nodes()->where('person_id', $child->id)->firstOrFail();

    expect($tree->user_id)->toBe($admin->id)
        ->and($tree->is_primary)->toBeTrue()
        ->and($tree->nodes()->count())->toBe(Person::query()->count())
        ->and($childNode->fatherNode?->person_id)->toBe($child->father_id)
        ->and($alternative->user_id)->toBe($admin->id)
        ->and($alternative->is_primary)->toBeFalse()
        ->and($alternative->based_on_id)->toBe($tree->id)
        ->and($alternative->nodes()->count())->toBe($tree->nodes()->count())
        ->and($alternativeChildNode->id)->not->toBe($childNode->id)
        ->and($alternativeChildNode->fatherNode?->person_id)->toBe($child->father_id);
});
