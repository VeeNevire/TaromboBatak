<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Story;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    /**
     * Pages worth indexing: public landing pages plus published stories and
     * events. Family trees, people and marga news (canonical at the source
     * portal) are deliberately left out.
     */
    public function __invoke(): Response
    {
        $xml = Cache::remember('sitemap.xml', now()->addHour(), function (): string {
            $base = config('seo.canonical_url');
            $urls = collect([
                ['/', 'daily', '1.0'],
                [route('tarombo.view', absolute: false), 'weekly', '0.9'],
                [route('marga.view', absolute: false), 'weekly', '0.9'],
                [route('cerita.index', absolute: false), 'daily', '0.8'],
                [route('kegiatan.index', absolute: false), 'daily', '0.8'],
                [route('marga-news.index', absolute: false), 'daily', '0.7'],
                [route('budaya.view', absolute: false), 'monthly', '0.6'],
                [route('komunitas.view', absolute: false), 'monthly', '0.6'],
                [route('tentang.view', absolute: false), 'monthly', '0.5'],
            ])->map(fn (array $page) => ['loc' => $base.$page[0], 'changefreq' => $page[1], 'priority' => $page[2], 'lastmod' => null]);

            Story::query()->publiclyVisible()->latest('id')->limit(5000)->get(['id', 'updated_at'])
                ->each(fn (Story $story) => $urls->push([
                    'loc' => $base.route('cerita.show', $story, false),
                    'changefreq' => 'monthly',
                    'priority' => '0.6',
                    'lastmod' => $story->updated_at,
                ]));

            Event::query()->publiclyVisible()->latest('id')->limit(5000)->get(['id', 'updated_at'])
                ->each(fn (Event $event) => $urls->push([
                    'loc' => $base.route('kegiatan.show', $event, false),
                    'changefreq' => 'monthly',
                    'priority' => '0.6',
                    'lastmod' => $event->updated_at,
                ]));

            return view('sitemap', ['urls' => $urls->map(fn (array $url) => [
                ...$url,
                'lastmod' => $url['lastmod'] instanceof Carbon ? $url['lastmod']->toAtomString() : null,
            ])])->render();
        });

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
