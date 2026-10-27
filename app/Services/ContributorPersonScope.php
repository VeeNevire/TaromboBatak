<?php

namespace App\Services;

use App\Models\Marga;
use App\Models\Person;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Decides which people sit in a contributor's marga trees: anyone whose marga
 * the contributor manages, a managed marga's identity person, and every
 * patrilineal descendant of those people, whatever marga they carry.
 */
class ContributorPersonScope
{
    /** @var array<int, Collection<int, int>> */
    protected array $margaIds = [];

    /** @var array<int, array<int, true>> */
    protected array $identityIds = [];

    /** @var array<int, int|null> */
    protected array $fathers = [];

    /** @var array<int, int|null> */
    protected array $margas = [];

    /** @var array<int, array<int, bool>> */
    protected array $managed = [];

    public function manages(User $user, Person $person): bool
    {
        return in_array($person->id, $this->managedIds($user, collect([$person])), true);
    }

    /**
     * @param  iterable<int, Person>  $people
     * @return array<int, int>
     */
    public function managedIds(User $user, iterable $people): array
    {
        if (! $user->isContributor()) {
            return [];
        }

        $people = collect($people);
        foreach ($people as $person) {
            $this->fathers[$person->id] = $person->father_id;
            $this->margas[$person->id] = $person->marga_id;
        }
        $this->loadAncestors($people->pluck('father_id'));

        return $people
            ->filter(fn (Person $person) => $this->resolve($user, $person->id))
            ->map(fn (Person $person) => $person->id)
            ->values()
            ->all();
    }

    /**
     * Load the father chain level by level, one query per generation.
     *
     * @param  Collection<int, int|null>  $fatherIds
     */
    protected function loadAncestors(Collection $fatherIds): void
    {
        $missing = $fatherIds->filter()->reject(fn ($id) => array_key_exists($id, $this->fathers))->unique();

        while ($missing->isNotEmpty()) {
            $ancestors = Person::query()->whereIn('id', $missing)->get(['id', 'father_id', 'marga_id']);
            foreach ($missing as $id) {
                $this->fathers[$id] = null;
                $this->margas[$id] = null;
            }
            foreach ($ancestors as $ancestor) {
                $this->fathers[$ancestor->id] = $ancestor->father_id;
                $this->margas[$ancestor->id] = $ancestor->marga_id;
            }
            $missing = $ancestors->pluck('father_id')->filter()
                ->reject(fn ($id) => array_key_exists($id, $this->fathers))->unique();
        }
    }

    protected function resolve(User $user, int $personId): bool
    {
        $margaIds = $this->margaIds[$user->id] ??= $user->accessibleMargaIds();
        $identityIds = $this->identityIds[$user->id] ??= Marga::query()
            ->whereKey($margaIds)
            ->whereNotNull('identity_person_id')
            ->pluck('identity_person_id')
            ->mapWithKeys(fn ($id) => [(int) $id => true])
            ->all();

        // Walk up until an answer is known; malformed cycles stop at a seen id.
        $chain = [];
        $current = $personId;
        $result = false;
        while ($current !== null && ! isset($chain[$current])) {
            if (isset($this->managed[$user->id][$current])) {
                $result = $this->managed[$user->id][$current];
                break;
            }
            $chain[$current] = true;
            $marga = $this->margas[$current] ?? null;
            if (isset($identityIds[$current]) || ($marga !== null && $margaIds->contains((int) $marga))) {
                $result = true;
                break;
            }
            $current = $this->fathers[$current] ?? null;
        }

        foreach (array_keys($chain) as $id) {
            $this->managed[$user->id][$id] = $result;
        }

        return $result;
    }
}
