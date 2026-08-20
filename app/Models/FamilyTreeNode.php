<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A person's placement in one family-tree version. Parentage and numbering
 * belong here so one person can appear differently in another version.
 *
 * @property int $id
 * @property int $family_tree_id
 * @property int $person_id
 * @property int|null $father_node_id
 * @property int|null $mother_node_id
 * @property int|null $birth_order
 * @property int|null $sibling_count
 * @property string|null $chain
 * @property bool $pending_father
 * @property-read FamilyTree $familyTree
 * @property-read Person $person
 * @property-read FamilyTreeNode|null $fatherNode
 * @property-read FamilyTreeNode|null $motherNode
 */
#[Fillable(['family_tree_id', 'person_id', 'father_node_id', 'mother_node_id', 'birth_order', 'sibling_count', 'chain', 'pending_father'])]
class FamilyTreeNode extends Model
{
    /** @return BelongsTo<FamilyTree, $this> */
    public function familyTree(): BelongsTo
    {
        return $this->belongsTo(FamilyTree::class);
    }

    /** @return BelongsTo<Person, $this> */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /** @return BelongsTo<FamilyTreeNode, $this> */
    public function fatherNode(): BelongsTo
    {
        return $this->belongsTo(self::class, 'father_node_id');
    }

    /** @return BelongsTo<FamilyTreeNode, $this> */
    public function motherNode(): BelongsTo
    {
        return $this->belongsTo(self::class, 'mother_node_id');
    }

    /** @return HasMany<FamilyTreeNode, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'father_node_id');
    }

    protected function casts(): array
    {
        return ['pending_father' => 'boolean'];
    }
}
