<?php

use App\Models\Story;
use App\Models\User;
use App\Notifications\StorySubmitted;
use Illuminate\Support\Facades\Notification;

test('all roles can save a story without submitting it', function (string $role) {
    Notification::fake();
    $user = User::factory()->create(['role' => $role]);
    $this->actingAs($user)->post(route('stories.store'), [
        'title' => 'Draf keluarga',
        'description' => 'Cerita yang belum siap diajukan.',
        'classification' => 'umum',
        'published' => true,
        'save_draft' => true,
    ])->assertRedirect(route('stories.index'));
    $story = Story::query()->sole();
    expect($story->status)->toBe(Story::STATUS_DRAFT)
        ->and($story->published)->toBeFalse();
    $this->get(route('cerita.show', $story))->assertNotFound();
    Notification::assertNothingSent();
})->with(['user', 'admin', 'subadmin', 'contributor_main', 'contributor_member']);

test('saving changes to a draft keeps it private without notifying reviewers for every role', function (string $role) {
    Notification::fake();
    $user = User::factory()->create(['role' => $role]);
    $story = Story::factory()->create(['created_by' => $user->id, 'status' => Story::STATUS_DRAFT, 'published' => false]);
    $this->actingAs($user)->put(route('stories.update', $story), [
        'title' => 'Draf diperbaiki', 'description' => 'Isi terbaru.', 'classification' => 'umum', 'published' => true,
    ])->assertSessionHasNoErrors()->assertRedirect(route('stories.index'));
    expect($story->fresh()->status)->toBe(Story::STATUS_DRAFT)
        ->and($story->fresh()->published)->toBeFalse()
        ->and($story->fresh()->title)->toBe('Draf diperbaiki');
    $this->get(route('cerita.show', $story))->assertNotFound();
    Notification::assertNothingSent();
})->with(['user', 'admin', 'subadmin', 'contributor_main', 'contributor_member']);

test('explicitly submitting a draft saves its latest content and notifies reviewers for every role', function (string $role) {
    Notification::fake();
    $user = User::factory()->create(['role' => $role]);
    $reviewer = User::factory()->asMainContributor()->create();
    $story = Story::factory()->create(['created_by' => $user->id, 'status' => Story::STATUS_DRAFT, 'published' => false]);
    $this->actingAs($user)->put(route('stories.update', $story), [
        'title' => 'Cerita siap ditinjau', 'description' => 'Isi terbaru.', 'classification' => 'umum',
        'published' => true, 'submit_for_publication' => true,
    ])->assertSessionHasNoErrors()->assertRedirect(route('stories.index'));
    expect($story->fresh()->status)->toBe(Story::STATUS_PENDING)
        ->and($story->fresh()->published)->toBeFalse()
        ->and($story->fresh()->title)->toBe('Cerita siap ditinjau');
    $this->get(route('cerita.show', $story))->assertNotFound();
    Notification::assertSentTo($reviewer, StorySubmitted::class);
})->with(['user', 'admin', 'subadmin', 'contributor_main', 'contributor_member']);

test('another user cannot submit someone elses draft', function () {
    Notification::fake();
    $owner = User::factory()->create();
    $story = Story::factory()->create(['created_by' => $owner->id, 'status' => Story::STATUS_DRAFT, 'published' => false]);
    $this->actingAs(User::factory()->create())->putJson(route('stories.update', $story), [
        'title' => 'Tidak diizinkan', 'description' => 'Isi.', 'classification' => 'umum', 'submit_for_publication' => true,
    ])->assertForbidden();
    expect($story->fresh()->status)->toBe(Story::STATUS_DRAFT);
    Notification::assertNothingSent();
});
