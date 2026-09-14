<?php

use App\Models\Event;
use App\Models\FeedItemComment;
use App\Models\FeedItemLike;
use App\Models\FeedPost;
use App\Models\Marga;
use App\Models\Story;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('liking is idempotent and can be undone', function () {
    $author = User::factory()->create();
    $liker = User::factory()->create();
    $post = FeedPost::create(['user_id' => $author->id, 'body' => 'Status disukai']);

    $this->actingAs($liker)->from(route('news-feed.index'))
        ->post(route('news-feed.posts.likes.store', $post))->assertRedirect();
    $this->post(route('news-feed.posts.likes.store', $post))->assertRedirect();

    expect($post->likes()->count())->toBe(1);

    $this->delete(route('news-feed.posts.likes.destroy', $post))->assertRedirect();

    expect($post->likes()->count())->toBe(0);
});

test('guests cannot like or comment', function () {
    $post = FeedPost::create([
        'user_id' => User::factory()->create()->id,
        'body' => 'Status publik',
    ]);

    $this->post(route('news-feed.posts.likes.store', $post))->assertRedirect(route('login'));
    $this->post(route('news-feed.posts.comments.store', $post), ['body' => 'Halo'])
        ->assertRedirect(route('login'));

    expect($post->likes()->count())->toBe(0)
        ->and($post->comments()->count())->toBe(0);
});

test('published stories and announcements can be liked and commented on from the news feed', function () {
    $user = User::factory()->create();
    $story = Story::factory()->create([
        'created_by' => $user->id,
        'published' => true,
        'status' => Story::STATUS_APPROVED,
    ]);
    $event = Event::factory()->create([
        'created_by' => $user->id,
        'published' => true,
        'status' => Event::STATUS_APPROVED,
    ]);

    $this->actingAs($user)
        ->post(route('news-feed.items.likes.store', ['feedType' => 'story', 'feedId' => $story->id]))
        ->assertRedirect();
    $this->post(route('news-feed.items.comments.store', ['feedType' => 'story', 'feedId' => $story->id]), [
        'body' => 'Cerita yang bagus.',
    ])->assertRedirect();
    $this->post(route('news-feed.items.likes.store', ['feedType' => 'announcement', 'feedId' => $event->id]))
        ->assertRedirect();
    $this->post(route('news-feed.items.comments.store', ['feedType' => 'announcement', 'feedId' => $event->id]), [
        'body' => 'Saya akan hadir.',
    ])->assertRedirect();

    expect(FeedItemLike::query()->whereMorphedTo('feedable', $story)->count())->toBe(1)
        ->and(FeedItemLike::query()->whereMorphedTo('feedable', $event)->count())->toBe(1)
        ->and(FeedItemComment::query()->whereMorphedTo('feedable', $story)->value('body'))->toBe('Cerita yang bagus.')
        ->and(FeedItemComment::query()->whereMorphedTo('feedable', $event)->value('body'))->toBe('Saya akan hadir.');
});

test('only the author may edit a status', function () {
    $author = User::factory()->create();
    $post = FeedPost::create(['user_id' => $author->id, 'body' => 'Versi awal']);

    // Web requests from a signed-in user turn a denied policy check into a
    // redirect with an error toast (bootstrap/app.php).
    $this->actingAs(User::factory()->create())->from(route('news-feed.index'))
        ->put(route('news-feed.posts.update', $post), ['body' => 'Dibajak'])
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data.toast.type', 'error');

    $this->actingAs(User::factory()->asAdmin()->create())->from(route('news-feed.index'))
        ->put(route('news-feed.posts.update', $post), ['body' => 'Admin pun tidak boleh'])
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data.toast.type', 'error');

    expect($post->refresh()->body)->toBe('Versi awal');

    $this->actingAs($author)->from(route('news-feed.index'))
        ->put(route('news-feed.posts.update', $post), ['body' => 'Versi baru'])
        ->assertRedirect();

    $post->refresh();

    expect($post->body)->toBe('Versi baru')
        ->and($post->edited_at)->not->toBeNull();
});

test('the author or staff may delete a status but other members may not', function () {
    $author = User::factory()->create();
    $post = FeedPost::create(['user_id' => $author->id, 'body' => 'Akan dihapus']);

    $this->actingAs(User::factory()->create())->from(route('news-feed.index'))
        ->delete(route('news-feed.posts.destroy', $post))
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data.toast.type', 'error');

    expect(FeedPost::count())->toBe(1);

    $this->actingAs(User::factory()->asAdmin()->create())->from(route('news-feed.index'))
        ->delete(route('news-feed.posts.destroy', $post))
        ->assertRedirect();

    expect(FeedPost::count())->toBe(0);
});

test('a status can carry uploaded images', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $this->actingAs($user)->from(route('news-feed.index'))
        ->post(route('news-feed.posts.store'), [
            'body' => 'Dengan foto',
            'images' => [
                UploadedFile::fake()->image('satu.jpg'),
                UploadedFile::fake()->image('dua.png'),
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $post = FeedPost::firstOrFail();

    expect($post->images)->toHaveCount(2);

    foreach ($post->images as $image) {
        Storage::disk('public')->assertExists($image->path);
    }
});

test('a status may be image only without a body', function () {
    Storage::fake('public');

    $this->actingAs(User::factory()->create())->from(route('news-feed.index'))
        ->post(route('news-feed.posts.store'), [
            'images' => [UploadedFile::fake()->image('foto.webp')],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(FeedPost::firstOrFail()->body)->toBe('');
});

test('image uploads are rejected when too many, too large, or the wrong type', function (array $images, string $field) {
    Storage::fake('public');

    $this->actingAs(User::factory()->create())
        ->post(route('news-feed.posts.store'), ['body' => 'Coba', 'images' => $images])
        ->assertSessionHasErrors($field);

    expect(FeedPost::count())->toBe(0);
})->with([
    'terlalu banyak' => fn () => [[
        UploadedFile::fake()->image('a.jpg'),
        UploadedFile::fake()->image('b.jpg'),
        UploadedFile::fake()->image('c.jpg'),
        UploadedFile::fake()->image('d.jpg'),
        UploadedFile::fake()->image('e.jpg'),
    ], 'images'],
    'terlalu besar' => fn () => [
        [UploadedFile::fake()->image('besar.jpg')->size(3000)],
        'images.0',
    ],
    'svg ditolak' => fn () => [
        [UploadedFile::fake()->create('vektor.svg', 10, 'image/svg+xml')],
        'images.0',
    ],
]);

test('deleting a status removes its stored images', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $this->actingAs($user)->from(route('news-feed.index'))
        ->post(route('news-feed.posts.store'), [
            'body' => 'Sementara',
            'images' => [UploadedFile::fake()->image('hapus.jpg')],
        ])->assertRedirect();

    $post = FeedPost::firstOrFail();
    $path = $post->images->first()->path;
    Storage::disk('public')->assertExists($path);

    $this->delete(route('news-feed.posts.destroy', $post))->assertRedirect();

    Storage::disk('public')->assertMissing($path);
    expect(FeedPost::count())->toBe(0);
});

test('editing a status can change its audience', function () {
    $marga = Marga::factory()->create();
    $author = User::factory()->create();
    $post = FeedPost::create(['user_id' => $author->id, 'body' => 'Publik dulu']);

    $this->actingAs($author)->from(route('news-feed.index'))
        ->put(route('news-feed.posts.update', $post), [
            'body' => 'Sekarang khusus marga',
            'audience' => 'marga',
            'marga_ids' => [$marga->id],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $post->refresh();

    expect($post->audience)->toBe('marga')
        ->and($post->audienceMargas()->count())->toBe(1);
});
