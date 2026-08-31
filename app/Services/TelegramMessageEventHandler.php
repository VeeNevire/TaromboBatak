<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TelegramAccount;
use danog\MadelineProto\EventHandler\Attributes\Handler;
use danog\MadelineProto\EventHandler\Message;
use danog\MadelineProto\EventHandler\SimpleFilter\Incoming;
use danog\MadelineProto\SimpleEventHandler;

final class TelegramMessageEventHandler extends SimpleEventHandler
{
    private static int $accountId;

    public static function setAccountId(int $accountId): void
    {
        self::$accountId = $accountId;
    }

    #[Handler]
    public function handle(Incoming&Message $update): void
    {
        $account = TelegramAccount::query()->find(self::$accountId ?? 0);
        if (! $account) {
            return;
        }

        app(TelegramMessageImporter::class)->importEvent($account, $update);
    }

    public function getReportPeers(): array
    {
        return [];
    }
}
