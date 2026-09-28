<?php

namespace App\Services;

use App\Models\Marga;
use App\Models\MargaNews;
use App\Models\MargaNewsTopic;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

/**
 * Stores news items sent by the news agent as pending articles: duplicates are
 * skipped, text is cleaned and shortened, and the margas mentioned are tagged.
 */
class MargaNewsIngestor
{
    /** Marga names too generic to tag on their own (every article mentions them). */
    private const UNTAGGABLE = ['batak'];

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{accepted: int, duplicates: int}
     */
    public function ingest(array $items, ?string $agent): array
    {
        $margas = $this->taggableMargas();
        $topics = MargaNewsTopic::query()->pluck('marga_id', 'id');
        $accepted = 0;
        $duplicates = 0;
        $seenUrls = [];
        $seenTitles = [];

        foreach ($items as $item) {
            $title = $this->cleanTitle((string) $item['title'], $item['publisher'] ?? null);
            $urlHash = MargaNews::hashUrl((string) $item['url']);
            $titleHash = MargaNews::hashTitle($title);

            if (
                isset($seenUrls[$urlHash]) || isset($seenTitles[$titleHash])
                || MargaNews::query()->where('url_hash', $urlHash)->orWhere('title_hash', $titleHash)->exists()
            ) {
                $duplicates++;

                continue;
            }

            $seenUrls[$urlHash] = true;
            $seenTitles[$titleHash] = true;
            $excerpt = $this->cleanText($item['excerpt'] ?? null, 300);
            $topicId = isset($item['topic_id']) && $topics->has($item['topic_id']) ? (int) $item['topic_id'] : null;

            $news = MargaNews::query()->create([
                'marga_news_topic_id' => $topicId,
                'title' => Str::limit($title, 297),
                'url' => (string) $item['url'],
                'url_hash' => $urlHash,
                'title_hash' => $titleHash,
                'publisher' => $this->cleanText($item['publisher'] ?? null, 120),
                'excerpt' => $excerpt,
                'summary' => $this->cleanText($item['summary'] ?? null, 500),
                'published_at' => $this->parseDate($item['published_at'] ?? null),
                'status' => MargaNews::STATUS_PENDING,
                'submitted_by' => $agent !== null ? Str::limit($agent, 57) : null,
            ]);

            $margaIds = $this->detectMargas(
                $margas,
                $title.' '.$excerpt,
                (array) ($item['margas'] ?? []),
            );

            if ($topicId !== null && $topics->get($topicId) !== null) {
                $margaIds[] = (int) $topics->get($topicId);
            }

            $news->margas()->sync(array_values(array_unique($margaIds)));
            $accepted++;
        }

        return ['accepted' => $accepted, 'duplicates' => $duplicates];
    }

    /** @return Collection<int, Marga> */
    private function taggableMargas(): Collection
    {
        return Marga::query()
            ->get(['id', 'name'])
            ->reject(fn (Marga $marga) => in_array(mb_strtolower(trim($marga->name)), self::UNTAGGABLE, true)
                || mb_strlen(trim($marga->name)) < 3);
    }

    /**
     * Margas named by the agent plus every marga whose name appears as a whole
     * word in the headline or excerpt.
     *
     * @param  Collection<int, Marga>  $margas
     * @param  array<int, mixed>  $named
     * @return array<int, int>
     */
    private function detectMargas(Collection $margas, string $text, array $named): array
    {
        $namedLower = collect($named)
            ->filter(fn ($name) => is_string($name))
            ->map(fn (string $name) => mb_strtolower(trim($name)))
            ->all();

        return $margas
            ->filter(function (Marga $marga) use ($text, $namedLower) {
                $name = mb_strtolower(trim($marga->name));

                return in_array($name, $namedLower, true)
                    || preg_match('/(?<![\p{L}\p{N}])'.preg_quote($name, '/').'(?![\p{L}\p{N}])/iu', $text) === 1;
            })
            ->pluck('id')
            ->all();
    }

    /** Google News appends " - Publisher" to every headline. */
    private function cleanTitle(string $title, ?string $publisher): string
    {
        $title = $this->cleanText($title, 1000) ?? '';

        if (is_string($publisher) && $publisher !== '' && Str::endsWith($title, ' - '.$publisher)) {
            $title = Str::beforeLast($title, ' - '.$publisher);
        }

        return trim($title);
    }

    private function cleanText(mixed $text, int $limit): ?string
    {
        if (! is_string($text)) {
            return null;
        }

        $plain = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5)) ?? '');

        return $plain === '' ? null : Str::limit($plain, $limit - 3);
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            $date = Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }

        // Dates in the future are agent mistakes; keep the article undated.
        return $date->isFuture() ? null : $date;
    }
}
