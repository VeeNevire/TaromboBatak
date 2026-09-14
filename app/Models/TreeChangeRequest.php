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
 * @property int $requester_id
 * @property int|null $reviewer_id
 * @property int|null $marga_id
 * @property string $action
 * @property string $protection_scope
 * @property array<string, mixed>|null $payload
 * @property string $status
 * @property Carbon|null $reviewed_at
 * @property string|null $rejection_reason
 * @property-read Person|null $person
 * @property-read FamilyTree|null $familyTree
 * @property-read User $requester
 * @property-read User|null $reviewer
 */
#[Fillable(['person_id', 'family_tree_id', 'requester_id', 'reviewer_id', 'marga_id', 'action', 'protection_scope', 'payload', 'status', 'reviewed_at', 'rejection_reason'])]
class TreeChangeRequest extends Model
{
    public const ACTION_UPDATE = 'update';

    public const ACTION_DELETE = 'delete';

    public const SCOPE_CONTRIBUTOR = 'contributor';

    public const SCOPE_SHARED_TREE_OWNER = 'shared_tree_owner';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

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
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }
}
