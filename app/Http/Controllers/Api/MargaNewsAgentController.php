<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IngestMargaNewsRequest;
use App\Models\Marga;
use App\Models\MargaNews;
use App\Models\MargaNewsSource;
use App\Models\MargaNewsTopic;
use App\Services\MargaNewsIngestor;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * The two endpoints the news agent uses: fetch what to search for, then send
 * back what it found. Everything received waits for staff review.
 */
class MargaNewsAgentController extends Controller
{
    public const LAST_SEEN_KEY = 'marga_news.agent_last_seen';

    public const LAST_INGEST_KEY = 'marga_news.agent_last_ingest';

    public function tasks(): JsonResponse
    {
        Cache::forever(self::LAST_SEEN_KEY, now()->toIso8601String());

        $sources = MargaNewsSource::query()
            ->where('is_active', true)
            ->with('topics:id,keyword')
            ->orderBy('name')
            ->get();

        $topics = MargaNewsTopic::query()
            ->where('is_active', true)
            ->with('marga:id,name')
            ->orderBy('id')
            ->get()
            ->map(fn (MargaNewsTopic $topic) => [
                'id' => $topic->id,
                'keyword' => $topic->keyword,
                'marga' => $topic->marga?->name,
                'notes' => $topic->notes,
                'sources' => $sources
                    ->filter(fn (MargaNewsSource $source) => $source->appliesToTopic($topic))
                    ->map(fn (MargaNewsSource $source) => [
                        'id' => $source->id,
                        'name' => $source->name,
                        'url' => $source->website_url,
                        'domain' => $source->domain,
                    ])
                    ->values(),
            ]);

        return response()->json([
            'topics' => $topics,
            'sources' => $sources->map(fn (MargaNewsSource $source) => [
                'id' => $source->id,
                'name' => $source->name,
                'url' => $source->website_url,
                'domain' => $source->domain,
            ])->values(),
            'margas' => Marga::query()->orderBy('name')->pluck('name'),
            // Already known articles, so the agent can skip them.
            'known_urls' => MargaNews::query()
                ->where('created_at', '>=', now()->subDays(30))
                ->pluck('url'),
            'instructions' => 'Cari berita terbaru tentang kegiatan marga/punguan/parsadaan/pomparan Batak untuk tiap keyword. '
                .'Untuk setiap topik, baca berita hanya dari website yang tercantum pada sources topik tersebut; jangan gunakan mesin pencari atau website lain sebagai sumber artikel. '
                .'Kirim source_id sesuai website yang menerbitkan artikel dan pastikan host URL artikel sama dengan domain sumber atau subdomainnya. '
                .'Kirim hanya berita yang benar-benar tentang kegiatan marga Batak (bukan kata "marga" dalam arti lain seperti "Sapta Marga"). '
                .'Ambil isi lengkap artikel dari URL asli, bukan cuplikan hasil pencarian, minimal 200 kata berbahasa Indonesia. '
                .'Ambil juga URL gambar utama/featured dari halaman artikel; isi image_url dengan URL absolut, atau null hanya jika sumber memang tidak memiliki gambar. '
                .'Jangan mengarang fakta atau URL gambar. Isi source_id, title, url, publisher, published_at (ISO 8601), excerpt singkat, summary 1-2 kalimat, content, image_url, dan margas yang disebut.',
        ]);
    }

    public function ingest(IngestMargaNewsRequest $request, MargaNewsIngestor $ingestor): JsonResponse
    {
        $result = $ingestor->ingest($request->validated('items'), $request->validated('agent'));

        Cache::forever(self::LAST_INGEST_KEY, [
            'at' => now()->toIso8601String(),
            'agent' => $request->validated('agent'),
            ...$result,
        ]);

        return response()->json($result, 201);
    }
}
