<?php

namespace App\Policies;

use App\Models\ContributionRequest;
use App\Models\FamilyTreeShare;
use App\Models\Person;
use App\Models\User;

class PersonPolicy
{
    /** @var array<int, true>|null */
    protected ?array $lockedAncestorIds = null;

    public function create(User $user): bool
    {
        return $user->isStaff() || $user->marga_id !== null;
    }

    public function view(User $user, Person $person): bool
    {
        return $user->isStaff()
            || ($user->marga_id !== null
                && $person->marga_id === $user->marga_id);
    }

    public function update(User $user, Person $person): bool
    {
        if ($user->isStaff()) {
            return true;
        }

        $sharedTreeExists = $person->familyTrees()
            ->whereHas('shares', fn ($shares) => $shares
                ->whereBelongsTo($user, 'recipient')
                ->where('status', FamilyTreeShare::STATUS_ACCEPTED))
            ->exists();
        $ownedTreeExists = $person->familyTrees()
            ->where('family_trees.user_id', $user->id)
            ->exists();

        return $this->view($user, $person)
            && (! $sharedTreeExists || $ownedTreeExists)
            && ! $this->isLockedAncestor($person);
    }

    public function delete(User $user, Person $person): bool
    {
        return $user->isStaff();
    }

    protected function isLockedAncestor(Person $person): bool
    {
        if ($this->lockedAncestorIds === null) {
            $this->lockedAncestorIds = [];
            $frontier = ContributionRequest::query()
                ->whereIn('status', [ContributionRequest::STATUS_PENDING, ContributionRequest::STATUS_APPROVED])
                ->pluck('matched_father_id')
                ->unique()
                ->values();

            while ($frontier->isNotEmpty()) {
                $people = Person::query()->whereIn('id', $frontier)->get(['id', 'father_id']);
                $frontier = collect();

                foreach ($people as $ancestor) {
                    if (isset($this->lockedAncestorIds[$ancestor->id])) {
                        continue;
                    }

                    $this->lockedAncestorIds[$ancestor->id] = true;

                    if ($ancestor->father_id !== null) {
                        $frontier->push($ancestor->father_id);
                    }
                }

                $frontier = $frontier->unique()->values();
            }
        }

        return isset($this->lockedAncestorIds[$person->id]);
    }
}
