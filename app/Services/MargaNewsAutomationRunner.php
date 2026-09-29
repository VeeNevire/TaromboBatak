<?php

namespace App\Services;

use App\Models\Marga;
use App\Models\MargaNews;
use App\Models\MargaNewsAutomationSetting;
use App\Models\MargaNewsTopic;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class MargaNewsAutomationRunner
{
    public function __construct(
        private readonly HermesRunClient $hermes,
        private readonly MargaNewsIngestor $ingestor,
    ) {}

    /** Called by Laravel's scheduler once a minute; due checks use admin settings. */
    public function runIfDue(): void
    {
        $claim = DB::transaction(function (): ?array {
            $setting = MargaNewsAutomationSetting::query()
                ->lockForUpdate()
                ->findOrFail(MargaNewsAutomationSetting::SINGLETON_ID);

            if (! $setting->enabled
                || ($setting->next_run_at !== null && $setting->next_run_at->isFuture())
                || ($setting->last_status === 'running' && $setting->run_lease_until?->isFuture())) {
                return null;
            }

            if (! filled($setting->prompt)) {
                $setting->update([
                    'last_status' => 'failed',
                    'last_finished_at' => now(),
                    'run_lease_until' => null,
                    'next_run_at' => now()->addMinutes($setting->interval_minutes),
                    'last_error' => 'Prompt Hermes belum diisi. Isi prompt di menu Otomatisasi Berita Marga.',
                ]);

                return null;
            }

            $recoveringStaleRun = $setting->last_status === 'running'
                && ! $setting->run_lease_until?->isFuture();

            $activeTopicCount = MargaNewsTopic::query()->where('is_active', true)->count();
            $perRunMinutes = (int) ceil((int) config('services.hermes.run_timeout', 300) / 60);
            $leaseMinutes = max(10, ($perRunMinutes * max(1, $activeTopicCount)) + 5);
            $startedAt = now();

            $setting->update([
                'last_status' => 'running',
                'last_started_at' => $startedAt,
                'run_lease_until' => $startedAt->copy()->addMinutes($leaseMinutes),
                'last_error' => null,
            ]);

            return [
                'started_at' => $startedAt,
                'prompt' => $setting->prompt,
                'recovering_stale_run' => $recoveringStaleRun,
            ];
        });

        if ($claim === null) {
            return;
        }

        $accepted = 0;
        $duplicates = 0;
        $completedTopics = 0;
        $errors = [];

        if ($claim['recovering_stale_run']) {
            Log::warning('Recovering stale Hermes automation run after its lease expired.');
        }

        Log::info('Hermes news automation run started.', [
            'started_at' => $claim['started_at']->toIso8601String(),
            'hermes_host' => parse_url((string) config('services.hermes.base_url'), PHP_URL_HOST),
            'hermes_port' => parse_url((string) config('services.hermes.base_url'), PHP_URL_PORT),
        ]);

        try {
            $topics = MargaNewsTopic::query()
                ->where('is_active', true)
                ->with('marga:id,name')
                ->orderBy('id')
                ->get();

            if ($topics->isEmpty()) {
                Log::error('Hermes news automation has no active topics.');
                $this->finish(
                    $accepted,
                    $duplicates,
                    'failed',
                    'Tidak ada topik berita marga yang aktif. Tambahkan atau aktifkan topik di menu Topik Berita Marga.',
                );

                return;
            }

            $knownUrls = MargaNews::query()
                ->where('created_at', '>=', now()->subDays(30))
                ->pluck('url');
            $allMargas = Marga::query()->orderBy('name')->pluck('name');

            foreach ($topics as $topic) {
                try {
                    Log::info('Hermes news topic started.', [
                        'topic_id' => $topic->id,
                        'keyword' => $topic->keyword,
                    ]);

                    $run = $this->hermes->runAndWait([
                        'input' => json_encode([
                            'keyword' => $topic->keyword,
                            'marga' => $topic->marga?->name,
                            'notes' => $topic->notes,
                            'margas' => $allMargas->values()->all(),
                            'known_urls' => $knownUrls->values()->all(),
                            'output_contract' => [
                                'format' => 'Return only a valid JSON object. Do not wrap it in Markdown.',
                                'items' => [[
                                    'title' => 'original article title',
                                    'url' => 'original article URL',
                                    'publisher' => 'publisher name',
                                    'published_at' => 'ISO 8601 date or null',
                                    'excerpt' => 'short excerpt',
                                    'summary' => 'short summary',
                                    'content' => 'full article text in Indonesian with at least 200 words; preserve factual details and do not invent missing information',
                                    'image_url' => 'absolute URL of the article lead image, or null only when the source has no image',
                                    'margas' => ['marga names mentioned'],
                                ]],
                                'requirements' => [
                                    'Only include articles whose full article text has at least 200 words.',
                                    'Fetch the full article text from the original source URL, not just a search result snippet.',
                                    'Fetch the original lead/featured image URL from the article page and return it as image_url. Do not invent image URLs.',
                                ],
                            ],
                        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'instructions' => $claim['prompt']."\n\nKetentuan wajib aplikasi: buka URL artikel sumber asli dan ambil isi lengkap minimal 200 kata; batas 200 kata ini menggantikan jumlah kata lain yang mungkin disebut pada prompt. Item di bawah batas ini akan ditolak. Ambil juga URL absolut gambar utama dari halaman sumber, dan isi image_url dengan null hanya jika sumber tidak menyediakan gambar. Jangan mengarang isi maupun URL gambar.",
                    ]);

                    $output = $run['output'] ?? [];

                    if (is_string($output)) {
                        $output = trim($output);
                        $output = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $output) ?? $output;
                        $output = json_decode($output, true);
                    }

                    if (! is_array($output)) {
                        throw new RuntimeException('Output Hermes bukan JSON yang valid. Minta Hermes mengembalikan object JSON dengan properti items.');
                    }

                    $items = $output['items'] ?? $output;

                    if (! is_array($items)) {
                        throw new RuntimeException('Output berita dari Hermes bukan array.');
                    }

                    Log::info('Hermes returned news items.', [
                        'topic_id' => $topic->id,
                        'keyword' => $topic->keyword,
                        'items_count' => count($items),
                    ]);

                    $items = array_map(
                        fn (array $item) => ['topic_id' => $topic->id, ...$item],
                        array_values(array_filter($items, 'is_array')),
                    );

                    if ($items !== []) {
                        $result = DB::transaction(
                            fn (): array => $this->ingestor->ingest($items, 'hermes-api'),
                        );
                        $accepted += $result['accepted'];
                        $duplicates += $result['duplicates'];

                        Log::info('Hermes news ingestion completed for topic.', [
                            'topic_id' => $topic->id,
                            'keyword' => $topic->keyword,
                            ...$result,
                        ]);

                        if ($result['insufficient_content'] > 0) {
                            $errors[] = "{$topic->keyword}: {$result['insufficient_content']} berita diabaikan karena isi kurang dari 200 kata.";
                        }

                        $knownUrls = $knownUrls->merge(collect($items)->pluck('url'))->unique()->values();
                    }

                    $completedTopics++;
                } catch (Throwable $exception) {
                    $summary = $this->errorSummary($exception);
                    $errors[] = "{$topic->keyword}: {$summary}";
                    Log::warning('Hermes news topic run failed.', [
                        'topic_id' => $topic->id,
                        'keyword' => $topic->keyword,
                        'exception_class' => $exception::class,
                        'error' => $summary,
                    ]);
                }
            }

            $status = $errors === []
                ? 'succeeded'
                : ($completedTopics > 0 ? 'partial' : 'failed');

            $this->finish($accepted, $duplicates, $status, $errors === [] ? null : implode("\n", $errors));
        } catch (Throwable $exception) {
            $summary = $this->errorSummary($exception);
            Log::error('Hermes news automation run failed.', [
                'exception_class' => $exception::class,
                'error' => $summary,
            ]);
            $this->finish($accepted, $duplicates, 'failed', $summary);
        }
    }

    private function finish(int $accepted, int $duplicates, string $status, ?string $error): void
    {
        DB::transaction(function () use ($accepted, $duplicates, $status, $error): void {
            $setting = MargaNewsAutomationSetting::query()
                ->lockForUpdate()
                ->findOrFail(MargaNewsAutomationSetting::SINGLETON_ID);

            $setting->update([
                'last_status' => $status,
                'last_finished_at' => now(),
                'run_lease_until' => null,
                'next_run_at' => $setting->enabled
                    ? now()->addMinutes($setting->interval_minutes)
                    : null,
                'last_accepted' => $accepted,
                'last_duplicates' => $duplicates,
                'last_error' => $error === null ? null : mb_substr($error, 0, 60000),
            ]);
        });

        Log::log($status === 'failed' ? 'error' : 'info', 'Hermes news automation run finished.', [
            'status' => $status,
            'accepted' => $accepted,
            'duplicates' => $duplicates,
            'error' => $error === null ? null : mb_substr($error, 0, 3000),
        ]);
    }

    private function errorSummary(Throwable $exception): string
    {
        if ($exception instanceof RequestException) {
            $response = $exception->response;
            $detail = $response->json('detail') ?? $response->json('error') ?? $response->json('message');

            return 'Hermes HTTP '.$response->status().($detail ? ': '.mb_substr((string) $detail, 0, 1000) : '');
        }

        if ($exception instanceof QueryException) {
            $details = $exception->errorInfo ?? [];

            return sprintf(
                'Database error %s/%s: %s',
                $details[0] ?? $exception->getCode(),
                $details[1] ?? 'unknown',
                $details[2] ?? $exception->getPrevious()?->getMessage() ?? 'unknown database error',
            );
        }

        return mb_substr($exception->getMessage(), 0, 1500);
    }
}
