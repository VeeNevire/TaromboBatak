<?php

namespace App\Services;

use App\Models\Marga;
use App\Models\MargaNews;
use App\Models\MargaNewsSource;
use App\Models\MargaNewsTopic;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

/**
 * Stores news items sent by the news agent as pending articles: duplicates and
 * articles shorter than 200 words are skipped, and mentioned margas are tagged.
 */
class MargaNewsIngestor
{
    /** Marga names too generic to tag on their own (every article mentions them). */
    private const UNTAGGABLE = ['batak'];

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{accepted: int, duplicates: int, insufficient_content: int, without_image: int, invalid_source: int}
     */
    public function ingest(array $items, ?string $agent): array
    {
        $margas = $this->taggableMargas();
        $topics = MargaNewsTopic::query()->with('sources:id,name')->get()->keyBy('id');
        $accepted = 0;
        $duplicates = 0;
        $insufficientContent = 0;
        $withoutImage = 0;
        $invalidSource = 0;
        $seenUrls = [];
        $seenTitles = [];
        $sources = MargaNewsSource::query()->where('is_active', true)->with('topics:id,keyword')->get()->keyBy('id');

        foreach ($items as $item) {
            $source = isset($item['source_id']) ? $sources->get((int) $item['source_id']) : null;
            $topic = isset($item['topic_id']) ? $topics->get((int) $item['topic_id']) : null;

            if (! $source instanceof MargaNewsSource
                || ! $source->allowsArticleUrl((string) ($item['url'] ?? ''))
                || ($topic instanceof MargaNewsTopic
                    ? ! $source->appliesToTopic($topic)
                    : ! $source->applies_to_all_topics)) {
                $invalidSource++;

                continue;
            }

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
            $content = $this->cleanArticleContent($item['content'] ?? null);

            if ($this->wordCount($content) < 200) {
                $insufficientContent++;

                continue;
            }

            $excerpt = $this->cleanText($item['excerpt'] ?? null, 300);
            $imageUrl = $this->cleanImageUrl($item['image_url'] ?? null);
            $topicId = $topic instanceof MargaNewsTopic ? $topic->id : null;

            $news = MargaNews::query()->create([
                'marga_news_topic_id' => $topicId,
                'marga_news_source_id' => $source->id,
                'title' => Str::limit($title, 297),
                'url' => (string) $item['url'],
                'url_hash' => $urlHash,
                'title_hash' => $titleHash,
                'publisher' => $this->cleanText($item['publisher'] ?? $source->name, 120),
                'excerpt' => $excerpt,
                'summary' => $this->cleanText($item['summary'] ?? null, 500),
                'content' => $content,
                'image_url' => $imageUrl,
                'published_at' => $this->parseDate($item['published_at'] ?? null),
                'status' => MargaNews::STATUS_PENDING,
                'submitted_by' => $agent !== null ? Str::limit($agent, 57) : null,
            ]);

            $margaIds = $this->detectMargas(
                $margas,
                $title.' '.$excerpt,
                (array) ($item['margas'] ?? []),
            );

            if ($topicId !== null && $topic->marga_id !== null) {
                $margaIds[] = (int) $topic->marga_id;
            }

            $news->margas()->sync(array_values(array_unique($margaIds)));
            $accepted++;

            if ($imageUrl === null) {
                $withoutImage++;
            }
        }

        return [
            'accepted' => $accepted,
            'duplicates' => $duplicates,
            'insufficient_content' => $insufficientContent,
            'without_image' => $withoutImage,
            'invalid_source' => $invalidSource,
        ];
    }

    private function cleanArticleContent(mixed $content): ?string
    {
        if (! is_string($content)) {
            return null;
        }

        $withBoundaries = preg_replace('/<[^>]*>/u', ' ', $content) ?? $content;
        $plain = trim(preg_replace('/\s+/u', ' ', html_entity_decode($withBoundaries, ENT_QUOTES | ENT_HTML5)) ?? '');

        return $plain === '' ? null : $plain;
    }

    private function wordCount(?string $content): int
    {
        if ($content === null) {
            return 0;
        }

        preg_match_all('~[\p{L}\p{N}]+(?:[’\'-][\p{L}\p{N}]+)*~u', $content, $matches);

        return count($matches[0]);
    }

    private function cleanImageUrl(mixed $imageUrl): ?string
    {
        if (! is_string($imageUrl) || filter_var($imageUrl, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return in_array(strtolower((string) parse_url($imageUrl, PHP_URL_SCHEME)), ['http', 'https'], true)
            ? $imageUrl
            : null;
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
