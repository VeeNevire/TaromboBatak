<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A keyword the news agent searches the web for.
 *
 * @property int $id
 * @property string $keyword
 * @property int|null $marga_id
 * @property bool $is_active
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Marga|null $marga
 */
#[Fillable(['keyword', 'marga_id', 'is_active', 'notes'])]
class MargaNewsTopic extends Model
{
    /** @return BelongsTo<Marga, $this> */
    public function marga(): BelongsTo
    {
        return $this->belongsTo(Marga::class);
    }

    /** @return HasMany<MargaNews, $this> */
    public function news(): HasMany
    {
        return $this->hasMany(MargaNews::class);
    }

    /** @return BelongsToMany<MargaNewsSource, $this> */
    public function sources(): BelongsToMany
    {
        return $this->belongsToMany(MargaNewsSource::class, 'marga_news_source_topic');
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
