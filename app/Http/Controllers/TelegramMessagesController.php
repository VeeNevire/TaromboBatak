<?php

namespace App\Http\Controllers;

use App\Http\Requests\TelegramReplyRequest;
use App\Models\TelegramAccount;
use App\Models\TelegramDialog;
use App\Models\TelegramMessage;
use App\Services\TelegramMessageImporter;
use App\Services\TelegramMtproto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class TelegramMessagesController extends Controller
{
    public function index(Request $request): Response
    {
        $account = $request->user()->telegramAccount;
        if ($account) {
            Cache::remember("telegram-dialogs-refresh:{$account->id}", now()->addMinute(), function () use ($account): bool {
                try {
                    $this->syncAccount($account, app(TelegramMtproto::class), app(TelegramMessageImporter::class), false);
                } catch (Throwable $exception) {
                    report($exception);
                }

                return true;
            });
        }
        $search = trim((string) $request->query('search', ''));
        $dialogs = $account
            ? $account->dialogs()
                ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhereHas('messages', fn ($messages) => $messages->where('body', 'like', "%{$search}%"));
                }))
                ->orderByDesc('last_message_at')
                ->paginate(10, ['id', 'type', 'title', 'username', 'last_message_at', 'unread_count'])
                ->withQueryString()
            : TelegramDialog::query()->whereKey(0)->paginate(10);

        $selectedDialog = $account
            ? $account->dialogs()->find($request->integer('dialog_id')) ?? $dialogs->first()
            : null;
        $messages = $selectedDialog
            ? $selectedDialog->messages()
                ->when($search !== '', fn ($query) => $query->where('body', 'like', "%{$search}%"))
                ->orderByDesc('sent_at')->paginate(50)->withQueryString()
            : TelegramMessage::query()->whereKey(0)->paginate(50);

        return Inertia::render('telegram/messages', [
            'connected' => $account !== null,
            'dialogs' => $dialogs,
            'selectedDialog' => $selectedDialog,
            'messages' => $messages,
            'search' => $search,
        ]);
    }

    public function sync(Request $request, TelegramMtproto $telegram, TelegramMessageImporter $importer): RedirectResponse
    {
        $account = $this->account($request);
        $this->syncAccount($account, $telegram, $importer);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pesan Telegram berhasil disinkronkan.']);

        return to_route('telegram-messages.index', ['dialog_id' => $request->integer('dialog_id') ?: null]);
    }

    private function syncAccount(TelegramAccount $account, TelegramMtproto $telegram, TelegramMessageImporter $importer, bool $withHistory = true): void
    {
        foreach ($telegram->dialogs($account) as $peer) {
            $peerId = is_scalar($peer) ? (int) $peer : 0;
            if ($peerId === 0) {
                continue;
            }

            try {
                $info = $telegram->info($account, $peer);
            } catch (Throwable $exception) {
                if ($telegram->isDeactivatedPeerError($exception)) {
                    $account->dialogs()->where('telegram_peer_id', $peerId)->update(['title' => 'Akun Telegram dinonaktifkan']);

                    continue;
                }

                throw $exception;
            }
            try {
                $dialog = TelegramDialog::query()->updateOrCreate(
                    ['telegram_account_id' => $account->id, 'telegram_peer_id' => $peerId],
                    [
                        'type' => $this->dialogType($info),
                        'title' => $this->dialogTitle($info, $peerId),
                        'username' => $this->dialogUsername($info),
                    ],
                );

                if ($withHistory) {
                    foreach ($telegram->history($account, $peer, 50) as $message) {
                        if (is_array($message)) {
                            $importer->importMessage($account, $dialog, $message);
                        }
                    }
                }
            } catch (Throwable $exception) {
                if (! $telegram->isDeactivatedPeerError($exception)) {
                    throw $exception;
                }
            }
        }
    }

    public function reply(TelegramReplyRequest $request, TelegramDialog $dialog, TelegramMtproto $telegram, TelegramMessageImporter $importer): RedirectResponse
    {
        $account = $this->account($request);
        abort_unless((int) $dialog->telegram_account_id === (int) $account->id, 404);

        try {
            $message = $telegram->sendMessage($account, $dialog->telegram_peer_id, $request->validated('body'));
        } catch (Throwable $exception) {
            report($exception);
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => $telegram->isDeactivatedPeerError($exception)
                    ? 'Pesan tidak dapat dikirim karena akun Telegram ini sudah dinonaktifkan.'
                    : 'Pesan Telegram gagal dikirim. Silakan coba lagi.',
            ]);

            return to_route('telegram-messages.index', ['dialog_id' => $dialog->id]);
        }
        if ($message !== []) {
            $message['out'] = true;
            $message['peer_id'] = ['chat_id' => $dialog->telegram_peer_id];
            $importer->importMessage($account, $dialog, $message);
        }

        return to_route('telegram-messages.index', ['dialog_id' => $dialog->id]);
    }

    public function read(Request $request, TelegramDialog $dialog): RedirectResponse
    {
        $account = $this->account($request);
        abort_unless((int) $dialog->telegram_account_id === (int) $account->id, 404);
        $dialog->update(['unread_count' => 0, 'last_read_at' => now()]);

        return to_route('telegram-messages.index', ['dialog_id' => $dialog->id]);
    }

    private function account(Request $request): TelegramAccount
    {
        return $request->user()->telegramAccount ?? abort(422, 'Hubungkan akun Telegram terlebih dahulu.');
    }

    private function dialogType(array $info): string
    {
        return match ($info['type'] ?? null) {
            'channel' => 'channel',
            'chat' => 'group',
            default => 'private',
        };
    }

    private function dialogTitle(array $info, int $peerId): string
    {
        $entity = $info['User'] ?? $info['Chat'] ?? $info['Channel'] ?? [];
        $title = $entity['title'] ?? trim(implode(' ', array_filter([$entity['first_name'] ?? null, $entity['last_name'] ?? null])));

        return $title !== '' ? $title : 'Telegram '.$peerId;
    }

    private function dialogUsername(array $info): ?string
    {
        $entity = $info['User'] ?? $info['Chat'] ?? $info['Channel'] ?? [];

        return filled($entity['username'] ?? null) ? (string) $entity['username'] : null;
    }
}
