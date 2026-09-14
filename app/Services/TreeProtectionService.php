<?php

namespace App\Services;

use App\Models\ContributionRequest;
use App\Models\FamilyTree;
use App\Models\FamilyTreeShare;
use App\Models\Person;
use App\Models\TreeChangeRequest;
use App\Models\User;
use Illuminate\Support\Collection;

class TreeProtectionService
{
    /**
     * Return the approval context required before this account may modify a
     * protected name, or null when the actor is already an authorized reviewer.
     *
     * @return array{scope: string, marga_id: int|null, reviewer_id: int|null, family_tree_id: int|null}|null
     */
    public function approvalFor(User $actor, Person $person): ?array
    {
        $sharedTree = $this->sharedTreeAddedByAnotherAccount($actor, $person);

        if ($sharedTree !== null) {
            return [
                'scope' => TreeChangeRequest::SCOPE_SHARED_TREE_OWNER,
                'marga_id' => $person->marga_id,
                'reviewer_id' => $sharedTree->user_id,
                'family_tree_id' => $sharedTree->id,
            ];
        }

        $approvedContribution = $this->approvedContributionFor($person);

        if ($approvedContribution === null) {
            return null;
        }

        $reviewMargaId = $approvedContribution->matchedFather?->marga_id;
        $canReview = $actor->isAdmin()
            || ($actor->isContributor()
                && $reviewMargaId !== null
                && $actor->accessibleMargaIds()->contains($reviewMargaId));

        if ($canReview) {
            return null;
        }

        return [
            'scope' => TreeChangeRequest::SCOPE_CONTRIBUTOR,
            'marga_id' => $reviewMargaId,
            'reviewer_id' => null,
            'family_tree_id' => $approvedContribution->family_tree_id,
        ];
    }

    /** @return array{scope: string, marga_id: int|null, reviewer_id: int|null, family_tree_id: int|null}|null */
    public function contextForLog(Person $person, ?FamilyTree $familyTree = null): ?array
    {
        if ($familyTree !== null && $familyTree->shares()
            ->where('status', FamilyTreeShare::STATUS_ACCEPTED)
            ->exists()) {
            return [
                'scope' => TreeChangeRequest::SCOPE_SHARED_TREE_OWNER,
                'marga_id' => $person->marga_id,
                'reviewer_id' => $familyTree->user_id,
                'family_tree_id' => $familyTree->id,
            ];
        }

        $approvedContribution = $this->approvedContributionFor($person);

        if ($approvedContribution === null) {
            return null;
        }

        return [
            'scope' => TreeChangeRequest::SCOPE_CONTRIBUTOR,
            'marga_id' => $approvedContribution->matchedFather?->marga_id,
            'reviewer_id' => null,
            'family_tree_id' => $approvedContribution->family_tree_id,
        ];
    }

    private function sharedTreeAddedByAnotherAccount(User $actor, Person $person): ?FamilyTree
    {
        if ($actor->isAdmin()) {
            return null;
        }

        return FamilyTree::query()
            ->where('user_id', '!=', $actor->id)
            ->whereHas('shares', fn ($shares) => $shares
                ->whereBelongsTo($actor, 'recipient')
                ->where('status', FamilyTreeShare::STATUS_ACCEPTED))
            ->whereHas('nodes', fn ($nodes) => $nodes
                ->where('person_id', $person->id)
                ->whereHas('person', fn ($people) => $people->where('created_by', $actor->id)))
            ->first();
    }

    private function approvedContributionFor(Person $person): ?ContributionRequest
    {
        $ancestorIds = $this->ancestorIds($person);

        if ($ancestorIds->isEmpty()) {
            return null;
        }

        return ContributionRequest::query()
            ->with('matchedFather:id,marga_id')
            ->where('status', ContributionRequest::STATUS_APPROVED)
            ->whereIn('matched_father_id', $ancestorIds)
            ->latest('reviewed_at')
            ->first();
    }

    /** @return Collection<int, int> */
    private function ancestorIds(Person $person): Collection
    {
        $ids = collect();
        $current = $person;
        $seen = [];

        while ($current !== null && ! isset($seen[$current->id])) {
            $seen[$current->id] = true;
            $ids->push($current->id);
            $current = $current->father_id === null
                ? null
                : Person::query()->find($current->father_id);
        }

        return $ids;
    }
}
