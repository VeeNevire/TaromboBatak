<?php

namespace App\Services;

use App\Exceptions\TelegramNotConfigured;
use App\Models\TelegramAccount;
use App\Models\TelegramAuthSession;
use danog\MadelineProto\LocalFile;
use danog\MadelineProto\Logger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class TelegramMtproto
{
    public function configured(): bool
    {
        return class_exists('danog\\MadelineProto\\API')
            && filled(config('services.telegram.api_id'))
            && filled(config('services.telegram.api_hash'));
    }

    public function begin(TelegramAuthSession $session): void
    {
        $api = $this->api($session->session_path);
        $api->phoneLogin((string) $session->phone);
    }

    /** @return array{svg: string, expires_at: Carbon} */
    public function beginQr(TelegramAuthSession $session): array
    {
        $qr = $this->api($session->session_path)->qrLogin();

        if ($qr === null) {
            throw new RuntimeException('Telegram tidak dapat membuat QR login.');
        }

        return [
            'svg' => $qr->getQRSvg(320, 2),
            'expires_at' => now()->addSeconds($qr->expiresIn()),
        ];
    }

    public function authorization(TelegramAuthSession $session): bool
    {
        return (bool) $this->api($session->session_path)->getAuthorization();
    }

    public function verifyCode(TelegramAuthSession $session, string $code): string
    {
        $api = $this->api($session->session_path);
        try {
            $api->completePhoneLogin($code);
        } catch (Throwable $exception) {
            $message = strtoupper($exception->getMessage());
            if (str_contains($message, 'SESSION_PASSWORD_NEEDED') || str_contains($message, '2FA')) {
                return TelegramAuthSession::STATUS_PASSWORD_REQUIRED;
            }

            if (str_contains($message, 'NOT WAITING FOR THE CODE')) {
                $this->assertAuthorized($api);

                return 'connected';
            }

            throw $exception;
        }

        $this->assertAuthorized($api);

        return 'connected';
    }

    public function verifyPassword(TelegramAuthSession $session, string $password): void
    {
        $api = $this->api($session->session_path);
        $api->complete2falogin($password);
        $this->assertAuthorized($api);
    }

    public function logout(TelegramAccount $account): void
    {
        $this->api($account->session_path)->logout();
    }

    /** @return array<string, mixed> */
    public function self(TelegramAccount $account): array
    {
        $api = $this->api($account->session_path);
        $self = $api->getSelf();

        if ($self === false) {
            $self = $api->getInfo('me');
        }

        if (! is_array($self)) {
            throw new RuntimeException('Login Telegram berhasil, tetapi profil akun tidak dapat dibaca.');
        }

        return $self;
    }

    /** @return iterable<int, array<string, mixed>> */
    public function dialogs(TelegramAccount $account): iterable
    {
        return $this->api($account->session_path)->getDialogIds();
    }

    /** @return array<int, array<string, mixed>> */
    public function history(TelegramAccount $account, int|string $peer, int $limit = 50): array
    {
        $messages = $this->api($account->session_path)->messages->getHistory(peer: $peer, limit: $limit);

        return is_array($messages) ? ($messages['messages'] ?? []) : [];
    }

    /** @return array<string, mixed> */
    public function info(TelegramAccount $account, int|string $peer): array
    {
        $info = $this->api($account->session_path)->getInfo($peer);

        return is_array($info) ? $info : [];
    }

    /** @return array<string, mixed> */
    public function sendMessage(TelegramAccount $account, int|string $peer, string $body): array
    {
        $message = $this->api($account->session_path)->sendMessage($peer, $body);

        return [
            'id' => $message->id,
            'message' => $message->message,
            'date' => $message->date,
            'out' => true,
        ];
    }

    /** @return array<string, mixed> */
    public function sendDocument(
        TelegramAccount $account,
        int|string $peer,
        string $path,
        string $fileName,
        string $mimeType,
        string $caption = '',
    ): array {
        $message = $this->api($account->session_path)->sendDocument(
            peer: $peer,
            file: new LocalFile($path),
            caption: $caption,
            fileName: $fileName,
            mimeType: $mimeType,
        );

        return [
            'id' => $message->id,
            'message' => $message->message,
            'date' => $message->date,
            'out' => true,
        ];
    }

    public function resolvePeer(TelegramAccount $sender, TelegramAccount $recipient): int|string
    {
        $api = $this->api($sender->session_path);

        if (filled($recipient->username)) {
            return '@'.ltrim((string) $recipient->username, '@');
        }

        if (filled($recipient->phone)) {
            $resolved = $api->contacts->resolvePhone(phone: (string) $recipient->phone);

            $resolvedPeer = $resolved['peer'] ?? null;

            if (is_array($resolvedPeer)) {
                $resolvedId = $resolvedPeer['user_id']
                    ?? $resolvedPeer['chat_id']
                    ?? $resolvedPeer['channel_id']
                    ?? null;

                if (is_numeric($resolvedId)) {
                    return (int) $resolvedId;
                }
            }

            if (is_int($resolvedPeer) || is_string($resolvedPeer)) {
                return $resolvedPeer;
            }
        }

        return $recipient->telegram_user_id;
    }

    public function isDeactivatedPeerError(Throwable $exception): bool
    {
        return str_contains(strtoupper($exception->getMessage()), 'INPUT_USER_DEACTIVATED');
    }

    /** @param callable(array<string, mixed>): void $handler */
    public function listen(TelegramAccount $account, callable $handler): void
    {
        throw new RuntimeException('Listener lama tidak lagi didukung. Gunakan TelegramMessageEventHandler.');
    }

    public function listenAccount(TelegramAccount $account): void
    {
        if (! $this->configured()) {
            throw new TelegramNotConfigured('MadelineProto atau TELEGRAM_API_ID/TELEGRAM_API_HASH belum dikonfigurasi.');
        }

        TelegramMessageEventHandler::setAccountId($account->id);
        TelegramMessageEventHandler::startAndLoop(
            $this->absoluteSessionPath((string) $account->session_path),
            $this->settings(),
        );
    }

    private function api(?string $sessionPath): object
    {
        if (! $this->configured()) {
            throw new TelegramNotConfigured('MadelineProto atau TELEGRAM_API_ID/TELEGRAM_API_HASH belum dikonfigurasi.');
        }

        if (! $sessionPath) {
            throw new RuntimeException('Session Telegram tidak ditemukan.');
        }

        $sessionPath = $this->absoluteSessionPath($sessionPath);
        File::ensureDirectoryExists(dirname($sessionPath), 0700, true);

        $class = 'danog\\MadelineProto\\API';

        return new $class($sessionPath, $this->settings());
    }

    private function settings(): object
    {
        $settingsClass = 'danog\\MadelineProto\\Settings';
        $settings = new $settingsClass;
        $appInfoClass = 'danog\\MadelineProto\\Settings\\AppInfo';
        $appInfo = new $appInfoClass;
        $appInfo
            ->setApiId((int) config('services.telegram.api_id'))
            ->setApiHash((string) config('services.telegram.api_hash'));
        $settings->setAppInfo($appInfo);

        $loggerClass = 'danog\\MadelineProto\\Settings\\Logger';
        $logger = new $loggerClass;
        $logger
            ->setType(Logger::FILE_LOGGER)
            ->setExtra(storage_path('logs/madelineproto.log'));
        $settings->setLogger($logger);

        return $settings;
    }

    private function assertAuthorized(object $api): void
    {
        if (! ($api->getAuthorization() ?? false)) {
            throw new RuntimeException('Login Telegram belum selesai.');
        }
    }

    public static function sessionPath(int $userId): string
    {
        $directory = (string) config('services.telegram.mtproto_session_path');
        if (! str_starts_with($directory, DIRECTORY_SEPARATOR)) {
            $directory = base_path($directory);
        }

        return rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'user-'.$userId.'-'.Str::random(16);
    }

    private function absoluteSessionPath(string $sessionPath): string
    {
        return str_starts_with($sessionPath, DIRECTORY_SEPARATOR)
            ? $sessionPath
            : base_path($sessionPath);
    }
}
