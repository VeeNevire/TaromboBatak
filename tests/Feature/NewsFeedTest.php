<?php

use App\Models\Event;
use App\Models\FeedComment;
use App\Models\FeedPost;
use App\Models\Story;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests cannot open the news feed', function () {
    $this->get(route('news-feed.index'))
        ->assertRedirect(route('login'));
});

test('authenticated users see statuses and approved published content', function () {
    $author = User::factory()->create(['name' => 'Pemilik Status']);
    $commenter = User::factory()->create(['name' => 'Pemberi Komentar']);

    $story = Story::factory()->create([
        'created_by' => $author->id,
        'title' => 'Cerita Disetujui',
        'created_at' => now()->subMinutes(3),
    ]);
    Event::factory()->create([
        'created_by' => $author->id,
        'title' => 'Pengumuman Disetujui',
        'created_at' => now()->subMinutes(2),
    ]);
    Story::factory()->pending()->create([
        'title' => 'Cerita Belum Disetujui',
    ]);

    $post = FeedPost::create([
        'user_id' => $author->id,
        'body' => 'Status keluarga terbaru.',
    ]);
    FeedComment::create([
        'feed_post_id' => $post->id,
        'user_id' => $commenter->id,
        'body' => 'Horas!',
    ]);

    $this->actingAs($commenter)
        ->get(route('news-feed.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('news-feed/index')
            ->has('items', 3)
            ->where('items.0.type', 'status')
            ->where('items.0.body', 'Status keluarga terbaru.')
            ->where('items.0.comments.0.author', 'Pemberi Komentar')
            ->where('items.1.type', 'announcement')
            ->where('items.2.type', 'story')
            ->where('items.2.id', $story->id));
});

test('authenticated users can publish a status', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('news-feed.index'))
        ->post(route('news-feed.posts.store'), [
            'body' => 'Status baru dari keluarga.',
        ])
        ->assertRedirect(route('news-feed.index'));

    $post = FeedPost::firstOrFail();

    expect($post->user_id)->toBe($user->id)
        ->and($post->body)->toBe('Status baru dari keluarga.');
});

test('a status body is required', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('news-feed.posts.store'), ['body' => ''])
        ->assertSessionHasErrors('body');
});

test('authenticated users can comment on a status', function () {
    $author = User::factory()->create();
    $commenter = User::factory()->create();
    $post = FeedPost::create([
        'user_id' => $author->id,
        'body' => 'Status yang dikomentari.',
    ]);

    $this->actingAs($commenter)
        ->from(route('news-feed.index'))
        ->post(route('news-feed.posts.comments.store', $post), [
            'body' => 'Komentar keluarga.',
        ])
        ->assertRedirect(route('news-feed.index'));

    $comment = FeedComment::firstOrFail();

    expect($comment->feed_post_id)->toBe($post->id)
        ->and($comment->user_id)->toBe($commenter->id)
        ->and($comment->body)->toBe('Komentar keluarga.');
});

test('a comment body is required', function () {
    $user = User::factory()->create();
    $post = FeedPost::create([
        'user_id' => $user->id,
        'body' => 'Status keluarga.',
    ]);

    $this->actingAs($user)
        ->post(route('news-feed.posts.comments.store', $post), ['body' => ''])
        ->assertSessionHasErrors('body');
});
