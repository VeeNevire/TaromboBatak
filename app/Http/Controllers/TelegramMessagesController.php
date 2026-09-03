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
        $syncError = null;
        if ($account) {
            try {
                Cache::remember("telegram-dialogs-refresh:v2:{$account->id}", now()->addMinute(), function () use ($account): bool {
                    $this->syncAccount($account, app(TelegramMtproto::class), app(TelegramMessageImporter::class), false);

                    return true;
                });
            } catch (Throwable $exception) {
                report($exception);
                $syncError = $this->handleSyncFailure($account, $exception);
            }
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
            'connected' => $account?->isMtprotoConnected() ?? false,
            'syncError' => $syncError,
            'dialogs' => $dialogs,
            'selectedDialog' => $selectedDialog,
            'messages' => $messages,
            'search' => $search,
        ]);
    }

    public function sync(Request $request, TelegramMtproto $telegram, TelegramMessageImporter $importer): RedirectResponse
    {
        $account = $this->account($request);
        try {
            $this->syncAccount($account, $telegram, $importer);
        } catch (Throwable $exception) {
            report($exception);
            $message = $this->handleSyncFailure($account, $exception);

            if ($this->isMissingSessionError($exception)) {
                Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

                return to_route('telegram-mtproto.index');
            }

            Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

            return to_route('telegram-messages.index', ['dialog_id' => $request->integer('dialog_id') ?: null]);
        }

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

    private function handleSyncFailure(TelegramAccount $account, Throwable $exception): string
    {
        if ($this->isMissingSessionError($exception)) {
            $account->update([
                'connection_status' => TelegramAccount::STATUS_ERROR,
                'last_error' => $exception->getMessage(),
            ]);

            return 'Sesi Telegram tidak ditemukan atau sudah kedaluwarsa. Hubungkan ulang akun Telegram untuk melanjutkan sinkronisasi.';
        }

        return 'Sinkronisasi Telegram gagal sementara. Silakan coba lagi beberapa saat lagi.';
    }

    private function isMissingSessionError(Throwable $exception): bool
    {
        $message = strtoupper($exception->getMessage());

        return str_contains($message, 'SESSION ERROR')
            || str_contains($message, 'SESSION_NOT_FOUND')
            || str_contains($message, 'SESSION PATH')
            || str_contains($message, 'SESSION') && (
                str_contains($message, 'NOT FOUND')
                || str_contains($message, 'TIDAK DITEMUKAN')
                || str_contains($message, 'EXPIRED')
                || str_contains($message, 'REVOKED')
            )
            || str_contains($message, 'AUTH_KEY_UNREGISTERED')
            || str_contains($message, 'AUTH_KEY_INVALID');
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
