<?php

namespace App\Console\Commands;

use App\Models\MargaNews;
use App\Services\WebPushSender;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SendMargaNewsPush extends Command
{
    /** @var string */
    protected $signature = 'news:push';

    /** @var string */
    protected $description = 'Kirim notifikasi push untuk berita marga yang baru disetujui.';

    public function handle(WebPushSender $sender): int
    {
        if (! $sender->isConfigured()) {
            $this->warn('Web Push belum dikonfigurasi (VAPID_* atau minishlink/web-push).');

            return Command::SUCCESS;
        }

        // News can be approved by the ingestor or in bulk by an admin; both
        // leave push_notified_at empty, so one sweep covers every path.
        $news = MargaNews::query()
            ->where('status', MargaNews::STATUS_APPROVED)
            ->whereNull('push_notified_at')
            ->latest('id')
            ->get(['id', 'title', 'excerpt', 'publisher']);

        if ($news->isEmpty()) {
            return Command::SUCCESS;
        }

        // Claim the rows first so an overlapping run cannot send them twice.
        MargaNews::query()->whereKey($news->modelKeys())->update(['push_notified_at' => now()]);

        $message = $news->count() === 1
            ? [
                'title' => Str::limit($news->first()->title, 120),
                'body' => Str::limit($news->first()->excerpt ?: 'Berita baru dari '.($news->first()->publisher ?: 'Tarombo Batak'), 160),
                'url' => route('marga-news.show', $news->first(), false),
                'tag' => 'marga-news-'.$news->first()->id,
            ]
            : [
                'title' => $news->count().' berita baru di Tarombo',
                'body' => Str::limit($news->first()->title, 160),
                'url' => route('marga-news.index', absolute: false),
                'tag' => 'marga-news',
            ];

        $sent = $sender->sendToAll($message);

        $this->info(sprintf('%d berita dikirim ke %d perangkat.', $news->count(), $sent));

        return Command::SUCCESS;
    }
}
