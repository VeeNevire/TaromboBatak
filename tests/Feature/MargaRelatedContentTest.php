<?php

use App\Models\Event;
use App\Models\FeedPost;
use App\Models\Marga;
use App\Models\Story;
use App\Models\User;

test('marga content shows only related approved published stories and events', function () {
    $admin = User::factory()->asAdmin()->create();
    $marga = Marga::factory()->create();
    $other = Marga::factory()->create();
    foreach ([Story::class => 'stories', Event::class => 'events'] as $model => $tab) {
        $visible = $model::factory()->create(['published' => true, 'status' => 'approved']);
        $visible->relatedMargas()->attach($marga);
        $hidden = $model::factory()->create(['published' => true, 'status' => 'pending']);
        $hidden->relatedMargas()->attach($marga);
        $unpublished = $model::factory()->create(['published' => false, 'status' => 'approved']);
        $unpublished->relatedMargas()->attach($marga);
        $unrelated = $model::factory()->create(['published' => true, 'status' => 'approved']);
        $unrelated->relatedMargas()->attach($other);
        $this->actingAs($admin)->getJson(route('marga.related-content', [$marga, 'tab' => $tab]))
            ->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('items.0.id', $visible->id);
    }
});

test('legacy events retain their original marga association', function () {
    $marga = Marga::factory()->create();
    $event = Event::factory()->create(['marga_id' => $marga->id, 'published' => true, 'status' => 'approved']);
    $this->actingAs(User::factory()->asAdmin()->create())->getJson(route('marga.related-content', [$marga, 'tab' => 'events']))
        ->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('items.0.id', $event->id);
});

test('marga news feed respects audience access and excludes public and unrelated statuses', function () {
    $marga = Marga::factory()->create();
    $other = Marga::factory()->create();
    $author = User::factory()->create();
    $admin = User::factory()->asAdmin()->withMarga($marga->id)->create();
    $post = FeedPost::create(['user_id' => $author->id, 'body' => 'Kabar khusus', 'audience' => 'marga']);
    $post->audienceMargas()->attach($marga);
    FeedPost::create(['user_id' => $author->id, 'body' => 'Publik']);
    $unrelated = FeedPost::create(['user_id' => $author->id, 'body' => 'Marga lain', 'audience' => 'marga']);
    $unrelated->audienceMargas()->attach($other);
    $url = route('marga.related-content', [$marga, 'tab' => 'statuses']);
    $this->actingAs($admin)->getJson($url)->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('items.0.id', $post->id);
    $this->actingAs(User::factory()->asAdmin()->create())->getJson($url)->assertOk()->assertJsonCount(0, 'items');
});

test('marga content is readable by guests and regular users and rejects invalid tabs', function () {
    $marga = Marga::factory()->create();
    $url = route('marga.related-content', [$marga, 'tab' => 'stories']);
    $this->getJson($url)->assertOk();
    $this->actingAs(User::factory()->create())->getJson($url)->assertOk();
    $this->getJson(route('marga.related-content', [$marga, 'tab' => 'invalid']))->assertUnprocessable();
    Story::factory()->count(11)->create(['published' => true, 'status' => 'approved'])->each(fn ($story) => $story->relatedMargas()->attach($marga));
    $this->getJson($url)->assertOk()->assertJsonCount(10, 'items')->assertJsonPath('last_page', 2);
    $this->getJson(route('marga.related-content', [$marga, 'tab' => 'stories', 'page' => 2]))->assertOk()->assertJsonCount(1, 'items');
});
