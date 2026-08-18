<?php

namespace App\Services;

use App\Models\Person;

class ChainNumberingService
{
    /**
     * Recompute the chain for the whole patrilineal lineage rooted at the
     * topmost ancestor of the given person.
     *
     * Chains follow the format described in tarombo-batak-chain-numbering.md:
     * a root ancestor gets a single flat number ("1", "2", ...), every child
     * appends its birth order after a dash ("1-1", "1-2", ...). The chain is
     * a cache derived from `father_id` + `birth_order`; the father link stays
     * the source of truth.
     *
     * Roots without children (spouses / mothers) never receive a chain.
     */
    public function recomputeFromAncestor(Person $person): void
    {
        $root = $this->climbToRoot($person);

        if ($root === null) {
            return;
        }

        $visited = [];
        $this->recomputeSubtree($root, $visited);
    }

    /**
     * Recompute chains for every person in the database. Used after a full
     * re-seed so every chain is derived consistently.
     */
    public function recomputeAll(): void
    {
        $roots = Person::query()
            ->whereNull('father_id')
            ->orderBy('id')
            ->get();

        foreach ($roots as $root) {
            $visited = [];
            $this->recomputeSubtree($root, $visited);
        }
    }

    /**
     * Walk up the father chain to the topmost ancestor.
     */
    protected function climbToRoot(Person $person): ?Person
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
     * Depth-first pass assigning chains: parent first, then children ordered
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

        $this->assignChain($person);

        $children = $person->children()
            ->orderBy('birth_order')
            ->orderBy('id')
            ->get();

        foreach ($children as $child) {
            $this->recomputeSubtree($child, $visited);
        }
    }

    /**
     * Assign the chain for a single person.
     *
     * A root (no father) that has children becomes a new patrilineal line and
     * gets a flat root number. Everyone else inherits the father's chain plus
     * their own birth order.
     */
    protected function assignChain(Person $person): void
    {
        if ($person->father_id !== null) {
            $father = $person->father;

            if ($father === null || $father->chain === null) {
                return;
            }

            $birthOrder = (int) $person->birth_order;

            if ($birthOrder < 1) {
                return;
            }

            $chain = $father->chain.'-'.$birthOrder;

            if ($chain !== $person->chain) {
                $person->chain = $chain;
                $person->save();
            }

            return;
        }

        // Root: only patrilineal ancestors (people who are a father) get a chain.
        if (! $person->children()->exists()) {
            return;
        }

        // Root chains are stable once assigned.
        if ($person->chain !== null) {
            return;
        }

        $chain = $this->nextRootChain();

        if ($chain !== $person->chain) {
            $person->chain = $chain;
            $person->save();
        }
    }

    /**
     * Next available root chain (max existing numeric root + 1).
     */
    protected function nextRootChain(): string
    {
        $last = Person::query()
            ->whereNull('father_id')
            ->whereNotNull('chain')
            ->orderByRaw('CAST(chain AS UNSIGNED) DESC')
            ->value('chain');

        return (string) ((int) $last + 1);
    }
}
