<?php

namespace App\Contracts;

interface TelegramBot
{
    /** @return array<int, array<string, mixed>> */
    public function getUpdates(int $offset, int $timeout = 25): array;

    /** @return array<string, mixed> */
    public function sendMessage(int|string $chatId, string $text): array;

    /** @return array<string, mixed> */
    public function getChatMember(int|string $chatId, int|string $userId): array;

    /** @return array<string, mixed> */
    public function getWebhookInfo(): array;
}
