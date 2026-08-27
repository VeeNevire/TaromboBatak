<?php

namespace App\Http\Controllers;

use App\Actions\CreateTelegramLinkToken;
use App\Models\ChatGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TelegramGroupLinkController extends Controller
{
    public function store(Request $request, ChatGroup $chatGroup, CreateTelegramLinkToken $tokens): RedirectResponse
    {
        abort_unless($request->user()?->can('update', $chatGroup), 403);
        abort_unless($request->user()->telegramAccount()->exists(), 422, 'Hubungkan akun Telegram terlebih dahulu.');

        $code = $tokens->forGroup($request->user(), $chatGroup);
        $request->session()->flash('telegram_link_code', $code);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kode pemasangan dibuat dan berlaku 15 menit.']);

        return to_route('groups.show', $chatGroup);
    }

    public function destroy(Request $request, ChatGroup $chatGroup): RedirectResponse
    {
        abort_unless($request->user()?->can('update', $chatGroup), 403);
        $chatGroup->update([
            'telegram_chat_id' => null,
            'telegram_title' => null,
            'telegram_linked_at' => null,
        ]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Grup Telegram diputuskan.']);

        return to_route('groups.show', $chatGroup);
    }
}
