<?php

use App\Models\Event;
use App\Models\MargaNews;
use App\Models\Story;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    config(['seo.canonical_url' => 'https://taromboconnection.com']);
    Cache::forget('sitemap.xml');
});

/** @return array<int, array<string, mixed>> */
function jsonLdBlocks(string $html): array
{
    preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);

    return array_map(fn (string $json) => json_decode($json, true, flags: JSON_THROW_ON_ERROR), $matches[1]);
}

test('the home page has the brand title, description, canonical and site schema', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)
        ->toContain('<html lang="id"')
        ->toContain('<title>Tarombo Connection – Silsilah &amp; Tarombo Marga Batak</title>')
        ->toContain('<meta name="description" content="Jelajahi tarombo dan silsilah marga Batak melalui Tarombo Connection.')
        ->toContain('<link rel="canonical" href="https://taromboconnection.com/">')
        ->toContain('<meta name="robots" content="index, follow, max-image-preview:large">')
        ->toContain('<meta property="og:site_name" content="Tarombo Connection">');

    expect(collect(jsonLdBlocks($html))->pluck('@type')->all())->toBe(['WebSite', 'Organization']);
});

test('the dashboard copy of the feed points its canonical to the home page', function () {
    $this->get(route('news-feed.index'))
        ->assertOk()
        ->assertSee('<link rel="canonical" href="https://taromboconnection.com/">', false);
});

test('public landing pages have their own title and a self canonical', function (string $route, string $title) {
    $path = route($route, absolute: false);

    $this->get($path)
        ->assertOk()
        ->assertSee('<title>'.e($title).'</title>', false)
        ->assertSee('<link rel="canonical" href="https://taromboconnection.com'.$path.'">', false)
        ->assertSee('<meta name="robots" content="index, follow, max-image-preview:large">', false);
})->with([
    ['tarombo.view', 'Cari Tarombo & Silsilah Marga Batak | Tarombo Connection'],
    ['marga.view', 'Daftar Marga Batak & Tarombo | Tarombo Connection'],
    ['marga-news.index', 'Berita Marga Batak Terbaru | Tarombo Connection'],
    ['cerita.index', 'Cerita Leluhur & Budaya Batak | Tarombo Connection'],
    ['kegiatan.index', 'Event & Kegiatan Komunitas Batak | Tarombo Connection'],
    ['budaya.view', 'Budaya Batak: Filosofi & Adat | Tarombo Connection'],
    ['komunitas.view', 'Komunitas & Organisasi Marga Batak | Tarombo Connection'],
    ['tentang.view', 'Tentang Tarombo Connection'],
]);

test('filtered listings are not indexed but paginated ones are', function () {
    $this->get(route('marga-news.index', ['search' => 'pesta']))
        ->assertSee('<meta name="robots" content="noindex, follow">', false)
        ->assertDontSee('rel="canonical"', false);

    $this->get(route('marga-news.index', ['page' => 2]))
        ->assertSee('<meta name="robots" content="index, follow, max-image-preview:large">', false)
        ->assertSee('<link rel="canonical" href="https://taromboconnection.com/berita-marga?page=2">', false);
});

test('a story page has article and breadcrumb structured data', function () {
    $story = Story::factory()->create(['title' => 'Asal Usul Silaban', 'description' => 'Kisah keturunan Silaban.']);

    $html = $this->get(route('cerita.show', $story))->assertOk()->getContent();
    $schemas = collect(jsonLdBlocks($html))->keyBy('@type');

    expect($html)
        ->toContain('<title>Asal Usul Silaban | Tarombo Connection</title>')
        ->toContain('<meta property="og:type" content="article">')
        ->and($schemas['Article']['headline'])->toBe('Asal Usul Silaban')
        ->and($schemas['Article']['datePublished'])->not->toBeEmpty()
        ->and($schemas['BreadcrumbList']['itemListElement'])->toHaveCount(3);
});

test('an event page has event structured data with its date', function () {
    $event = Event::factory()->create(['title' => 'Pesta Bona Taon', 'location' => 'Medan', 'date' => '2026-12-20']);

    $schemas = collect(jsonLdBlocks($this->get(route('kegiatan.show', $event))->getContent()))->keyBy('@type');

    expect($schemas['Event']['startDate'])->toBe('2026-12-20')
        ->and($schemas['Event']['location']['name'])->toBe('Medan');
});

test('a marga news copy is canonical to the original article', function () {
    $news = MargaNews::factory()->approved()->create(['url' => 'https://portal.example/berita/pesta-silaban']);

    $this->get(route('marga-news.show', $news))
        ->assertOk()
        ->assertSee('<link rel="canonical" href="https://portal.example/berita/pesta-silaban">', false)
        ->assertSee('<meta property="og:url" content="https://taromboconnection.com/berita-marga/'.$news->id.'">', false);
});

test('login and private pages are not indexed', function (string $path) {
    $html = $this->get($path)->getContent();

    expect($html)
        ->toContain('<meta name="robots" content="noindex, follow">')
        ->not->toContain('rel="canonical"')
        ->not->toContain('application/ld+json');
})->with(['/login', '/register', '/dashboard', '/halaman-yang-tidak-ada']);

test('private image and data endpoints send a noindex header', function () {
    $this->get('/regions/districts/12.01')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

test('the sitemap lists public pages and published content only', function () {
    $published = Story::factory()->create();
    $pending = Story::factory()->pending()->create();
    $event = Event::factory()->create();

    $xml = $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->getContent();

    expect(simplexml_load_string($xml))->not->toBeFalse()
        ->and($xml)
        ->toContain('<loc>https://taromboconnection.com/</loc>')
        ->toContain('<loc>https://taromboconnection.com/tarombo</loc>')
        ->toContain('<loc>https://taromboconnection.com/cerita/'.$published->id.'</loc>')
        ->toContain('<loc>https://taromboconnection.com/kegiatan/'.$event->id.'</loc>')
        ->not->toContain('/cerita/'.$pending->id.'<')
        ->not->toContain('/login')
        ->not->toContain('/dashboard');
});

test('robots.txt points crawlers to the sitemap', function () {
    expect(file_get_contents(public_path('robots.txt')))
        ->toContain('Sitemap: https://taromboconnection.com/sitemap.xml')
        ->not->toMatch('/^Disallow: \/$/m');
});
