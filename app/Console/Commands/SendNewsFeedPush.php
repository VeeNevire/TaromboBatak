<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\Story;
use App\Services\WebPushSender;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SendNewsFeedPush extends Command
{
    /** @var string */
    protected $signature = 'feed:push';

    /** @var string */
    protected $description = 'Kirim notifikasi push untuk cerita dan kegiatan baru di News Feed.';

    public function handle(WebPushSender $sender): int
    {
        if (! $sender->isConfigured()) {
            $this->warn('Web Push belum dikonfigurasi (VAPID_* atau minishlink/web-push).');

            return Command::SUCCESS;
        }

        // Member statuses are left out on purpose: only published stories and
        // announcements, which every visitor may read, reach phones.
        $stories = $this->claim(Story::query(), 'story', ['id', 'title', 'description', 'created_at']);
        $events = $this->claim(Event::query(), 'announcement', ['id', 'title', 'date', 'location', 'created_at']);
        $items = $stories->map(fn (Story $story) => [
            'title' => 'Cerita baru: '.$story->title,
            'body' => $story->description,
            'url' => route('cerita.show', $story, false),
            'tag' => 'story-'.$story->id,
            'at' => $story->created_at,
        ])->concat($events->map(fn (Event $event) => [
            'title' => 'Kegiatan baru: '.$event->title,
            'body' => collect([$event->date->translatedFormat('d F Y'), $event->location])->filter()->join(' • '),
            'url' => route('kegiatan.show', $event, false),
            'tag' => 'announcement-'.$event->id,
            'at' => $event->created_at,
        ]))->sortByDesc('at')->values();

        if ($items->isEmpty()) {
            return Command::SUCCESS;
        }

        $first = $items->first();
        $message = $items->count() === 1
            ? [
                'title' => Str::limit($first['title'], 120),
                'body' => Str::limit((string) $first['body'], 160),
                'url' => $first['url'],
                'tag' => $first['tag'],
            ]
            : [
                'title' => $items->count().' kabar baru di News Feed',
                'body' => Str::limit($first['title'], 160),
                'url' => route('news-feed.index', absolute: false),
                'tag' => 'news-feed',
            ];

        $sent = $sender->sendToAll($message);

        $this->info(sprintf('%d kabar dikirim ke %d perangkat.', $items->count(), $sent));

        return Command::SUCCESS;
    }

    /**
     * Take the published rows not yet announced and mark them announced, so
     * an overlapping run cannot send them twice. Rows an admin hid from the
     * feed are marked too, but not returned.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<int, string>  $columns
     * @return Collection<int, TModel>
     */
    private function claim(Builder $query, string $feedType, array $columns): Collection
    {
        $rows = $query->clone()
            ->publiclyVisible()
            ->whereNull('push_notified_at')
            ->get($columns);

        if ($rows->isEmpty()) {
            return collect();
        }

        $query->clone()->whereKey($rows->modelKeys())->update(['push_notified_at' => now()]);
        $hidden = DB::table('hidden_feed_items')
            ->where('feed_type', $feedType)
            ->whereIn('feed_id', $rows->modelKeys())
            ->pluck('feed_id')
            ->all();

        return $rows->reject(fn (Model $row) => in_array($row->getKey(), $hidden))->values()->toBase();
    }
}
