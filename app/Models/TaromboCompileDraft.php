<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A saved Compile Gambar arrangement (frame, layers, crops and positions) so
 * the compile can be opened and edited again. Layer images live privately in
 * storage under directory(), shared by every saved compile (duplicates) of
 * the same account and snapshot.
 *
 * @property int $id
 * @property int $user_id
 * @property int $tarombo_snapshot_id
 * @property string|null $name
 * @property int|null $tarombo_frame_id
 * @property array<string, mixed> $state
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read TaromboSnapshot $snapshot
 */
#[Fillable(['user_id', 'tarombo_snapshot_id', 'name', 'tarombo_frame_id', 'state'])]
class TaromboCompileDraft extends Model
{
    public const PREVIEW_PREFIX = 'preview-';

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<TaromboSnapshot, $this> */
    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(TaromboSnapshot::class, 'tarombo_snapshot_id');
    }

    /** Private storage folder of this draft's layer images. */
    public static function directory(int $userId, int $snapshotId): string
    {
        return "tarombo-compile/{$userId}/{$snapshotId}";
    }

    public static function imagePath(int $userId, int $snapshotId, string $uuid): string
    {
        return self::directory($userId, $snapshotId)."/{$uuid}.png";
    }

    /** The composed look of this saved compile, shown in "Hasil Simpan". */
    public function previewPath(): string
    {
        return self::directory($this->user_id, $this->tarombo_snapshot_id).'/'.self::PREVIEW_PREFIX.$this->id.'.jpg';
    }

    /**
     * Uuids of the layer images used by any saved compile of this account and
     * snapshot; images outside this list can be deleted.
     *
     * @return array<int, string>
     */
    public static function usedImages(int $userId, int $snapshotId): array
    {
        return self::query()
            ->where('user_id', $userId)
            ->where('tarombo_snapshot_id', $snapshotId)
            ->get()
            ->flatMap(fn (self $draft) => self::storedImages($draft->state))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Uuids of the layer images the state refers to ("stored:<uuid>").
     *
     * @param  array<string, mixed>  $state
     * @return array<int, string>
     */
    public static function storedImages(array $state): array
    {
        return collect($state['layers'] ?? [])
            ->pluck('source')
            ->filter(fn ($source) => is_string($source) && str_starts_with($source, 'stored:'))
            ->map(fn (string $source) => substr($source, 7))
            ->unique()
            ->values()
            ->all();
    }

    protected function casts(): array
    {
        return [
            'state' => 'array',
        ];
    }
}
