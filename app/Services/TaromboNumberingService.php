<?php

namespace App\Services;

use App\Models\Person;

class TaromboNumberingService
{
    /**
     * Recompute the dotted silsilah number ("1", "1.2", "1.2.1", ...) for the
     * whole lineage starting from the given person's topmost ancestor.
     *
     * Manually overridden numbers (`nomor_manual`) are never touched. Spouses
     * / mothers (fatherless people who are not a father themselves) are left
     * without an automatic number.
     */
    public function recomputeFromAncestor(Person $person): void
    {
        $ancestor = $this->climbToAncestor($person);

        if ($ancestor === null) {
            return;
        }

        // Clear every automatic number below the ancestor first. This keeps the
        // unique constraint from clashing while siblings are renumbered (e.g.
        // when birth orders are swapped). The ancestor itself keeps its number.
        $ids = [];
        $this->collectAutoDescendantIds($ancestor, $ids);

        if ($ids !== []) {
            Person::query()->whereIn('id', array_keys($ids))->update(['nomor' => null]);
        }

        $visited = [];
        $this->recomputeSubtree($ancestor, $visited);
    }

    /**
     * Collect the ids of auto-numbered descendants of the given person.
     *
     * @param  array<int, true>  $ids
     */
    protected function collectAutoDescendantIds(Person $person, array &$ids): void
    {
        foreach ($person->children()->orderBy('birth_order')->get() as $child) {
            if (! $child->nomor_manual) {
                $ids[$child->id] = true;
            }

            $this->collectAutoDescendantIds($child, $ids);
        }
    }

    /**
     * Walk up the father chain to the topmost ancestor.
     */
    protected function climbToAncestor(Person $person): ?Person
    {
        $current = $person;
        $seen = [];

        while ($current->father_id !== null && ! isset($seen[$current->id])) {
            $seen[$current->id] = true;
            $father = $current->father;

            if ($father === null) {
                break;
            }

            $current = $father;
        }

        return $current;
    }

    /**
     * Depth-first pass assigning numbers: parent first, then children ordered
     * by birth order.
     *
     * @param  array<int, true>  $visited
     */
    protected function recomputeSubtree(Person $person, array &$visited): void
    {
        if (isset($visited[$person->id])) {
            return;
        }

        $visited[$person->id] = true;

        $this->assignNomor($person);

        $children = $person->children()
            ->orderBy('birth_order')
            ->orderBy('id')
            ->get();

        foreach ($children as $child) {
            $this->recomputeSubtree($child, $visited);
        }
    }

    /**
     * Assign the number for a single person unless it is manually overridden.
     *
     * Leaders (`is_leader`) get a flat sequential number along the lineage
     * ("1", "2", "3", ...). Everyone else inherits the father's number plus
     * their birth order ("1.2", "2.1", ...). Fatherless non-leaders who are a
     * father themselves become a new root.
     */
    protected function assignNomor(Person $person): void
    {
        if ($person->nomor_manual) {
            return;
        }

        if ($person->is_leader) {
            $nomor = (string) $this->leaderSequenceNumber($person);

            if ($nomor !== $person->nomor) {
                $person->nomor = $nomor;
                $person->save();
            }

            return;
        }

        if ($person->father_id !== null) {
            $father = $person->father;

            if ($father === null || $father->nomor === null) {
                return;
            }

            $birthOrder = (int) $person->birth_order;

            if ($birthOrder < 1) {
                return;
            }

            $nomor = $father->nomor.'.'.$birthOrder;

            if ($nomor !== $person->nomor) {
                $person->nomor = $nomor;
                $person->save();
            }

            return;
        }

        // Root: only patrilineal ancestors (people who are a father) get a number.
        if (! $person->children()->exists()) {
            return;
        }

        // Root numbers are stable once assigned.
        if ($person->nomor !== null) {
            return;
        }

        $nomor = $this->nextRootNumber();

        if ($nomor !== $person->nomor) {
            $person->nomor = $nomor;
            $person->save();
        }
    }

    /**
     * Flat lineage number for a leader: one more than the number of leader
     * ancestors above it. Falls back to the next free integer if taken.
     */
    protected function leaderSequenceNumber(Person $person): int
    {
        $count = 0;
        $current = $person;
        $seen = [];

        while ($current->father_id !== null && ! isset($seen[$current->id])) {
            $seen[$current->id] = true;
            $father = $current->father;

            if ($father === null) {
                break;
            }

            $current = $father;

            if ($current->is_leader) {
                $count++;
            }
        }

        $used = Person::query()
            ->where('is_leader', true)
            ->where('id', '!=', $person->id)
            ->whereNotNull('nomor')
            ->pluck('nomor')
            ->map(fn (string $nomor) => (int) $nomor)
            ->filter(fn (int $nomor) => $nomor > 0)
            ->values()
            ->all();

        $candidate = $count + 1;

        while (in_array($candidate, $used, true)) {
            $candidate++;
        }

        return $candidate;
    }

    /**
     * Next available root number (max existing numeric root + 1).
     */
    protected function nextRootNumber(): string
    {
        $last = Person::query()
            ->whereNull('father_id')
            ->whereNotNull('nomor')
            ->orderByRaw('CAST(nomor AS UNSIGNED) DESC')
            ->value('nomor');

        return (string) ((int) $last + 1);
    }
}
