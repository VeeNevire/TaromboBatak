<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'website_url', 'domain', 'is_active', 'applies_to_all_topics', 'notes'])]
class MargaNewsSource extends Model
{
    protected $attributes = [
        'is_active' => true,
        'applies_to_all_topics' => true,
    ];

    /** @return BelongsToMany<MargaNewsTopic, $this> */
    public function topics(): BelongsToMany
    {
        return $this->belongsToMany(MargaNewsTopic::class, 'marga_news_source_topic');
    }

    /** @return HasMany<MargaNews, $this> */
    public function news(): HasMany
    {
        return $this->hasMany(MargaNews::class, 'marga_news_source_id');
    }

    public static function normalizeDomain(string $url): ?string
    {
        $host = parse_url(trim($url), PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        $host = mb_strtolower(rtrim($host, '.'));

        return str_starts_with($host, 'www.') ? mb_substr($host, 4) : $host;
    }

    public function allowsArticleUrl(string $url): bool
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $scheme = mb_strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = self::normalizeDomain($url);

        if (! in_array($scheme, ['http', 'https'], true) || $host === null) {
            return false;
        }

        return $host === $this->domain || str_ends_with($host, '.'.$this->domain);
    }

    public function appliesToTopic(MargaNewsTopic $topic): bool
    {
        return $this->applies_to_all_topics
            || $this->topics->contains('id', $topic->id);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'applies_to_all_topics' => 'boolean',
        ];
    }
}
