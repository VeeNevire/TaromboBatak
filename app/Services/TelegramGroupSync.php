<?php

namespace App\Services;

use App\Models\TelegramAccount;
use App\Models\TelegramDialog;
use Throwable;

class TelegramGroupSync
{
    public const PAGE_SIZE = 10;

    public const MAX_DIALOGS = 20;

    public const HISTORY_LIMIT = 20;

    public function __construct(
        private readonly TelegramMtproto $telegram,
        private readonly TelegramMessageImporter $importer,
    ) {}

    /**
     * Sync the next small batch of groups/channels without walking an
     * unbounded Telegram dialog list or importing every message.
     */
    public function syncNext(TelegramAccount $account): int
    {
        $knownPeerIds = TelegramDialog::query()
            ->whereBelongsTo($account, 'account')
            ->whereIn('type', ['group', 'channel'])
            ->pluck('telegram_peer_id')
            ->map(fn (int|string $id): int => (int) $id)
            ->all();
        $knownPeerIds = array_fill_keys($knownPeerIds, true);

        if (count($knownPeerIds) >= self::MAX_DIALOGS) {
            return 0;
        }

        $synced = 0;
        $scanned = 0;
        $batchSize = min(self::PAGE_SIZE, self::MAX_DIALOGS - count($knownPeerIds));

        foreach ($this->telegram->dialogs($account) as $peer) {
            if (++$scanned > 100 || $synced >= $batchSize) {
                break;
            }

            $peerId = is_scalar($peer) ? (int) $peer : 0;
            if ($peerId === 0 || isset($knownPeerIds[$peerId])) {
                continue;
            }

            try {
                $info = $this->telegram->info($account, $peer);
                $type = $this->type($info);
                if ($type === null) {
                    continue;
                }

                $dialog = TelegramDialog::query()->updateOrCreate(
                    ['telegram_account_id' => $account->id, 'telegram_peer_id' => $peerId],
                    [
                        'type' => $type,
                        'title' => $this->title($info, $peerId),
                        'username' => $this->username($info),
                    ],
                );

                $history = $this->telegram->history($account, $peer, self::HISTORY_LIMIT);
                $history = is_array($history)
                    ? array_slice($history, 0, self::HISTORY_LIMIT)
                    : iterator_to_array($history, false);

                foreach ($history as $message) {
                    if (is_array($message)) {
                        $this->importer->importMessage($account, $dialog, $message);
                    }
                }

                $knownPeerIds[$peerId] = true;
                $synced++;
            } catch (Throwable $exception) {
                report($exception);
                // One inaccessible/deleted dialog must not abort the batch.
            }
        }

        return $synced;
    }

    private function type(array $info): ?string
    {
        return match ($info['type'] ?? null) {
            'channel' => 'channel',
            'chat' => 'group',
            default => null,
        };
    }

    private function title(array $info, int $peerId): string
    {
        $entity = $info['User'] ?? $info['Chat'] ?? $info['Channel'] ?? [];
        $title = trim((string) ($entity['title'] ?? ''));

        return $title !== '' ? $title : 'Telegram '.$peerId;
    }

    private function username(array $info): ?string
    {
        $entity = $info['User'] ?? $info['Chat'] ?? $info['Channel'] ?? [];

        return filled($entity['username'] ?? null) ? (string) $entity['username'] : null;
    }
}
