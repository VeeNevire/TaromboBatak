<?php

use App\Models\Event;
use App\Models\FeedPost;
use App\Models\Story;
use App\Models\User;
use App\Services\SharePreviewService;

test('public content exposes its title and description in initial html', function (string $type) {
    $model = $type === 'event' ? Event::factory()->create(['title' => 'Pertemuan Keluarga', 'description' => 'Berkumpul bersama keluarga.']) : Story::factory()->create(['title' => 'Pertemuan Keluarga', 'description' => 'Berkumpul bersama keluarga.']);
    $this->get(route($type === 'event' ? 'kegiatan.show' : 'cerita.show', $model))
        ->assertOk()
        ->assertSee('<meta property="og:title" content="Pertemuan Keluarga">', false)
        ->assertSee('<meta property="og:description" content="Berkumpul bersama keluarga.">', false)
        ->assertSee('<meta property="og:image"', false);
})->with(['event', 'story']);

test('public status exposes preview metadata for guests', function () {
    $post = FeedPost::query()->create(['user_id' => User::factory()->create(['name' => 'Ito'])->id, 'body' => 'Kabar keluarga hari ini.', 'audience' => 'public']);
    $this->get(route('news-feed.statuses.show', $post))->assertOk()
        ->assertSee('<meta property="og:title" content="Status Ito">', false)
        ->assertSee('<meta property="og:description" content="Kabar keluarga hari ini.">', false);
});

test('private status does not leak content into share metadata', function () {
    $preview = app(SharePreviewService::class)->forPage(['component' => 'news-feed/status', 'props' => ['item' => ['audience' => 'marga', 'body' => 'Rahasia keluarga', 'author' => 'Ito']]]);
    expect($preview['description'])->not->toContain('Rahasia keluarga');
});

test('news preview strips markup and resolves image urls', function () {
    $preview = app(SharePreviewService::class)->forPage(['component' => 'marga-news/show', 'props' => ['news' => ['title' => 'Berita Marga', 'excerpt' => '<p>Berita &amp; kegiatan</p>', 'image_url' => '/Brand.png']]]);
    expect($preview['title'])->toBe('Berita Marga')
        ->and($preview['description'])->toBe('Berita & kegiatan')
        ->and($preview['image'])->toStartWith('http');
});
