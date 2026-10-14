<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Search and share metadata for the initial HTML of every Inertia page.
 *
 * Only pages listed in {@see self::INDEXABLE} may be indexed; everything
 * else (login, dashboards, private trees, errors) is served as noindex so a
 * new page stays out of search results until it is added here on purpose.
 */
class SeoService
{
    private const INDEXABLE = [
        'news-feed/index',
        'news-feed/status',
        'tarombo/public',
        'marga/public',
        'marga-news/index',
        'marga-news/show',
        'cerita/index',
        'cerita/show',
        'kegiatan/index',
        'kegiatan/show',
        'budaya/index',
        'komunitas/index',
        'tentang/index',
    ];

    /** Query parameters that only paginate and keep the page indexable. */
    private const PAGINATION_PARAMETERS = ['page'];

    /**
     * @param  array<string, mixed>  $page
     * @return array{title: string, og_title: string, description: string, canonical: string|null, og_url: string, robots: string, og_type: string, image: string, json_ld: array<int, array<string, mixed>>}
     */
    public function forPage(array $page, ?Request $request = null): array
    {
        $request ??= request();
        $component = (string) ($page['component'] ?? '');
        $props = $page['props'] ?? [];
        $siteName = (string) config('seo.site_name');

        $meta = [
            'title' => null,
            'description' => config('seo.default_description'),
            'image' => config('seo.default_image'),
            'og_type' => 'website',
            'canonical' => $this->canonicalUrl($request),
            'indexable' => in_array($component, self::INDEXABLE, true),
            'json_ld' => [],
        ];

        $meta = match ($component) {
            'news-feed/index' => [
                ...$meta,
                'title' => $siteName.' – Silsilah & Tarombo Marga Batak',
                // The feed also lives under /dashboard/news-feed; the home page is the one to index.
                'canonical' => $this->absolute('/').$this->paginationQuery($request),
                'json_ld' => [$this->websiteSchema(), $this->organizationSchema()],
            ],
            'tarombo/public' => [
                ...$meta,
                'title' => 'Cari Tarombo & Silsilah Marga Batak',
                'description' => 'Cari informasi tarombo, silsilah marga Batak, dan hubungan kekerabatan melalui pohon tarombo publik di Tarombo Connection.',
            ],
            'marga/public' => [
                ...$meta,
                'title' => 'Daftar Marga Batak & Tarombo',
                'description' => $this->margaListDescription($props),
            ],
            'marga-news/index' => [
                ...$meta,
                'title' => 'Berita Marga Batak Terbaru',
                'description' => 'Kabar terbaru kegiatan punguan, parsadaan, dan pomparan marga Batak yang dihimpun dari berbagai portal berita.',
            ],
            'cerita/index' => [
                ...$meta,
                'title' => 'Cerita Leluhur & Budaya Batak',
                'description' => 'Kumpulan cerita leluhur, sejarah, dan budaya Batak yang dibagikan komunitas Tarombo Connection.',
            ],
            'kegiatan/index' => [
                ...$meta,
                'title' => 'Event & Kegiatan Komunitas Batak',
                'description' => 'Temukan event dan kegiatan komunitas serta marga Batak yang diumumkan melalui Tarombo Connection.',
            ],
            'budaya/index' => [
                ...$meta,
                'title' => 'Budaya Batak: Filosofi & Adat',
                'description' => 'Kenali kekayaan budaya Batak, mulai dari filosofi hidup, seni, hingga adat yang mengatur hubungan kekerabatan.',
            ],
            'komunitas/index' => [
                ...$meta,
                'title' => 'Komunitas & Organisasi Marga Batak',
                'description' => 'Daftar komunitas dan organisasi marga Batak di Indonesia untuk menjalin silaturahmi dengan keluarga besar marga Anda.',
            ],
            'tentang/index' => [
                ...$meta,
                'title' => 'Tentang Tarombo Connection',
                'description' => 'Tarombo Connection adalah rumah digital untuk menjaga tarombo, silsilah, cerita, dan tradisi keluarga Batak bagi generasi mendatang.',
            ],
            'cerita/show' => $this->storyMeta($meta, $props['story'] ?? []),
            'kegiatan/show' => $this->eventMeta($meta, $props['event'] ?? []),
            'marga-news/show' => $this->newsMeta($meta, $props['news'] ?? []),
            'news-feed/status' => $this->statusMeta($meta, $props['item'] ?? []),
            default => $meta,
        };

        // Search and filter combinations would create endless near-duplicates.
        $hasFilters = collect($request->query())->keys()->diff(self::PAGINATION_PARAMETERS)->isNotEmpty();
        $indexable = $meta['indexable'] && ! $hasFilters;

        $ogTitle = $meta['title'] ?? $siteName;
        $title = $meta['title'] === null
            ? $siteName
            : (str_contains($meta['title'], $siteName) ? $meta['title'] : $meta['title'].' | '.$siteName);

        return [
            'title' => $title,
            'og_title' => $ogTitle,
            'description' => $this->clean((string) $meta['description'], 160),
            // noindex and canonical send conflicting signals, so only indexed pages name one.
            'canonical' => $indexable ? $meta['canonical'] : null,
            // Shares should open this page even when another URL is canonical.
            'og_url' => $this->canonicalUrl($request),
            'robots' => $indexable ? 'index, follow, max-image-preview:large' : 'noindex, follow',
            'og_type' => $meta['og_type'],
            'image' => $this->absolute((string) $meta['image']),
            'json_ld' => $indexable ? $meta['json_ld'] : [],
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $story
     * @return array<string, mixed>
     */
    private function storyMeta(array $meta, array $story): array
    {
        if ($story === []) {
            return $meta;
        }

        $url = $this->absolute(route('cerita.show', $story['id'], false));

        return [
            ...$meta,
            'title' => (string) $story['title'],
            'description' => $story['description'] ?: $meta['description'],
            'image' => $story['image'] ?: $meta['image'],
            'og_type' => 'article',
            'json_ld' => [
                array_filter([
                    '@context' => 'https://schema.org',
                    '@type' => 'Article',
                    'headline' => Str::limit((string) $story['title'], 110, ''),
                    'description' => $this->clean((string) ($story['description'] ?? ''), 300),
                    'image' => filled($story['image'] ?? null) ? [$this->absolute((string) $story['image'])] : null,
                    'datePublished' => $story['published_at'] ?? null,
                    'dateModified' => $story['updated_at'] ?? null,
                    'author' => filled($story['author'] ?? null)
                        ? ['@type' => 'Person', 'name' => $story['author']]
                        : ['@type' => 'Organization', 'name' => config('seo.site_name')],
                    'publisher' => $this->organizationReference(),
                    'mainEntityOfPage' => $url,
                    'inLanguage' => 'id-ID',
                ]),
                $this->breadcrumbs([
                    ['Beranda', '/'],
                    ['Cerita', route('cerita.index', absolute: false)],
                    [(string) $story['title'], $url],
                ]),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    private function eventMeta(array $meta, array $event): array
    {
        if ($event === []) {
            return $meta;
        }

        $url = $this->absolute(route('kegiatan.show', $event['id'], false));
        $when = collect([$event['date'] ?? null, $event['location'] ?? null])->filter()->join(' · ');

        return [
            ...$meta,
            'title' => (string) $event['title'],
            'description' => trim(($when !== '' ? $when.'. ' : '').($event['description'] ?? '')) ?: $meta['description'],
            'json_ld' => [
                array_filter([
                    '@context' => 'https://schema.org',
                    '@type' => 'Event',
                    'name' => (string) $event['title'],
                    'description' => $this->clean((string) ($event['description'] ?? ''), 300),
                    'startDate' => $event['start_date'] ?? null,
                    'eventStatus' => 'https://schema.org/EventScheduled',
                    'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
                    'location' => filled($event['location'] ?? null)
                        ? ['@type' => 'Place', 'name' => $event['location'], 'address' => $event['location']]
                        : null,
                    'organizer' => $this->organizationReference(),
                    'url' => $url,
                ]),
                $this->breadcrumbs([
                    ['Beranda', '/'],
                    ['Kegiatan', route('kegiatan.index', absolute: false)],
                    [(string) $event['title'], $url],
                ]),
            ],
        ];
    }

    /**
     * Articles are copied from other portals, so the original article is
     * the canonical page and this copy does not compete with it.
     *
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $news
     * @return array<string, mixed>
     */
    private function newsMeta(array $meta, array $news): array
    {
        if ($news === []) {
            return $meta;
        }

        $source = (string) ($news['url'] ?? '');

        return [
            ...$meta,
            'title' => (string) $news['title'],
            'description' => $news['excerpt'] ?? $news['summary'] ?? $meta['description'],
            'image' => $news['image_url'] ?? $meta['image'],
            'og_type' => 'article',
            'canonical' => preg_match('/^https?:\/\//i', $source) ? $source : $meta['canonical'],
            'json_ld' => [
                $this->breadcrumbs([
                    ['Beranda', '/'],
                    ['Berita Marga', route('marga-news.index', absolute: false)],
                    [(string) $news['title'], $this->absolute(route('marga-news.show', $news['id'], false))],
                ]),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function statusMeta(array $meta, array $item): array
    {
        // A status shared with selected margas must not leak into previews.
        if (($item['audience'] ?? '') !== 'public') {
            return [...$meta, 'indexable' => false];
        }

        return [
            ...$meta,
            'title' => 'Status '.($item['author'] ?? ''),
            'description' => $item['body'] ?? $meta['description'],
            'image' => $item['images'][0] ?? $meta['image'],
            'og_type' => 'article',
        ];
    }

    /** @param  array<string, mixed>  $props */
    private function margaListDescription(array $props): string
    {
        $total = (int) ($props['stats']['totalMargas'] ?? 0);

        return $total > 0
            ? "Lihat {$total} marga Batak di Tarombo Connection beserta jumlah anggota yang tercatat, lalu telusuri tarombo dan silsilah tiap marga."
            : 'Lihat daftar marga Batak di Tarombo Connection beserta jumlah anggota yang tercatat, lalu telusuri tarombo dan silsilah tiap marga.';
    }

    /** @return array<string, mixed> */
    private function websiteSchema(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => config('seo.site_name'),
            'url' => $this->absolute('/'),
            'inLanguage' => 'id-ID',
        ];
    }

    /** @return array<string, mixed> */
    private function organizationSchema(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => config('seo.site_name'),
            'url' => $this->absolute('/'),
            'logo' => $this->absolute(config('seo.default_image')),
        ];
    }

    /** @return array<string, mixed> */
    private function organizationReference(): array
    {
        return [
            '@type' => 'Organization',
            'name' => config('seo.site_name'),
            'logo' => ['@type' => 'ImageObject', 'url' => $this->absolute(config('seo.default_image'))],
        ];
    }

    /**
     * @param  array<int, array{0: string, 1: string}>  $items
     * @return array<string, mixed>
     */
    private function breadcrumbs(array $items): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($items)->values()->map(fn (array $item, int $index) => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => Str::limit($item[0], 110),
                'item' => $this->absolute($item[1]),
            ])->all(),
        ];
    }

    private function canonicalUrl(Request $request): string
    {
        return $this->absolute('/'.ltrim($request->path(), '/')).$this->paginationQuery($request);
    }

    private function paginationQuery(Request $request): string
    {
        $page = (int) $request->query('page', 1);

        return $page > 1 ? '?page='.$page : '';
    }

    /** Resolve a path or URL against the canonical origin, leaving other hosts alone. */
    private function absolute(string $url): string
    {
        if (preg_match('/^https?:\/\//i', $url)) {
            $path = parse_url($url, PHP_URL_PATH) ?? '/';
            $host = parse_url($url, PHP_URL_HOST);
            $ownHosts = [parse_url(config('app.url'), PHP_URL_HOST), parse_url(config('seo.canonical_url'), PHP_URL_HOST), 'www.'.parse_url(config('seo.canonical_url'), PHP_URL_HOST)];

            if (! in_array($host, $ownHosts, true)) {
                return $url;
            }

            $query = parse_url($url, PHP_URL_QUERY);
            $url = $path.($query ? '?'.$query : '');
        }

        $path = '/'.ltrim($url, '/');

        return config('seo.canonical_url').($path === '/' ? '/' : rtrim($path, '/'));
    }

    private function clean(string $text, int $limit): string
    {
        return Str::limit(Str::squish(html_entity_decode(strip_tags($text), ENT_QUOTES, 'UTF-8')), $limit);
    }
}
