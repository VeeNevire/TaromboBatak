<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class FormSubmissionGuard
{
    /**
     * Claim a form submission inside the same transaction as its data writes.
     * Concurrent retries with the same key wait for the first transaction and
     * cannot insert a second claim after it commits.
     */
    public function claim(int $userId, string $scope, ?string $key): bool
    {
        if ($key === null || $key === '') {
            return true;
        }

        return DB::table('form_submissions')->insertOrIgnore([
            'user_id' => $userId,
            'scope' => $scope,
            'submission_key' => $key,
            'created_at' => now(),
        ]) === 1;
    }
}
