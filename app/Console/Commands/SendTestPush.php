<?php

namespace App\Console\Commands;

use App\Models\PushSubscription;
use App\Models\User;
use App\Services\WebPushSender;
use Illuminate\Console\Command;

class SendTestPush extends Command
{
    /** @var string */
    protected $signature = 'webpush:test
        {--user= : ID atau email akun; kirim ke semua perangkat akun itu}
        {--id= : ID langganan tertentu (lihat --list)}
        {--list : Tampilkan langganan terbaru tanpa mengirim}';

    /** @var string */
    protected $description = 'Kirim notifikasi percobaan ke satu perangkat (default: yang terakhir berlangganan).';

    public function handle(WebPushSender $sender): int
    {
        if ($this->option('list')) {
            $this->table(
                ['ID', 'Akun', 'Perangkat', 'Sejak'],
                PushSubscription::query()->with('user:id,name,email')->latest('id')->limit(20)->get()
                    ->map(fn (PushSubscription $subscription) => [
                        $subscription->id,
                        $subscription->user ? "{$subscription->user->name} (#{$subscription->user->id})" : 'Tamu',
                        $this->device($subscription->user_agent),
                        $subscription->created_at?->diffForHumans(),
                    ]),
            );

            return Command::SUCCESS;
        }

        if (! $sender->isConfigured()) {
            $this->error('Web Push belum dikonfigurasi: isi VAPID_PUBLIC_KEY dan VAPID_PRIVATE_KEY di .env.');

            return Command::FAILURE;
        }

        $query = PushSubscription::query();

        if (filled($this->option('user'))) {
            $user = User::query()
                ->where('id', $this->option('user'))
                ->orWhere('email', $this->option('user'))
                ->first();

            if ($user === null) {
                $this->error('Akun tidak ditemukan.');

                return Command::FAILURE;
            }

            $query->whereBelongsTo($user);
        } elseif (filled($this->option('id'))) {
            $query->whereKey($this->option('id'));
        } else {
            $query->whereKey(PushSubscription::query()->max('id'));
        }

        $count = $query->clone()->count();

        if ($count === 0) {
            $this->error('Tidak ada perangkat yang cocok. Aktifkan notifikasi dulu di HP, lalu cek dengan --list.');

            return Command::FAILURE;
        }

        $result = $sender->sendTo($query, [
            'title' => 'Tes notifikasi Tarombo',
            'body' => 'Kalau pesan ini muncul, notifikasi berita sudah berjalan. '.now()->format('H:i:s'),
            'url' => route('marga-news.index', absolute: false),
            'tag' => 'webpush-test',
        ]);

        $this->info("Terkirim: {$result['sent']} dari {$count} perangkat.");

        if ($result['expired'] > 0) {
            $this->warn("{$result['expired']} langganan sudah kedaluwarsa dan dihapus; aktifkan ulang di HP.");
        }

        foreach ($result['failed'] as $endpoint => $reason) {
            $this->error('Gagal ('.parse_url($endpoint, PHP_URL_HOST).'): '.$reason);
        }

        return $result['sent'] > 0 ? Command::SUCCESS : Command::FAILURE;
    }

    private function device(?string $userAgent): string
    {
        $userAgent ??= '';

        $os = match (true) {
            str_contains($userAgent, 'iPhone'), str_contains($userAgent, 'iPad') => 'iOS',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Mac OS') => 'macOS',
            default => 'Lainnya',
        };
        $browser = match (true) {
            str_contains($userAgent, 'Edg/') => 'Edge',
            str_contains($userAgent, 'Firefox') => 'Firefox',
            str_contains($userAgent, 'Chrome') => 'Chrome',
            str_contains($userAgent, 'Safari') => 'Safari',
            default => '',
        };

        return trim("{$os} {$browser}");
    }
}
