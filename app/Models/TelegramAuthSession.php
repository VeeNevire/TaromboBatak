<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'phone', 'session_path', 'status', 'qr_svg', 'qr_expires_at', 'expires_at'])]
class TelegramAuthSession extends Model
{
    public const STATUS_CODE_REQUIRED = 'phone_code_required';
    public const STATUS_PASSWORD_REQUIRED = 'password_required';
    public const STATUS_QR_PENDING = 'qr_pending';
    public const STATUS_QR_SCANNED = 'qr_scanned';

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return ['phone' => 'encrypted', 'expires_at' => 'datetime', 'qr_expires_at' => 'datetime'];
    }
}
