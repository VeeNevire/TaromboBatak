<?php

namespace App\Models;

use Database\Factories\ContributionRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $requester_id
 * @property int $matched_father_id
 * @property int $subject_person_id
 * @property int|null $family_tree_id
 * @property array<int, int> $affected_person_ids
 * @property string $status
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $rejection_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $requester
 * @property-read Person $matchedFather
 * @property-read Person $subjectPerson
 * @property-read FamilyTree|null $familyTree
 * @property-read User|null $reviewer
 */
#[Fillable([
    'requester_id',
    'matched_father_id',
    'subject_person_id',
    'family_tree_id',
    'affected_person_ids',
    'status',
    'reviewed_by',
    'reviewed_at',
    'rejection_reason',
])]
class ContributionRequest extends Model
{
    /** @use HasFactory<ContributionRequestFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /** @return BelongsTo<Person, $this> */
    public function matchedFather(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'matched_father_id');
    }

    /** @return BelongsTo<Person, $this> */
    public function subjectPerson(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'subject_person_id');
    }

    /** @return BelongsTo<FamilyTree, $this> */
    public function familyTree(): BelongsTo
    {
        return $this->belongsTo(FamilyTree::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    protected function casts(): array
    {
        return [
            'affected_person_ids' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }
}
