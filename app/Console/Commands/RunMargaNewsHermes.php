<?php

namespace App\Console\Commands;

use App\Models\Marga;
use App\Models\MargaNews;
use App\Models\MargaNewsTopic;
use App\Services\HermesRunClient;
use App\Services\MargaNewsIngestor;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Starts a Hermes run per active topic, waits for it, and ingests whatever
 * news items the run returns. Schedule this (e.g. every 6 hours) instead of
 * waiting for Hermes to call api/berita-marga/tugas + masuk on its own.
 */
class RunMargaNewsHermes extends Command
{
    protected $signature = 'marga-news:run-hermes';

    protected $description = 'Ask Hermes to search news for each active marga news topic and store the results';

    public function handle(HermesRunClient $hermes, MargaNewsIngestor $ingestor): int
    {
        $topics = MargaNewsTopic::query()->where('is_active', true)->with('marga:id,name')->get();

        if ($topics->isEmpty()) {
            $this->info('Tidak ada topik aktif.');

            return self::SUCCESS;
        }

        $knownUrls = MargaNews::query()->where('created_at', '>=', now()->subDays(30))->pluck('url');
        $allMargas = Marga::query()->orderBy('name')->pluck('name');

        foreach ($topics as $topic) {
            $this->line("Menjalankan Hermes untuk topik \"{$topic->keyword}\"...");

            try {
                $run = $hermes->runAndWait([
                    'input' => [
                        'keyword' => $topic->keyword,
                        'marga' => $topic->marga?->name,
                        'notes' => $topic->notes,
                        'margas' => $allMargas,
                        'known_urls' => $knownUrls,
                        'instructions' => 'Cari berita terbaru (maksimal 6 bulan) tentang kegiatan marga Batak '
                            .'(punguan/parsadaan/pomparan/pesta bona taon/tugu/mubes) untuk keyword ini. '
                            .'Kembalikan array item berisi title, url artikel asli, publisher, published_at (ISO 8601), '
                            .'excerpt, summary singkat, dan margas yang disebut. Lewati URL yang ada di known_urls.',
                    ],
                ]);
            } catch (RuntimeException $exception) {
                $this->error("Topik \"{$topic->keyword}\" gagal: {$exception->getMessage()}");

                continue;
            }

            $items = $run['output']['items'] ?? $run['output'] ?? [];
            $items = is_array($items) ? array_map(
                fn (array $item) => ['topic_id' => $topic->id, ...$item],
                array_values($items),
            ) : [];

            if ($items === []) {
                $this->line('Tidak ada berita baru.');

                continue;
            }

            $result = $ingestor->ingest($items, 'hermes-run');
            $this->info("Topik \"{$topic->keyword}\": {$result['accepted']} baru, {$result['duplicates']} duplikat.");
        }

        return self::SUCCESS;
    }
}
