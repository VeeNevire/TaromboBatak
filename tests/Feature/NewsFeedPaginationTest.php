<?php

use App\Models\Event;
use App\Models\FeedPost;
use App\Models\Story;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function agedStatus(int $userId, string $body, ?string $at = null): FeedPost
{
    $post = FeedPost::create(['user_id' => $userId, 'body' => $body]);

    // created_at is not fillable, so age each row after it is inserted.
    if ($at !== null) {
        $post->created_at = $at;
        $post->save();
    }

    return $post;
}

test('the first page is capped and reports that more remain', function () {
    $author = User::factory()->create();

    foreach (range(1, 25) as $index) {
        agedStatus($author->id, 'Status '.$index, now()->subMinutes($index)->toDateTimeString());
    }

    $this->get(route('news-feed.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('items', 20)
            ->where('hasMore', true)
            ->where('items.0.body', 'Status 1')
            ->has('cursor.status'));
});

test('paging never repeats or skips a row even when timestamps tie', function () {
    $author = User::factory()->create();
    $shared = now()->subHour()->toDateTimeString();

    // Every row shares one timestamp: the worst case for a cursor that only
    // tracks created_at.
    foreach (range(1, 30) as $index) {
        agedStatus($author->id, 'Kembar '.$index, $shared);
    }

    $seen = [];
    $cursor = null;

    for ($request = 0; $request < 5; $request++) {
        $payload = $this
            ->getJson(route('news-feed.index', $cursor === null ? [] : ['cursor' => $cursor]))
            ->assertOk()
            ->json();

        $seen = [...$seen, ...collect($payload['items'])->pluck('key')->all()];
        $cursor = $payload['cursor'];

        if (! $payload['has_more']) {
            break;
        }
    }

    expect($seen)->toHaveCount(30)
        ->and(array_unique($seen))->toHaveCount(30)
        ->and($cursor)->toBeNull();
});

test('the cursor walks back through every source', function () {
    $author = User::factory()->create();

    agedStatus($author->id, 'Status baru', now()->subMinute()->toDateTimeString());
    Story::factory()->create([
        'created_by' => $author->id,
        'title' => 'Cerita lama',
        'created_at' => now()->subHours(2),
    ]);
    Event::factory()->create([
        'created_by' => $author->id,
        'title' => 'Kegiatan paling lama',
        'created_at' => now()->subHours(3),
    ]);

    $payload = $this->getJson(route('news-feed.index'))->assertOk()->json();

    expect(collect($payload['items'])->pluck('type')->all())
        ->toEqual(['status', 'story', 'announcement'])
        ->and($payload['has_more'])->toBeFalse()
        ->and($payload['cursor'])->toBeNull();
});

test('an oversized cursor value is rejected', function () {
    $this->getJson(route('news-feed.index', ['cursor' => ['status' => str_repeat('x', 100)]]))
        ->assertUnprocessable();
});

test('the json feed keeps stable keys so the client can drop duplicates', function () {
    $author = User::factory()->create();
    $post = FeedPost::create(['user_id' => $author->id, 'body' => 'Satu-satunya']);

    $payload = $this->getJson(route('news-feed.index'))->assertOk()->json();

    expect($payload['items'][0]['key'])->toBe('status-'.$post->id)
        ->and($payload)->toHaveKeys(['items', 'cursor', 'has_more']);
});
