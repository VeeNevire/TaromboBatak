<?php

namespace App\Http\Controllers\Settings;

use App\Actions\CreateTelegramLinkToken;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class TelegramConnectionController extends Controller
{
    public function store(Request $request, CreateTelegramLinkToken $tokens): Response|RedirectResponse
    {
        $username = ltrim((string) config('services.telegram.bot_username'), '@');

        if ($username === '') {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'TELEGRAM_BOT_USERNAME belum dikonfigurasi.']);

            return to_route('profile.edit');
        }

        $token = $tokens->forAccount($request->user());

        return Inertia::location("https://t.me/{$username}?start={$token}");
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->user()->telegramAccount()->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Koneksi Telegram diputuskan.']);

        return to_route('profile.edit');
    }
}
