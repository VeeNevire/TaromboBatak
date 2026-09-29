<?php

namespace App\Models;

use Database\Factories\MargaNewsFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * A news article about marga activities found by the news agent. Only the
 * headline, a short excerpt and the link are kept; readers open the original.
 *
 * @property int $id
 * @property int|null $marga_news_topic_id
 * @property int|null $marga_news_source_id
 * @property string $title
 * @property string $url
 * @property string $url_hash
 * @property string $title_hash
 * @property string|null $publisher
 * @property string|null $excerpt
 * @property string|null $summary
 * @property string|null $content
 * @property string|null $image_url
 * @property Carbon|null $published_at
 * @property string $status
 * @property string|null $submitted_by
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read MargaNewsTopic|null $topic
 * @property-read User|null $reviewer
 */
#[Fillable([
    'marga_news_topic_id',
    'marga_news_source_id',
    'title',
    'url',
    'url_hash',
    'title_hash',
    'publisher',
    'excerpt',
    'summary',
    'content',
    'image_url',
    'published_at',
    'status',
    'submitted_by',
    'reviewed_by',
    'reviewed_at',
])]
class MargaNews extends Model
{
    /** @use HasFactory<MargaNewsFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $table = 'marga_news';

    /** @return BelongsTo<MargaNewsTopic, $this> */
    public function topic(): BelongsTo
    {
        return $this->belongsTo(MargaNewsTopic::class, 'marga_news_topic_id');
    }

    /** @return BelongsTo<MargaNewsSource, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(MargaNewsSource::class, 'marga_news_source_id');
    }

    /** @return BelongsToMany<Marga, $this> */
    public function margas(): BelongsToMany
    {
        return $this->belongsToMany(Marga::class, 'marga_marga_news');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @param  Builder<MargaNews>  $query
     * @return Builder<MargaNews>
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /** Same article under a slightly different URL (tracking parameters, trailing slash). */
    public static function hashUrl(string $url): string
    {
        $parts = parse_url(trim($url));
        $query = [];

        parse_str($parts['query'] ?? '', $query);
        $query = array_filter(
            $query,
            fn (string $key) => ! str_starts_with($key, 'utm_') && ! in_array($key, ['fbclid', 'gclid'], true),
            ARRAY_FILTER_USE_KEY,
        );
        ksort($query);

        $normalized = mb_strtolower(($parts['host'] ?? '').rtrim($parts['path'] ?? '', '/'))
            .($query === [] ? '' : '?'.http_build_query($query));

        return sha1($normalized);
    }

    /** Same headline republished by another portal or under another link. */
    public static function hashTitle(string $title): string
    {
        return sha1(preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower($title)) ?? '');
    }

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }
}
