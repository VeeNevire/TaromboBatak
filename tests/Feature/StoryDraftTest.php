<?php

use App\Models\Story;
use App\Models\User;
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
