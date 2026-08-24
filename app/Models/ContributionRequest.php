<?php

namespace App\Models;

use Database\Factories\ContributionRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function matchedFather(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'matched_father_id');
    }

    public function subjectPerson(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'subject_person_id');
    }

    public function familyTree(): BelongsTo
    {
        return $this->belongsTo(FamilyTree::class);
    }

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
