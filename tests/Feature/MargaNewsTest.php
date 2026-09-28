<?php

use App\Models\Marga;
use App\Models\MargaNews;
use App\Models\MargaNewsTopic;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['services.marga_news.agent_token' => 'secret-agent-token']);
});

function agentHeaders(string $token = 'secret-agent-token'): array
{
    return ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];
}

function newsItem(array $overrides = []): array
{
    return [
        'title' => 'Seribuan Pomparan Borsak Junjungan Silaban Marpesta Bona Taon di Medan - harianSIB.com',
        'url' => 'https://www.hariansib.com/berita/pesta-bona-taon-silaban',
        'publisher' => 'harianSIB.com',
        'published_at' => '2025-01-19T09:00:00+07:00',
        'excerpt' => '<p>Ribuan pomparan <b>Silaban</b> berkumpul.</p>',
        ...$overrides,
    ];
}

test('the agent api rejects a missing or wrong token', function () {
    $this->getJson(route('api.marga-news.tasks'))->assertUnauthorized();
    $this->getJson(route('api.marga-news.tasks'), agentHeaders('wrong'))->assertUnauthorized();
});

test('the agent api is unavailable until a token is configured', function () {
    config(['services.marga_news.agent_token' => null]);

    $this->getJson(route('api.marga-news.tasks'), agentHeaders())->assertServiceUnavailable();
});

test('the agent receives active topics, marga names and known urls', function () {
    $marga = Marga::factory()->create(['name' => 'Silaban']);
    MargaNewsTopic::query()->create(['keyword' => 'pesta bona taon', 'marga_id' => $marga->id, 'is_active' => true]);
    MargaNewsTopic::query()->create(['keyword' => 'nonaktif', 'is_active' => false]);
    $known = MargaNews::factory()->create();

    $this->getJson(route('api.marga-news.tasks'), agentHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'topics')
        ->assertJsonPath('topics.0.keyword', 'pesta bona taon')
        ->assertJsonPath('topics.0.marga', 'Silaban')
        ->assertJsonFragment(['Silaban'])
        ->assertJsonPath('known_urls.0', $known->url);
});

test('received news is stored pending, cleaned and tagged with the margas mentioned', function () {
    $silaban = Marga::factory()->create(['name' => 'Silaban']);
    $sihombing = Marga::factory()->create(['name' => 'Sihombing']);
    Marga::factory()->create(['name' => 'Batak']);
    $topicMarga = Marga::factory()->create(['name' => 'Lumbantoruan']);
    $topic = MargaNewsTopic::query()->create(['keyword' => 'x', 'marga_id' => $topicMarga->id, 'is_active' => true]);

    $this->postJson(route('api.marga-news.ingest'), [
        'agent' => 'hermes-vps',
        'items' => [newsItem(['topic_id' => $topic->id, 'margas' => ['Sihombing']])],
    ], agentHeaders())
        ->assertCreated()
        ->assertJson(['accepted' => 1, 'duplicates' => 0]);

    $news = MargaNews::query()->sole();

    expect($news->status)->toBe(MargaNews::STATUS_PENDING)
        ->and($news->title)->toBe('Seribuan Pomparan Borsak Junjungan Silaban Marpesta Bona Taon di Medan')
        ->and($news->excerpt)->toBe('Ribuan pomparan Silaban berkumpul.')
        ->and($news->submitted_by)->toBe('hermes-vps')
        ->and($news->published_at?->toDateString())->toBe('2025-01-19')
        ->and($news->margas->pluck('id')->sort()->values()->all())
        ->toBe(collect([$silaban->id, $sihombing->id, $topicMarga->id])->sort()->values()->all());
});

test('the same article is not stored twice', function () {
    $this->postJson(route('api.marga-news.ingest'), ['items' => [newsItem()]], agentHeaders())->assertCreated();

    $this->postJson(route('api.marga-news.ingest'), ['items' => [
        // Same link with tracking parameters and a trailing slash.
        newsItem(['url' => 'https://www.hariansib.com/berita/pesta-bona-taon-silaban/?utm_source=x']),
        // Same headline republished under another link.
        newsItem(['url' => 'https://portal-lain.test/berita/1']),
    ]], agentHeaders())
        ->assertCreated()
        ->assertJson(['accepted' => 0, 'duplicates' => 2]);

    expect(MargaNews::query()->count())->toBe(1);
});

test('received news must link to an http address and be sent in small batches', function () {
    $this->postJson(route('api.marga-news.ingest'), [
        'items' => [newsItem(['url' => 'javascript:alert(1)'])],
    ], agentHeaders())->assertUnprocessable()->assertJsonValidationErrors('items.0.url');

    $this->postJson(route('api.marga-news.ingest'), [
        'items' => array_fill(0, 101, newsItem()),
    ], agentHeaders())->assertUnprocessable()->assertJsonValidationErrors('items');

    expect(MargaNews::query()->exists())->toBeFalse();
});

test('guests only see approved news and can filter by marga', function () {
    $silaban = Marga::factory()->create(['name' => 'Silaban']);
    $approved = MargaNews::factory()->approved()->create();
    $approved->margas()->attach($silaban);
    MargaNews::factory()->approved()->create();
    MargaNews::factory()->create();
    MargaNews::factory()->rejected()->create();

    $this->get(route('marga-news.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('marga-news/index')
            ->has('news.data', 2)
            ->has('margas', 1));

    $this->get(route('marga-news.index', ['marga' => $silaban->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('news.data', 1)
            ->where('news.data.0.id', $approved->id));
});

test('staff can review news and it then appears publicly', function (string $state) {
    $reviewer = User::factory()->{$state}()->create();
    $news = MargaNews::factory()->count(2)->create();

    $this->actingAs($reviewer)
        ->get(route('marga-news.review'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('marga-news/review')->has('news.data', 2));

    $this->actingAs($reviewer)
        ->post(route('marga-news.decide'), ['action' => 'approve', 'ids' => [$news[0]->id]])
        ->assertRedirect();
    $this->actingAs($reviewer)
        ->post(route('marga-news.decide'), ['action' => 'reject', 'ids' => [$news[1]->id]])
        ->assertRedirect();

    expect($news[0]->fresh()->status)->toBe(MargaNews::STATUS_APPROVED)
        ->and($news[0]->fresh()->reviewed_by)->toBe($reviewer->id)
        ->and($news[1]->fresh()->status)->toBe(MargaNews::STATUS_REJECTED);
})->with(['asAdmin', 'asSubAdmin']);

test('staff can correct the margas of a news item', function () {
    $admin = User::factory()->asAdmin()->create();
    $marga = Marga::factory()->create();
    $news = MargaNews::factory()->create();

    $this->actingAs($admin)
        ->put(route('marga-news.margas.update', $news), ['marga_ids' => [$marga->id]])
        ->assertRedirect();

    expect($news->margas()->pluck('margas.id')->all())->toBe([$marga->id]);
});

test('other accounts cannot review news', function (?string $state) {
    $factory = User::factory();
    $user = ($state ? $factory->{$state}() : $factory)->create();
    $news = MargaNews::factory()->create();

    $this->actingAs($user)->get(route('marga-news.review'))->assertForbidden();
    $this->actingAs($user)
        ->post(route('marga-news.decide'), ['action' => 'approve', 'ids' => [$news->id]])
        ->assertForbidden();

    expect($news->fresh()->status)->toBe(MargaNews::STATUS_PENDING);
})->with([null, 'asMainContributor']);

test('only admins manage the search topics', function () {
    $admin = User::factory()->asAdmin()->create();

    $this->actingAs($admin)
        ->post(route('marga-news-topics.store'), ['keyword' => 'pesta tugu', 'is_active' => true])
        ->assertRedirect();

    $topic = MargaNewsTopic::query()->sole();

    $this->actingAs($admin)
        ->put(route('marga-news-topics.update', $topic), ['keyword' => 'pesta tugu marga', 'is_active' => false])
        ->assertRedirect();

    expect($topic->fresh())->keyword->toBe('pesta tugu marga')->is_active->toBeFalse();

    $this->actingAs(User::factory()->asSubAdmin()->create())
        ->get(route('marga-news-topics.index'))
        ->assertForbidden();

    $this->actingAs($admin)->delete(route('marga-news-topics.destroy', $topic))->assertRedirect();

    expect(MargaNewsTopic::query()->exists())->toBeFalse();
});
