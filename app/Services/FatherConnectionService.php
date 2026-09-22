<?php

namespace App\Services;

use App\Models\ContributionRequest;
use App\Models\FamilyTree;
use App\Models\Person;
use App\Models\User;
use App\Notifications\FatherMatchSubmitted;
use Illuminate\Validation\ValidationException;

class FatherConnectionService
{
    public function __construct(
        private readonly FamilyTreeDescendantSyncService $sync,
    ) {}

    /**
     * Connect $subject to $father as a global relationship.
     *
     * Written immediately (and propagated to every tree) when $createdBy owns a
     * tree containing $father; otherwise a pending ContributionRequest is
     * created and $subject's global father is left untouched until approval.
     *
     * @return ContributionRequest|null the pending request, or null when connected immediately
     */
    public function connect(Person $subject, Person $father, int $createdBy, ?FamilyTree $contextTree = null): ?ContributionRequest
    {
        if (in_array($father->id, $subject->ineligibleFatherIds(), true)) {
            throw ValidationException::withMessages([
                'father.id' => 'Relasi ayah akan membentuk siklus silsilah.',
            ]);
        }

        if ($this->ownsFamilyTreeContaining($createdBy, $father)) {
            $subject->update(['father_id' => $father->id, 'pending_father' => false]);
            $this->syncTreesContaining($father, $contextTree);

            return null;
        }

        $existing = ContributionRequest::query()
            ->where('subject_person_id', $subject->id)
            ->where('matched_father_id', $father->id)
            ->where('status', ContributionRequest::STATUS_PENDING)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $contribution = ContributionRequest::create([
            'requester_id' => $createdBy,
            'matched_father_id' => $father->id,
            'subject_person_id' => $subject->id,
            'family_tree_id' => $contextTree?->id,
            'affected_person_ids' => [$subject->id],
            'status' => ContributionRequest::STATUS_PENDING,
        ]);
        $contribution->load(['requester', 'subjectPerson', 'matchedFather']);
        $subject->update(['pending_father' => true]);

        User::query()
            ->whereIn('role', ['contributor_main', 'contributor_member'])
            ->where('marga_id', $father->marga_id)
            ->each(fn (User $contributor) => $contributor->notify(new FatherMatchSubmitted($contribution)));

        return $contribution;
    }

    public function ownsFamilyTreeContaining(?int $userId, Person $person): bool
    {
        if ($userId === null) {
            return false;
        }

        return FamilyTree::query()
            ->where('user_id', $userId)
            ->where(fn ($query) => $query
                ->where('root_person_id', $person->id)
                ->orWhereHas('people', fn ($people) => $people->whereKey($person))
                ->orWhereHas('nodes', fn ($nodes) => $nodes->where('person_id', $person->id)))
            ->exists();
    }

    private function syncTreesContaining(Person $father, ?FamilyTree $contextTree): void
    {
        $trees = FamilyTree::query()
            ->where(fn ($query) => $query
                ->where('root_person_id', $father->id)
                ->orWhereHas('people', fn ($people) => $people->whereKey($father))
                ->orWhereHas('nodes', fn ($nodes) => $nodes->where('person_id', $father->id)))
            ->get();

        if ($contextTree !== null) {
            $trees->push($contextTree);
        }

        $trees->unique('id')->each(fn (FamilyTree $tree) => $this->sync->syncTreeAndDescendantVersions($tree));
    }
}
