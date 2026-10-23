<?php

namespace App\Services;

use App\Models\Person;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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
        Cache::lock('tarombo-chain-numbering', 120)->block(60, function () use ($person) {
            $root = $this->climbToRoot($person->fresh());

            if ($root === null) {
                return;
            }

            $visited = [];
            $this->recomputeSubtree($root, $visited);
        });
    }

    /**
     * Recompute only one person's lineage path and descendant branch. This is
     * used when moving an individual to a different father so their siblings
     * keep their existing positions and cached chains.
     */
    public function recomputeBranch(Person $person): void
    {
        $ancestors = [];
        $current = $person;
        $seen = [];

        while ($current->father_id !== null && ! isset($seen[$current->id])) {
            $seen[$current->id] = true;
            $father = $current->father;

            if ($father === null) {
                break;
            }

            $ancestors[] = $father;
            $current = $father;
        }

        foreach (array_reverse($ancestors) as $ancestor) {
            $this->assignChain($ancestor);
        }

        $visited = [];
        $this->recomputeSubtree($person, $visited);
    }

    /**
     * Recompute chains for every person in the database. Used after a full
     * re-seed so every chain is derived consistently.
     */
    public function recomputeAll(): void
    {
        Cache::lock('tarombo-chain-numbering', 120)->block(60, function () {
            $roots = Person::query()
                ->whereNull('father_id')
                ->orderBy('id')
                ->get();

            foreach ($roots as $root) {
                $visited = [];
                $this->recomputeSubtree($root, $visited);
            }
        });
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
     * Assign the subtree root's chain, then derive every descendant's chain in
     * memory. A lineage under a high ancestor holds thousands of people, so
     * descendants are loaded one generation per query and only changed
     * chains are written, in batches.
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

        $chains = [$person->id => $person->chain];
        $changed = [];
        $frontier = [$person->id];

        while ($frontier !== []) {
            $children = collect();

            foreach (array_chunk($frontier, 1000) as $fatherIds) {
                $children = $children->concat(Person::query()
                    ->whereIn('father_id', $fatherIds)
                    ->get(['id', 'father_id', 'birth_order', 'chain', 'pending_father']));
            }

            $frontier = [];

            foreach ($children as $child) {
                if (isset($visited[$child->id])) {
                    continue;
                }

                $visited[$child->id] = true;
                $chain = $this->descendantChain($child, $chains[$child->father_id] ?? null);
                $chains[$child->id] = $chain;
                $frontier[] = $child->id;

                if ($chain !== $child->chain) {
                    $changed[$child->id] = $chain;
                }
            }
        }

        $this->writeChains($changed);
    }

    /**
     * The chain assignChain() gives a person who has a father, computed from
     * the father's freshly derived chain instead of a query.
     */
    protected function descendantChain(Person $person, ?string $fatherChain): ?string
    {
        if ($person->pending_father || $fatherChain === null) {
            return null;
        }

        $birthOrder = (int) $person->birth_order;

        if ($birthOrder < 1) {
            return $person->chain;
        }

        return $fatherChain.'-'.$birthOrder;
    }

    /** @param array<int, string|null> $chains */
    protected function writeChains(array $chains): void
    {
        $now = now();

        $table = (new Person)->getTable();

        foreach (array_chunk($chains, 200, true) as $batch) {
            $cases = [];
            $bindings = [];

            foreach ($batch as $id => $chain) {
                $cases[] = 'WHEN ? THEN ?';
                array_push($bindings, $id, $chain);
            }

            $placeholders = implode(', ', array_fill(0, count($batch), '?'));

            DB::update(
                "UPDATE {$table} SET chain = CASE id ".implode(' ', $cases)." END, updated_at = ? WHERE id IN ({$placeholders})",
                [...$bindings, $now, ...array_keys($batch)],
            );
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
        if ($person->pending_father) {
            // Masih tertempel ke ayah sementara (data lama): jangan beri chain.
            if ($person->father_id !== null) {
                if ($person->chain !== null) {
                    $person->chain = null;
                    $person->save();
                }

                return;
            }

            // Root yang belum tersambung hanya diberi label cadangan ("-n")
            // ketika sudah punya anak. Begitu disambung ke ayah asli, flag
            // pending dihapus dan chain asli dihitung ulang dari ayah tersebut.
            if (! $person->children()->exists()) {
                if ($person->chain !== null) {
                    $person->chain = null;
                    $person->save();
                }

                return;
            }

            if (is_string($person->chain) && str_starts_with($person->chain, '-')) {
                return;
            }

            $chain = $this->nextPendingRootChain();

            if ($chain !== $person->chain) {
                $person->chain = $chain;
                $person->save();
            }

            return;
        }

        if ($person->father_id !== null) {
            $father = $person->father;

            if ($father === null || $father->chain === null) {
                if ($person->chain !== null) {
                    $person->chain = null;
                    $person->save();
                }

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
            if ($person->chain !== null) {
                $person->chain = null;
                $person->save();
            }

            return;
        }

        // Root chains are stable once assigned.
        if ($person->chain !== null) {
            return;
        }

        $chain = $this->nextRootChain();
        $person->chain = $chain;
        $person->save();
    }

    /**
     * Next available root chain (max existing numeric root + 1).
     */
    protected function nextRootChain(): string
    {
        $max = Person::query()
            ->whereNull('father_id')
            ->whereNotNull('chain')
            ->where('chain', 'not like', '%-%')
            ->pluck('chain')
            ->map(fn (string $chain) => (int) $chain)
            ->max() ?? 0;

        return (string) ($max + 1);
    }

    /**
     * Next available pending label ("-n") based on the highest numeric suffix
     * already in use. Pending labels are reserved (they never appear in real
     * chains, which are always numeric and dash-separated) so they never
     * collide.
     */
    protected function nextPendingRootChain(): string
    {
        $max = 0;

        foreach (Person::query()->where('chain', 'like', '-%')->pluck('chain') as $chain) {
            $parts = explode('-', $chain);

            if (count($parts) === 2 && $parts[0] === '') {
                $max = max($max, (int) $parts[1]);
            }
        }

        return '-'.($max + 1);
    }
}
