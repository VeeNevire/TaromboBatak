<?php

namespace App\Policies;

use App\Models\ContactRequest;
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
        return $user->isStaff() || $user->accessibleMargaIds()->isNotEmpty();
    }

    public function view(User $user, Person $person): bool
    {
        return $user->isStaff()
            || ($person->marga_id !== null
                && ($user->accessibleMargaIds()->contains($person->marga_id)
                    || $user->approvedMargaAccessIds()->contains($person->marga_id)))
            || $this->hasContactRequestForClaimedPerson($user, $person);
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
        $createdByUser = (int) $person->created_by === (int) $user->id;
        $staffTreeExists = $person->familyTrees()
            ->whereHas('user', fn ($owner) => $owner->whereIn('role', ['admin', 'subadmin']))
            ->exists();

        return $this->view($user, $person)
            && ($ownedTreeExists || $createdByUser || (! $sharedTreeExists && ! $staffTreeExists))
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

    /** Allow viewing only the claimed identity associated with a contact request. */
    protected function hasContactRequestForClaimedPerson(User $user, Person $person): bool
    {
        return ContactRequest::query()
            ->whereIn('status', [ContactRequest::STATUS_PENDING, ContactRequest::STATUS_APPROVED])
            ->where(function ($requests) use ($user, $person) {
                $requests
                    ->where(function ($incoming) use ($user, $person) {
                        $incoming
                            ->where('recipient_id', $user->id)
                            ->whereHas('requester', fn ($requester) => $requester
                                ->where('current_person_id', $person->id));
                    })
                    ->orWhere(function ($outgoing) use ($user, $person) {
                        $outgoing
                            ->where('requester_id', $user->id)
                            ->whereHas('recipient', fn ($recipient) => $recipient
                                ->where('current_person_id', $person->id));
                    });
            })
            ->exists();
    }
}
