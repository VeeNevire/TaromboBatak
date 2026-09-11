<?php

use App\Models\FeedPost;
use App\Models\Marga;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('anyone can open the permalink of a public status', function () {
    $author = User::factory()->create(['name' => 'Penulis Status']);
    $post = FeedPost::create(['user_id' => $author->id, 'body' => 'Halo semua']);

    $this->get(route('news-feed.statuses.show', $post))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('news-feed/status')
            ->where('item.id', $post->id)
            ->where('item.body', 'Halo semua')
            ->where('item.author', 'Penulis Status')
            ->where('item.can.update', false)
            ->where('item.can.delete', false));
});

test('a marga status is hidden behind 404 for outsiders and guests', function () {
    $marga = Marga::factory()->create();
    $author = User::factory()->create();
    $post = FeedPost::create([
        'user_id' => $author->id,
        'body' => 'Rahasia marga',
        'audience' => 'marga',
    ]);
    $post->audienceMargas()->attach($marga);

    $this->get(route('news-feed.statuses.show', $post))->assertNotFound();

    $this->actingAs(User::factory()->create())
        ->get(route('news-feed.statuses.show', $post))
        ->assertNotFound();

    $this->actingAs(User::factory()->withMarga($marga->id)->create())
        ->get(route('news-feed.statuses.show', $post))
        ->assertOk();

    $this->actingAs($author)
        ->get(route('news-feed.statuses.show', $post))
        ->assertOk();
});

test('the author sees their own edit and delete permissions', function () {
    $author = User::factory()->create();
    $post = FeedPost::create(['user_id' => $author->id, 'body' => 'Milik saya']);

    $this->actingAs($author)
        ->get(route('news-feed.statuses.show', $post))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('item.can.update', true)
            ->where('item.can.delete', true));

    $this->actingAs(User::factory()->asAdmin()->create())
        ->get(route('news-feed.statuses.show', $post))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('item.can.update', false)
            ->where('item.can.delete', true));
});
