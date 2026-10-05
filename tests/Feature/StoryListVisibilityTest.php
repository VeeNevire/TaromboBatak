<?php

use App\Models\Marga;
use App\Models\Story;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('regular users can read public stories and their own drafts in the story menu', function () {
    $user = User::factory()->create();
    Story::factory()->create(['title' => 'Cerita publik']);
    Story::factory()->create(['status' => 'draft', 'published' => false, 'created_by' => $user->id]);
    Story::factory()->create(['status' => 'draft', 'published' => false, 'created_by' => User::factory()->create()->id]);
    $this->actingAs($user)->get(route('stories.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->has('stories.data', 2));
});

test('public marga directory excludes private margas', function () {
    Marga::factory()->create(['name' => 'Publik', 'is_public' => true]);
    Marga::factory()->create(['name' => 'Privat', 'is_public' => false]);
    $this->get(route('marga.view'))->assertOk()->assertInertia(fn (Assert $page) => $page->has('margas', 1)->where('margas.0.name', 'Publik')->where('stats.totalMargas', 1));
});
