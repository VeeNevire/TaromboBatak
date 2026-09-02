<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $message_id
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size
 * @property string $category
 * @property-read Message $message
 */
#[Fillable(['disk', 'path', 'original_name', 'mime_type', 'size', 'category'])]
class MessageAttachment extends Model
{
    /** @var array<string, mixed> */
    protected $attributes = [
        'disk' => 'local',
    ];

    /** @return BelongsTo<Message, $this> */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public static function categoryFor(string $mimeType): string
    {
        return match (true) {
            str_starts_with($mimeType, 'image/') => 'image',
            str_starts_with($mimeType, 'video/') => 'video',
            str_starts_with($mimeType, 'audio/') => 'audio',
            default => 'file',
        };
    }

    /** @return array<string, int|string> */
    public function payload(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'category' => $this->category,
            'url' => route('message-attachments.show', $this),
            'download_url' => route('message-attachments.show', [
                'messageAttachment' => $this,
                'download' => 1,
            ]),
        ];
    }
}
