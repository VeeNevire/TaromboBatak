<?php

use App\Models\Event;
use App\Models\FeedPost;
use App\Models\Story;
use App\Models\User;
use App\Services\WebPushSender;
use Illuminate\Support\Facades\DB;

test('feed:push announces one new published story and never repeats it', function () {
    $story = Story::factory()->create(['title' => 'Asal Usul Silaban']);
    Story::factory()->pending()->create();
    Story::factory()->create(['push_notified_at' => now()->subDay()]);

    $this->mock(WebPushSender::class, function ($mock) use ($story) {
        $mock->shouldReceive('isConfigured')->andReturnTrue();
        $mock->shouldReceive('sendToAll')->once()->withArgs(fn (array $message) => $message['title'] === 'Cerita baru: Asal Usul Silaban'
            && $message['url'] === route('cerita.show', $story, false)
            && $message['tag'] === 'story-'.$story->id)->andReturn(2);
    });

    $this->artisan('feed:push')->expectsOutputToContain('1 kabar dikirim ke 2 perangkat.')->assertSuccessful();
    $this->artisan('feed:push')->assertSuccessful();

    expect($story->fresh()->push_notified_at)->not->toBeNull()
        ->and(Story::query()->where('status', Story::STATUS_PENDING)->sole()->push_notified_at)->toBeNull();
});

test('feed:push links a single announcement to its event page', function () {
    $event = Event::factory()->create(['title' => 'Pesta Bona Taon', 'location' => 'Medan']);

    $this->mock(WebPushSender::class, function ($mock) use ($event) {
        $mock->shouldReceive('isConfigured')->andReturnTrue();
        $mock->shouldReceive('sendToAll')->once()->withArgs(fn (array $message) => $message['title'] === 'Kegiatan baru: Pesta Bona Taon'
            && str_contains($message['body'], 'Medan')
            && $message['url'] === route('kegiatan.show', $event, false))->andReturn(1);
    });

    $this->artisan('feed:push')->assertSuccessful();
});

test('feed:push sums up several new items in one notification', function () {
    Story::factory()->create();
    Event::factory()->count(2)->create();

    $this->mock(WebPushSender::class, function ($mock) {
        $mock->shouldReceive('isConfigured')->andReturnTrue();
        $mock->shouldReceive('sendToAll')->once()->withArgs(fn (array $message) => $message['title'] === '3 kabar baru di News Feed'
            && $message['url'] === route('news-feed.index', absolute: false))->andReturn(1);
    });

    $this->artisan('feed:push')->assertSuccessful();
});

test('feed:push skips member statuses and hidden feed items', function () {
    FeedPost::query()->create(['user_id' => User::factory()->create()->id, 'body' => 'Horas!', 'audience' => 'public']);
    $hidden = Story::factory()->create();
    DB::table('hidden_feed_items')->insert([
        'feed_type' => 'story',
        'feed_id' => $hidden->id,
        'hidden_by' => User::factory()->create()->id,
        'created_at' => now(),
    ]);

    $this->mock(WebPushSender::class, function ($mock) {
        $mock->shouldReceive('isConfigured')->andReturnTrue();
        $mock->shouldNotReceive('sendToAll');
    });

    $this->artisan('feed:push')->assertSuccessful();

    expect($hidden->fresh()->push_notified_at)->not->toBeNull();
});

test('feed:push waits while web push is not configured', function () {
    $story = Story::factory()->create();

    $this->mock(WebPushSender::class, function ($mock) {
        $mock->shouldReceive('isConfigured')->andReturnFalse();
        $mock->shouldNotReceive('sendToAll');
    });

    $this->artisan('feed:push')->assertSuccessful();

    expect($story->fresh()->push_notified_at)->toBeNull();
});
