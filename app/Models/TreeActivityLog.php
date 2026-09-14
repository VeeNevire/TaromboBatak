<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $person_id
 * @property int|null $family_tree_id
 * @property int|null $actor_id
 * @property int|null $marga_id
 * @property string $action
 * @property string|null $protection_scope
 * @property string $summary
 * @property array<string, mixed>|null $details
 * @property Carbon|null $created_at
 * @property-read Person|null $person
 * @property-read FamilyTree|null $familyTree
 * @property-read User|null $actor
 * @property-read Marga|null $marga
 */
#[Fillable(['person_id', 'family_tree_id', 'actor_id', 'marga_id', 'action', 'protection_scope', 'summary', 'details'])]
class TreeActivityLog extends Model
{
    /** @return BelongsTo<Person, $this> */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /** @return BelongsTo<FamilyTree, $this> */
    public function familyTree(): BelongsTo
    {
        return $this->belongsTo(FamilyTree::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** @return BelongsTo<Marga, $this> */
    public function marga(): BelongsTo
    {
        return $this->belongsTo(Marga::class);
    }

    protected function casts(): array
    {
        return ['details' => 'array'];
    }
}
