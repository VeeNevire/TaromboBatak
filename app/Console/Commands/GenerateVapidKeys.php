<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;
use RuntimeException;

class GenerateVapidKeys extends Command
{
    /** @var string */
    protected $signature = 'webpush:vapid';

    /** @var string */
    protected $description = 'Buat pasangan kunci VAPID untuk notifikasi push.';

    public function handle(): int
    {
        if (! class_exists(VAPID::class)) {
            $this->error('Jalankan dulu: composer require minishlink/web-push');

            return Command::FAILURE;
        }

        try {
            $keys = VAPID::createVapidKeys();
        } catch (RuntimeException $exception) {
            // On Windows PHP cannot create EC keys until OPENSSL_CONF points
            // at an openssl.cnf; sending notifications needs the same fix.
            $config = dirname(PHP_BINARY).DIRECTORY_SEPARATOR.'extras'.DIRECTORY_SEPARATOR.'ssl'.DIRECTORY_SEPARATOR.'openssl.cnf';

            $this->error('OpenSSL tidak dapat membuat kunci EC: '.$exception->getMessage());

            if (PHP_OS_FAMILY === 'Windows' && is_file($config)) {
                $this->line('Set variabel lingkungan OPENSSL_CONF, lalu buka ulang terminal dan Laragon:');
                $this->line('  setx OPENSSL_CONF "'.$config.'"');
            }

            return Command::FAILURE;
        }

        $this->line('Salin ke .env (sekali saja; mengganti kunci memutus semua langganan):');
        $this->newLine();
        $this->line('VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('VAPID_PRIVATE_KEY='.$keys['privateKey']);

        return Command::SUCCESS;
    }
}
