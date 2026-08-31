<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;

class AccountActivityLogger
{
    /** @param array<string, mixed> $metadata */
    public function log(User $account, User $actor, string $action, string $description, array $metadata = []): ActivityLog
    {
        return ActivityLog::create([
            'account_id' => $account->id,
            'actor_id' => $actor->id,
            'account_name' => $account->name,
            'account_email' => $account->email,
            'action' => $action,
            'description' => $description,
            'metadata' => $metadata !== [] ? $metadata : null,
        ]);
    }
}
