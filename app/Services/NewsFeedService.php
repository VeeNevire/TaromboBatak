<?php

namespace App\Services;

use App\Models\Event;
use App\Models\FeedComment;
use App\Models\FeedPost;
use App\Models\FeedPostImage;
use App\Models\Story;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class NewsFeedService
{
    /**
     * Load one status with everything the feed card needs, for the single
     * status page.
     *
     * @return array<string, mixed>
     */
    public function statusItem(FeedPost $post, ?User $user = null): array
    {
        $post->loadMissing([
            'author:id,name',
            'audienceMargas:id,name',
            'images',
            'comments' => fn ($query) => $query
                ->select(['id', 'feed_post_id', 'user_id', 'body', 'created_at'])
                ->with('author:id,name')
                ->oldest()
                ->oldest('id'),
        ]);
        $post->loadCount('likes');

        if ($user !== null) {
            $post->loadExists([
                'likes as liked_by_me' => fn ($likes) => $likes->where('user_id', $user->id),
            ]);
        }

        return $this->mapStatus($post, $user);
    }

    /** @return array<string, mixed> */
    private function mapStatus(FeedPost $post, ?User $user): array
    {
        return [
            'key' => 'status-'.$post->id,
            'type' => 'status',
            'id' => $post->id,
            'author' => $post->author->name,
            'title' => null,
            'body' => $post->body,
            'audience_label' => $post->audience === 'public' ? 'Publik' : $post->audienceMargas->pluck('name')->join(', '),
            'audience' => $post->audience,
            'marga_ids' => $post->audienceMargas->pluck('id')->all(),
            'image' => null,
            'images' => $post->images->map(fn (FeedPostImage $image) => $image->url())->all(),
            'likes_count' => $post->likes_count,
            'liked_by_me' => (bool) ($post->liked_by_me ?? false),
            'edited' => $post->edited_at !== null,
            'can' => [
                'update' => $user?->can('update', $post) ?? false,
                'delete' => $user?->can('delete', $post) ?? false,
            ],
            'url' => route('news-feed.statuses.show', $post),
            'meta' => null,
            'created_at' => $post->created_at?->toIso8601String(),
            'comments' => $post->comments->map(fn (FeedComment $comment) => [
                'id' => $comment->id,
                'author' => $comment->author->name,
                'body' => $comment->body,
                'created_at' => $comment->created_at?->toIso8601String(),
            ])->values(),
        ];
    }

    private const SOURCES = ['status', 'story', 'announcement'];

    /**
     * One page of the merged feed.
     *
     * The three sources live in different tables, so they cannot share an
     * offset or a single timestamp cursor — `created_at` ties are common
     * (seeded or bulk-created rows land in the same second), and a plain
     * timestamp cursor would either re-serve those rows forever or skip them.
     *
     * Instead each source carries its own keyset cursor of
     * `created_at|id`, matching the `created_at DESC, id DESC` order the
     * queries already use. That yields no duplicates and no gaps.
     *
     * @param  array<string, string|null>|null  $cursor
     * @return array{items: array<int, array<string, mixed>>, cursor: array<string, string|null>|null, has_more: bool}
     */
    public function page(?User $user = null, ?array $cursor = null, int $perPage = 20): array
    {
        $sourceFilledPage = false;
        $collect = function (Collection $rows) use ($perPage, &$sourceFilledPage): Collection {
            $sourceFilledPage = $sourceFilledPage || $rows->count() >= $perPage;

            return $rows;
        };

        $all = $collect($this->statusRows($user, $cursor['status'] ?? null, $perPage))
            ->concat($collect($this->storyRows($cursor['story'] ?? null, $perPage)))
            ->concat($collect($this->announcementRows($cursor['announcement'] ?? null, $perPage)))
            ->sortByDesc('created_at')
            ->values();

        $items = $all->take($perPage)->values();

        $next = [];

        foreach (self::SOURCES as $source) {
            $last = $items->last(fn (array $item) => $item['type'] === $source);
            $next[$source] = $last !== null
                ? $last['created_at'].'|'.$last['id']
                : ($cursor[$source] ?? null);
        }

        $hasMore = $all->count() > $perPage || $sourceFilledPage;

        return [
            'items' => $items->all(),
            'cursor' => $hasMore ? $next : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Restrict a source to rows strictly older than its cursor, ordered by
     * `created_at DESC, id DESC`.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function seek(Builder $query, ?string $cursor, int $perPage): Builder
    {
        if ($cursor !== null && str_contains($cursor, '|')) {
            [$timestamp, $id] = explode('|', $cursor, 2);
            // The cursor travels as ISO 8601; the driver needs a real date so
            // the comparison is not done against a mismatched string literal.
            $at = Carbon::parse($timestamp);

            $query->where(fn ($outer) => $outer
                ->where('created_at', '<', $at)
                ->orWhere(fn ($tie) => $tie
                    ->where('created_at', $at)
                    ->where('id', '<', (int) $id)));
        }

        return $query->latest()->latest('id')->limit($perPage);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function statusRows(?User $user, ?string $cursor, int $perPage): Collection
    {
        $query = FeedPost::query()
            ->select(['id', 'user_id', 'body', 'audience', 'edited_at', 'created_at'])
            ->visibleTo($user)
            ->with([
                'author:id,name',
                'audienceMargas:id,name',
                'images',
                'comments' => fn ($query) => $query
                    ->select(['id', 'feed_post_id', 'user_id', 'body', 'created_at'])
                    ->with('author:id,name')
                    ->oldest()
                    ->oldest('id'),
            ])
            ->withCount('likes')
            ->when($user !== null, fn ($query) => $query->withExists([
                'likes as liked_by_me' => fn ($likes) => $likes->where('user_id', $user->id),
            ]));

        return $this->seek($query, $cursor, $perPage)
            ->get()
            ->map(fn (FeedPost $post) => $this->mapStatus($post, $user));
    }

    /** @return Collection<int, array<string, mixed>> */
    private function storyRows(?string $cursor, int $perPage): Collection
    {
        $query = Story::query()
            ->select(['id', 'created_by', 'marga_id', 'title', 'description', 'image', 'content_url', 'created_at'])
            ->with(['creator:id,name', 'marga:id,name'])
            ->publiclyVisible();

        return $this->seek($query, $cursor, $perPage)
            ->get()
            ->map(fn (Story $story) => [
                'key' => 'story-'.$story->id,
                'type' => 'story',
                'id' => $story->id,
                'author' => $story->creator?->name ?? 'Tim Tarombo Batak',
                'title' => $story->title,
                'body' => $story->description,
                'image' => $story->image,
                'url' => $story->content_url ?: route('cerita.show', $story),
                'meta' => $story->marga?->name,
                'created_at' => $story->created_at?->toIso8601String(),
                'comments' => [],
            ]);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function announcementRows(?string $cursor, int $perPage): Collection
    {
        $query = Event::query()
            ->select(['id', 'created_by', 'title', 'description', 'location', 'date', 'created_at'])
            ->with('creator:id,name')
            ->publiclyVisible();

        return $this->seek($query, $cursor, $perPage)
            ->get()
            ->map(fn (Event $event) => [
                'key' => 'announcement-'.$event->id,
                'type' => 'announcement',
                'id' => $event->id,
                'author' => $event->creator?->name ?? 'Tim Tarombo Batak',
                'title' => $event->title,
                'body' => $event->description,
                'image' => null,
                'url' => route('kegiatan.show', $event),
                'meta' => collect([
                    $event->date->translatedFormat('d F Y'),
                    $event->location,
                ])->filter()->join(' • '),
                'created_at' => $event->created_at?->toIso8601String(),
                'comments' => [],
            ]);
    }
}
