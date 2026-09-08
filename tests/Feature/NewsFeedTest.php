<?php

use App\Models\Event;
use App\Models\FeedComment;
use App\Models\FeedPost;
use App\Models\Marga;
use App\Models\Story;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests can open the news feed in read-only mode', function () {
    $this->get(route('news-feed.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('news-feed/index'));
});

test('the home route opens the public news feed', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('news-feed/index'));
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

test('restricted statuses and comments are visible only to selected marga members and the author', function () {
    $marga = Marga::factory()->create();
    $secondMarga = Marga::factory()->create();
    $author = User::factory()->create();
    $member = User::factory()->withMarga($marga->id)->create();
    $secondMember = User::factory()->withMarga($secondMarga->id)->create();
    $outsider = User::factory()->asAdmin()->create();
    $this->actingAs($author)->post(route('news-feed.posts.store'), [
        'body' => 'Kabar khusus marga', 'audience' => 'marga', 'marga_ids' => [$marga->id, $secondMarga->id],
    ])->assertSessionHasNoErrors()->assertRedirect();
    $post = FeedPost::firstOrFail();
    expect($post->audienceMargas()->count())->toBe(2);
    foreach ([$author, $member, $secondMember] as $viewer) {
        $this->actingAs($viewer)->get(route('news-feed.index'))->assertInertia(fn (Assert $page) => $page
            ->has('items', 1)->where('items.0.body', 'Kabar khusus marga'));
        $this->post(route('news-feed.posts.comments.store', $post), ['body' => 'Komentar terbatas'])->assertRedirect();
    }
    $this->actingAs($outsider)->get(route('news-feed.index'))->assertInertia(fn (Assert $page) => $page->has('items', 0));
    $this->post(route('news-feed.posts.comments.store', $post), ['body' => 'Tidak boleh'])->assertNotFound();
    expect($post->comments()->count())->toBe(3);
    auth()->forgetGuards();
    $this->get(route('news-feed.index'))->assertInertia(fn (Assert $page) => $page->has('items', 0));
});

test('public status defaults remain visible to signed in users only', function () {
    $author = User::factory()->create();
    $post = FeedPost::create(['user_id' => $author->id, 'body' => 'Status lama']);
    $this->get(route('news-feed.index'))->assertInertia(fn (Assert $page) => $page->has('items', 0));
    $this->actingAs(User::factory()->create())->get(route('news-feed.index'))->assertInertia(fn (Assert $page) => $page
        ->has('items', 1)->where('items.0.audience_label', 'Publik'));
    expect($post->audience)->toBe('public');
});

test('marga audience requires valid unique selected margas', function (string $kind) {
    $marga = Marga::factory()->create();
    $ids = match ($kind) {
        'empty' => [], 'invalid' => [$marga->id + 1000], 'duplicate' => [$marga->id, $marga->id], 'scalar' => 'bad',
    };
    $error = in_array($kind, ['empty', 'scalar']) ? 'marga_ids' : 'marga_ids.0';
    $this->actingAs(User::factory()->create())->post(route('news-feed.posts.store'), [
        'body' => 'Status', 'audience' => 'marga', 'marga_ids' => $ids,
    ])->assertSessionHasErrors($error);
    expect(FeedPost::count())->toBe(0);
})->with(['empty', 'invalid', 'duplicate', 'scalar']);

test('public status discards marga selections and rejects unknown audience', function () {
    $marga = Marga::factory()->create();
    $this->actingAs(User::factory()->create())->post(route('news-feed.posts.store'), [
        'body' => 'Publik', 'audience' => 'public', 'marga_ids' => [$marga->id],
    ])->assertSessionHasNoErrors()->assertRedirect();
    expect(FeedPost::firstOrFail()->audienceMargas()->count())->toBe(0);
    $this->post(route('news-feed.posts.store'), ['body' => 'Status', 'audience' => 'unknown'])->assertSessionHasErrors('audience');
});
