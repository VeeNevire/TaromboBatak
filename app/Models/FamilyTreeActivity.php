<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $family_tree_id
 * @property int|null $owner_id
 * @property int|null $actor_id
 * @property string $tree_name
 * @property string $action
 * @property string $description
 * @property Carbon|null $created_at
 * @property-read FamilyTree|null $familyTree
 * @property-read User|null $owner
 * @property-read User|null $actor
 */
#[Fillable(['family_tree_id', 'owner_id', 'actor_id', 'tree_name', 'action', 'description'])]
class FamilyTreeActivity extends Model
{
    /** @return BelongsTo<FamilyTree, $this> */
    public function familyTree(): BelongsTo
    {
        return $this->belongsTo(FamilyTree::class);
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
