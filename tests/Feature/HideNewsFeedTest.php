<?php

use App\Models\Event;
use App\Models\FeedPost;
use App\Models\Story;
use App\Models\User;
use App\Services\NewsFeedService;

test('staff can hide each feed source without deleting its content', function (string $role, string $type) {
    $staff = User::factory()->create(['role' => $role]);
    $item = match ($type) {
        'status' => FeedPost::query()->create(['user_id' => User::factory()->create()->id, 'audience' => 'public', 'body' => 'Status pengujian']),
        'story' => Story::factory()->create(),
        'announcement' => Event::factory()->create(),
    };

    $this->actingAs($staff)->post(route('news-feed.items.hide', [$type, $item->id]))->assertRedirect();
    $this->assertDatabaseHas('hidden_feed_items', ['feed_type' => $type, 'feed_id' => $item->id, 'hidden_by' => $staff->id]);
    $this->assertDatabaseHas($item->getTable(), ['id' => $item->id]);
    expect(app(NewsFeedService::class)->page()['items'])->toBeEmpty();

    $this->actingAs($staff)->post(route('news-feed.items.hide', [$type, $item->id]))->assertRedirect();
    $this->assertDatabaseCount('hidden_feed_items', 1);
})->with(['admin', 'subadmin'])->with(['status', 'story', 'announcement']);

test('regular users cannot hide feed items', function () {
    $user = User::factory()->create();
    $post = FeedPost::query()->create(['user_id' => $user->id, 'audience' => 'public', 'body' => 'Status pengujian']);
    $this->actingAs($user)->post(route('news-feed.items.hide', ['status', $post->id]))->assertForbidden();
    $this->assertDatabaseCount('hidden_feed_items', 0);
});

test('hidden items no longer count as unread activity', function () {
    $user = User::factory()->create(['news_feed_read_at' => now()->subDay()]);
    $staff = User::factory()->create(['role' => 'admin']);
    $post = FeedPost::query()->create(['user_id' => User::factory()->create()->id, 'audience' => 'public', 'body' => 'Status pengujian']);
    expect(app(NewsFeedService::class)->unreadCount($user))->toBe(1);
    $this->actingAs($staff)->post(route('news-feed.items.hide', ['status', $post->id]));
    expect(app(NewsFeedService::class)->unreadCount($user))->toBe(0);
});
