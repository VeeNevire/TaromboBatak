<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $update_id
 * @property array<string, mixed> $payload
 * @property string $status
 * @property int $attempts
 */
#[Fillable(['update_id', 'payload', 'status', 'attempts', 'last_error', 'processed_at'])]
class TelegramUpdate extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSED = 'processed';

    public const STATUS_FAILED = 'failed';

    protected function casts(): array
    {
        return ['payload' => 'array', 'processed_at' => 'datetime'];
    }
}
