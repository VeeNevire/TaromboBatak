<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\TelegramCodeRequest;
use App\Http\Requests\Settings\TelegramPasswordRequest;
use App\Http\Requests\Settings\TelegramPhoneRequest;
use App\Models\TelegramAccount;
use App\Models\TelegramAuthSession;
use App\Services\TelegramMtproto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class TelegramMtprotoController extends Controller
{
    public function index(): Response
    {
        $user = request()->user();
        $pending = $user->telegramAuthSession;

        return Inertia::render('settings/telegram', [
            'configured' => app(TelegramMtproto::class)->configured(),
            'account' => $user->telegramAccount?->only(['username', 'display_name', 'linked_at', 'connection_status', 'last_seen_at']),
            'pendingStatus' => $pending?->status,
            'qrSvg' => $pending?->status === TelegramAuthSession::STATUS_QR_PENDING ? $pending->qr_svg : null,
            'qrExpiresAt' => $pending?->qr_expires_at?->toIso8601String(),
        ]);
    }

    public function store(TelegramPhoneRequest $request, TelegramMtproto $telegram): RedirectResponse
    {
        $user = $request->user();
        $user->telegramAccount?->delete();
        $existing = $user->telegramAuthSession;
        if ($existing) {
            $this->removeSession($existing);
        }

        $session = TelegramAuthSession::query()->create([
            'user_id' => $user->id,
            'phone' => $request->validated('phone'),
            'session_path' => TelegramMtproto::sessionPath($user->id),
            'expires_at' => now()->addMinutes(10),
        ]);

        try {
            $telegram->begin($session);
        } catch (Throwable $exception) {
            $session->delete();
            $this->flashError($exception->getMessage());

            return to_route('telegram-mtproto.index');
        }

        $this->flashSuccess('Kode verifikasi Telegram sudah dikirim.');

        return to_route('telegram-mtproto.index');
    }

    public function qr(TelegramMtproto $telegram): RedirectResponse
    {
        $user = request()->user();
        $user->telegramAccount?->delete();
        $existing = $user->telegramAuthSession;
        if ($existing) {
            $this->removeSession($existing);
        }

        $session = TelegramAuthSession::query()->create([
            'user_id' => $user->id,
            'session_path' => TelegramMtproto::sessionPath($user->id),
            'status' => TelegramAuthSession::STATUS_QR_PENDING,
            'expires_at' => now()->addMinutes(10),
        ]);

        try {
            $qr = $telegram->beginQr($session);
            $session->update(['qr_svg' => $qr['svg'], 'qr_expires_at' => $qr['expires_at']]);
            $this->flashSuccess('QR Telegram siap dipindai.');
        } catch (Throwable $exception) {
            $this->removeSession($session);
            report($exception);
            $this->flashError($this->qrErrorMessage($exception));
        }

        return to_route('telegram-mtproto.index');
    }

    public function qrStatus(TelegramMtproto $telegram): JsonResponse
    {
        $session = $this->session(request()->user());

        if ($session->status !== TelegramAuthSession::STATUS_QR_PENDING) {
            return response()->json(['status' => $session->status]);
        }

        if ($session->qr_expires_at?->isPast()) {
            $session->update(['status' => 'qr_expired']);

            return response()->json(['status' => 'qr_expired']);
        }

        try {
            if ($telegram->authorization($session)) {
                $session->update(['status' => TelegramAuthSession::STATUS_QR_SCANNED]);
                $this->complete(request()->user(), $session, $telegram);

                return response()->json(['status' => 'connected']);
            }
        } catch (Throwable $exception) {
            report($exception);

            if ($this->isExpiredSessionError($exception)) {
                $this->removeSession($session);

                return response()->json(['status' => 'qr_expired']);
            }

            return response()->json(['status' => 'error']);
        }

        return response()->json(['status' => TelegramAuthSession::STATUS_QR_PENDING]);
    }

    public function verifyCode(TelegramCodeRequest $request, TelegramMtproto $telegram): RedirectResponse
    {
        $session = $this->session($request->user());
        try {
            $code = preg_replace('/\D+/', '', (string) $request->validated('code')) ?: '';
            $status = $telegram->verifyCode($session, $code);
            if ($status === TelegramAuthSession::STATUS_PASSWORD_REQUIRED) {
                $session->update(['status' => $status]);
                $this->flashSuccess('Akun memakai 2FA. Masukkan password Telegram.');

                return to_route('telegram-mtproto.index');
            }

            $this->complete($request->user(), $session, $telegram);
        } catch (Throwable $exception) {
            report($exception);
            $this->flashError($this->loginErrorMessage($exception));
        }

        return to_route('telegram-mtproto.index');
    }

    public function resendCode(TelegramMtproto $telegram): RedirectResponse
    {
        $session = $this->session(request()->user());

        try {
            $telegram->begin($session);
            $this->flashSuccess('Kode verifikasi Telegram baru sudah dikirim.');
        } catch (Throwable $exception) {
            report($exception);
            $this->flashError($this->loginErrorMessage($exception));
        }

        return to_route('telegram-mtproto.index');
    }

    public function verifyPassword(TelegramPasswordRequest $request, TelegramMtproto $telegram): RedirectResponse
    {
        $session = $this->session($request->user());
        try {
            $telegram->verifyPassword($session, (string) $request->validated('password'));
            $this->complete($request->user(), $session, $telegram);
        } catch (Throwable $exception) {
            report($exception);
            $this->flashError($this->loginErrorMessage($exception));
        }

        return to_route('telegram-mtproto.index');
    }

    public function destroy(): RedirectResponse
    {
        $user = request()->user();
        $session = $user->telegramAuthSession;
        $account = $user->telegramAccount;

        if ($account) {
            try {
                app(TelegramMtproto::class)->logout($account);
            } catch (Throwable $exception) {
                report($exception);
            }

            $this->removeAccountSession($account);
            $account->delete();
        }

        if ($session) {
            $this->removeSession($session);
        }

        $this->flashSuccess('Koneksi Telegram diputuskan dan semua session serta pesan lokal telah dihapus.');

        return to_route('telegram-mtproto.index');
    }

    private function complete(object $user, TelegramAuthSession $session, TelegramMtproto $telegram): void
    {
        $self = $telegram->self(new TelegramAccount(['session_path' => $session->session_path]));
        $accountData = [
            'telegram_user_id' => (int) ($self['id'] ?? 0),
            'private_chat_id' => (int) ($self['id'] ?? 0),
            'username' => $self['username'] ?? null,
            'display_name' => trim(implode(' ', array_filter([$self['first_name'] ?? null, $self['last_name'] ?? null]))) ?: 'Pengguna Telegram',
            'linked_at' => now(),
            'session_path' => $session->session_path,
            'connection_status' => TelegramAccount::STATUS_CONNECTED,
        ];

        if (filled($session->phone) || filled($self['phone'] ?? null)) {
            $accountData['phone'] = $session->phone ?: $self['phone'];
        }

        TelegramAccount::query()->updateOrCreate(['user_id' => $user->id], $accountData);
        $session->delete();
        $this->flashSuccess('Akun Telegram berhasil terhubung melalui MTProto.');
    }

    private function session(object $user): TelegramAuthSession
    {
        $session = $user->telegramAuthSession;

        abort_unless($session, 422, 'Belum ada proses login Telegram. Masukkan nomor telepon terlebih dahulu.');

        if ($session->expires_at->isPast()) {
            $session->update(['expires_at' => now()->addMinutes(10)]);
        }

        return $session;
    }

    private function removeSession(TelegramAuthSession $session): void
    {
        $this->deleteSessionFiles($session->session_path);
        $session->delete();
    }

    private function removeAccountSession(TelegramAccount $account): void
    {
        $this->deleteSessionFiles($account->session_path);
    }

    private function deleteSessionFiles(?string $sessionPath): void
    {
        if (! $sessionPath) {
            return;
        }

        $path = str_starts_with($sessionPath, DIRECTORY_SEPARATOR)
            ? $sessionPath
            : base_path($sessionPath);

        if (is_dir($path)) {
            File::deleteDirectory($path);
        } elseif (is_file($path)) {
            File::delete($path);
        }

        if (is_file($path.'.lock')) {
            File::delete($path.'.lock');
        }
    }

    private function flashSuccess(string $message): void
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);
    }

    private function flashError(string $message): void
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => $message]);
    }

    private function loginErrorMessage(Throwable $exception): string
    {
        $message = strtoupper($exception->getMessage());

        return match (true) {
            str_contains($message, 'PHONE_CODE_INVALID') => 'Kode Telegram salah. Periksa kembali kode terbaru yang dikirim.',
            str_contains($message, 'PHONE_CODE_EXPIRED') => 'Kode Telegram sudah kedaluwarsa. Silakan kirim ulang kode.',
            str_contains($message, 'PASSWORD_HASH_INVALID') => 'Password 2FA Telegram salah.',
            str_contains($message, 'SESSION_PASSWORD_NEEDED') => 'Akun Telegram memerlukan password 2FA.',
            str_contains($message, 'FLOOD_WAIT') => 'Telegram membatasi percobaan sementara. Tunggu beberapa saat lalu coba lagi.',
            default => 'Telegram tidak dapat memproses permintaan ini. Silakan kirim ulang kode dan coba lagi.',
        };
    }

    private function qrErrorMessage(Throwable $exception): string
    {
        $message = strtolower($exception->getMessage());

        if (
            str_contains($message, 'unknown column')
            || str_contains($message, 'no such column')
            || str_contains($message, 'qr_svg')
            || str_contains($message, 'qr_expires_at')
        ) {
            return 'Database belum diperbarui untuk fitur QR Telegram. Jalankan php artisan migrate lalu coba lagi.';
        }

        if (str_contains($message, 'api_id') || str_contains($message, 'api_hash')) {
            return 'TELEGRAM_API_ID dan TELEGRAM_API_HASH belum terbaca. Jalankan php artisan config:clear lalu coba lagi.';
        }

        return 'QR Telegram tidak dapat dibuat. Cek storage/logs/laravel.log untuk detail error.';
    }

    private function isExpiredSessionError(Throwable $exception): bool
    {
        $message = strtoupper($exception->getMessage());

        return str_contains($message, 'SESSION_EXPIRED')
            || str_contains($message, 'AUTH_RESTART')
            || str_contains($message, 'AUTH_KEY_UNREGISTERED')
            || str_contains($message, 'SESSION_REVOKED');
    }
}
