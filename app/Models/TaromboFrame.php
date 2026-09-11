<?php

namespace App\Models;

use Database\Factories\TaromboFrameFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $path
 * @property int $canvas_width
 * @property int $canvas_height
 * @property int $area_x
 * @property int $area_y
 * @property int $area_width
 * @property int $area_height
 * @property bool $is_active
 */
#[Fillable(['name', 'path', 'canvas_width', 'canvas_height', 'area_x', 'area_y', 'area_width', 'area_height', 'is_active'])]
class TaromboFrame extends Model
{
    /** @use HasFactory<TaromboFrameFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @param Builder<TaromboFrame> $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** @return HasMany<TaromboSnapshot, $this> */
    public function snapshots(): HasMany
    {
        return $this->hasMany(TaromboSnapshot::class);
    }
}
