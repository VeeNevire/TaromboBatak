<?php

namespace App\Policies;

use App\Models\FamilyTree;
use App\Models\FamilyTreeShare;
use App\Models\User;

class FamilyTreePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, FamilyTree $familyTree): bool
    {
        return $this->manage($user, $familyTree)
            || $this->hasApprovedMargaAccess($user, $familyTree)
            || ($user->isContributor()
                && $familyTree->rootPerson()
                    ->whereIn('marga_id', $user->accessibleMargaIds())
                    ->exists())
            || $this->hasAcceptedShare($user, $familyTree);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isStaff() || $user->marga_id !== null;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, FamilyTree $familyTree): bool
    {
        return $this->manage($user, $familyTree);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, FamilyTree $familyTree): bool
    {
        return $this->manage($user, $familyTree);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, FamilyTree $familyTree): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, FamilyTree $familyTree): bool
    {
        return false;
    }

    public function manage(User $user, FamilyTree $familyTree): bool
    {
        return $user->isStaff() || $familyTree->user_id === $user->id;
    }

    public function share(User $user, FamilyTree $familyTree): bool
    {
        return $this->manage($user, $familyTree);
    }

    public function append(User $user, FamilyTree $familyTree): bool
    {
        return $this->manage($user, $familyTree)
            || $this->hasApprovedMargaAccess($user, $familyTree)
            || $this->hasAcceptedShare($user, $familyTree);
    }

    protected function hasApprovedMargaAccess(User $user, FamilyTree $familyTree): bool
    {
        if ($user->isStaff() || $user->isContributor()) {
            return false;
        }

        if ((int) $familyTree->approval_access_user_id === (int) $user->id
            && $familyTree->relationLoaded('user') && array_key_exists('has_approved_marga_node', $familyTree->getAttributes())) {
            return in_array($familyTree->user?->role, ['admin', 'subadmin'], true)
                && (bool) $familyTree->has_approved_marga_node;
        }

        return $user->approvedMargaAccessIds()->isNotEmpty()
            && $familyTree->user()->whereIn('role', ['admin', 'subadmin'])->exists()
            && $familyTree->nodes()
                ->whereHas('person', fn ($person) => $person
                    ->whereIn('marga_id', $user->approvedMargaAccessIds()))
                ->exists();
    }

    protected function hasAcceptedShare(User $user, FamilyTree $familyTree): bool
    {
        if ($familyTree->relationLoaded('shares')) {
            return $familyTree->shares->contains(fn ($share) => (int) $share->recipient_id === (int) $user->id
                && $share->status === FamilyTreeShare::STATUS_ACCEPTED);
        }

        return $familyTree->shares()->whereBelongsTo($user, 'recipient')
            ->where('status', FamilyTreeShare::STATUS_ACCEPTED)->exists();
    }
}
