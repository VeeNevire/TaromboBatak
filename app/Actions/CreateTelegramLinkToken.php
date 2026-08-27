<?php

namespace App\Actions;

use App\Models\ChatGroup;
use App\Models\TelegramLinkToken;
use App\Models\User;
use Illuminate\Support\Str;

class CreateTelegramLinkToken
{
    public function forAccount(User $user): string
    {
        return $this->create($user, TelegramLinkToken::PURPOSE_ACCOUNT);
    }

    public function forGroup(User $user, ChatGroup $chatGroup): string
    {
        return $this->create($user, TelegramLinkToken::PURPOSE_GROUP, $chatGroup);
    }

    private function create(User $user, string $purpose, ?ChatGroup $chatGroup = null): string
    {
        TelegramLinkToken::query()
            ->whereBelongsTo($user)
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->delete();

        $token = $purpose === TelegramLinkToken::PURPOSE_GROUP
            ? Str::upper(Str::random(10))
            : Str::random(32);

        TelegramLinkToken::query()->create([
            'user_id' => $user->id,
            'chat_group_id' => $chatGroup?->id,
            'purpose' => $purpose,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addMinutes(15),
        ]);

        return $token;
    }
}
