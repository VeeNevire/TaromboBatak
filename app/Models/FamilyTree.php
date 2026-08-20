<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $root_person_id
 * @property string|null $name
 * @property string|null $description
 * @property string|null $source_name
 * @property string|null $source_url
 * @property int|null $based_on_id
 * @property bool $is_primary
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Person|null $rootPerson
 * @property-read FamilyTree|null $basedOn
 * @property-read Collection<int, FamilyTreeNode> $nodes
 */
#[Fillable(['user_id', 'root_person_id', 'name', 'description', 'source_name', 'source_url', 'based_on_id', 'is_primary'])]
class FamilyTree extends Model
{
    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function rootPerson(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'root_person_id');
    }

    /**
     * @return BelongsToMany<Person, $this>
     */
    public function people(): BelongsToMany
    {
        return $this->belongsToMany(Person::class);
    }

    /**
     * @return BelongsTo<FamilyTree, $this>
     */
    public function basedOn(): BelongsTo
    {
        return $this->belongsTo(self::class, 'based_on_id');
    }

    /**
     * @return HasMany<FamilyTreeNode, $this>
     */
    public function nodes(): HasMany
    {
        return $this->hasMany(FamilyTreeNode::class);
    }

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }
}
