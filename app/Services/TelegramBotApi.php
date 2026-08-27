<?php

namespace App\Services;

use App\Contracts\TelegramBot;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TelegramBotApi implements TelegramBot
{
    public function getUpdates(int $offset, int $timeout = 25): array
    {
        $result = $this->request('getUpdates', [
            'offset' => $offset,
            'timeout' => $timeout,
            'allowed_updates' => ['message'],
        ], $timeout + 10);

        if (! is_array($result)) {
            return [];
        }

        $updates = [];

        foreach ($result as $update) {
            if (is_array($update)) {
                $updates[] = $this->withStringKeys($update);
            }
        }

        return $updates;
    }

    public function sendMessage(int|string $chatId, string $text): array
    {
        $result = $this->request('sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
        ]);

        return is_array($result) ? $this->withStringKeys($result) : [];
    }

    public function getChatMember(int|string $chatId, int|string $userId): array
    {
        $result = $this->request('getChatMember', [
            'chat_id' => $chatId,
            'user_id' => $userId,
        ]);

        return is_array($result) ? $this->withStringKeys($result) : [];
    }

    public function getWebhookInfo(): array
    {
        $result = $this->request('getWebhookInfo');

        return is_array($result) ? $this->withStringKeys($result) : [];
    }

    /** @param array<string, mixed> $payload */
    private function request(string $method, array $payload = [], int $timeout = 15): mixed
    {
        $token = (string) config('services.telegram.bot_token');

        if ($token === '') {
            throw new RuntimeException('TELEGRAM_BOT_TOKEN belum dikonfigurasi.');
        }

        $response = $this->client($timeout)
            ->post($method, $payload)
            ->throw();

        if (! $response->json('ok')) {
            throw new RuntimeException((string) $response->json('description', 'Telegram API gagal.'));
        }

        return $response->json('result');
    }

    /**
     * @param  array<mixed>  $value
     * @return array<string, mixed>
     */
    private function withStringKeys(array $value): array
    {
        $result = [];

        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $result[$key] = $item;
            }
        }

        return $result;
    }

    private function client(int $timeout): PendingRequest
    {
        return Http::baseUrl('https://api.telegram.org/bot'.config('services.telegram.bot_token'))
            ->acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout($timeout)
            ->retry([250, 1000], throw: false);
    }
}
