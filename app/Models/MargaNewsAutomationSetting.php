<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property bool $enabled
 * @property int $interval_minutes
 * @property string|null $prompt
 * @property Carbon|null $next_run_at
 * @property string $last_status
 * @property Carbon|null $last_started_at
 * @property Carbon|null $last_finished_at
 * @property Carbon|null $run_lease_until
 * @property int $last_accepted
 * @property int $last_duplicates
 * @property string|null $last_error
 */
#[Fillable([
    'enabled',
    'interval_minutes',
    'prompt',
    'next_run_at',
    'last_status',
    'last_started_at',
    'last_finished_at',
    'run_lease_until',
    'last_accepted',
    'last_duplicates',
    'last_error',
])]
class MargaNewsAutomationSetting extends Model
{
    public const SINGLETON_ID = 1;

    public static function current(): self
    {
        return self::query()->firstOrCreate(
            ['id' => self::SINGLETON_ID],
            [
                'enabled' => false,
                'interval_minutes' => 360,
                'last_status' => 'idle',
            ],
        );
    }

    public static function scheduleImmediateRunIfEnabled(): void
    {
        self::query()
            ->whereKey(self::SINGLETON_ID)
            ->where('enabled', true)
            ->update([
                'next_run_at' => now(),
                'last_error' => null,
            ]);
    }

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'interval_minutes' => 'integer',
            'next_run_at' => 'datetime',
            'last_started_at' => 'datetime',
            'last_finished_at' => 'datetime',
            'run_lease_until' => 'datetime',
            'last_accepted' => 'integer',
            'last_duplicates' => 'integer',
        ];
    }
}
