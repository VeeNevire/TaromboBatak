<?php

use App\Models\Marga;
use App\Models\Story;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

test('related margas can be saved loaded replaced and cleared without changing classification', function () {
    Notification::fake();
    $user = User::factory()->create();
    $margas = Marga::factory()->count(3)->create();
    $ids = $margas->modelKeys();
    $payload = [
        'title' => 'Cerita lintas marga',
        'description' => 'Sejarah bersama beberapa marga.',
        'classification' => 'umum',
        'published' => false,
        'related_marga_ids' => [$ids[0], $ids[1]],
    ];

    $this->actingAs($user)->get(route('stories.create'))
        ->assertInertia(fn (Assert $page) => $page->has('relatedMargaOptions', 3));
    $this->post(route('stories.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();
    $story = Story::firstOrFail();
    expect($story->relatedMargas()->allRelatedIds()->all())->toEqualCanonicalizing([$ids[0], $ids[1]])
        ->and($story->marga_id)->toBeNull()
        ->and($story->status)->toBe(Story::STATUS_PENDING);

    $this->get(route('stories.edit', $story))->assertInertia(fn (Assert $page) => $page
        ->where('story.related_marga_ids', fn ($values) => collect($values)->sort()->values()->all() === collect([$ids[0], $ids[1]])->sort()->values()->all()));

    $this->put(route('stories.update', $story), [...$payload, 'related_marga_ids' => [$ids[2]]])
        ->assertSessionHasNoErrors()->assertRedirect();
    expect($story->relatedMargas()->allRelatedIds()->all())->toBe([$ids[2]]);

    $this->put(route('stories.update', $story), [...$payload, 'related_marga_ids' => []])
        ->assertSessionHasNoErrors()->assertRedirect();
    expect($story->relatedMargas()->count())->toBe(0);
});

test('invalid related marga selections are rejected on create and update', function (string $selection) {
    $user = User::factory()->create();
    $marga = Marga::factory()->create();
    $story = Story::factory()->create(['created_by' => $user->id, 'status' => Story::STATUS_PENDING]);
    $story->relatedMargas()->attach($marga);
    $payload = [
        'title' => 'Cerita', 'description' => 'Deskripsi', 'classification' => 'umum',
        'related_marga_ids' => match ($selection) {
            'missing' => [$marga->id + 1000],
            'duplicate' => [$marga->id, $marga->id],
            'scalar' => 'invalid',
        },
    ];
    $error = $selection === 'scalar' ? 'related_marga_ids' : 'related_marga_ids.0';
    $this->actingAs($user)->post(route('stories.store'), $payload)->assertSessionHasErrors($error);
    $this->put(route('stories.update', $story), $payload)->assertSessionHasErrors($error);
    expect(Story::count())->toBe(1)
        ->and($story->relatedMargas()->allRelatedIds()->all())->toBe([$marga->id]);
})->with(['missing', 'duplicate', 'scalar']);

test('related margas do not change the primary marga of a marga story', function () {
    Notification::fake();
    $primary = Marga::factory()->create();
    $related = Marga::factory()->create();
    $user = User::factory()->withMarga($primary->id)->create();
    $this->actingAs($user)->post(route('stories.store'), [
        'title' => 'Cerita marga', 'description' => 'Deskripsi', 'classification' => 'marga',
        'related_marga_ids' => [$related->id],
    ])->assertSessionHasNoErrors()->assertRedirect();
    $story = Story::firstOrFail();
    expect($story->marga_id)->toBe($primary->id)
        ->and($story->relatedMargas()->allRelatedIds()->all())->toBe([$related->id]);
});
