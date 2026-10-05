<?php

namespace App\Services;

use App\Models\Person;
use Illuminate\Database\Eloquent\Builder;

class TaromboStatisticsService
{
    /**
     * Count all generations below each identity, excluding the identity itself.
     *
     * @param  array<int, int>  $identityIds
     * @return array<int, int>
     */
    public function descendantCounts(array $identityIds): array
    {
        if ($identityIds === []) {
            return [];
        }

        $children = [];
        foreach (Person::query()->whereNotNull('father_id')->pluck('father_id', 'id') as $id => $fatherId) {
            $children[$fatherId][] = (int) $id;
        }

        $counts = [];
        foreach (array_unique($identityIds) as $identityId) {
            $seen = [$identityId => true];
            $queue = [$identityId];

            while ($queue !== []) {
                $current = array_pop($queue);
                foreach ($children[$current] ?? [] as $childId) {
                    if (! isset($seen[$childId])) {
                        $seen[$childId] = true;
                        $queue[] = $childId;
                    }
                }
            }

            $counts[$identityId] = count($seen) - 1;
        }

        return $counts;
    }

    /**
     * @param  Builder<Person>  $scope
     */
    public function maxGenerationDepth(Builder $scope, bool $includeExternalAncestors = false): int
    {
        $targetIds = (clone $scope)->pluck('id');

        if ($targetIds->isEmpty()) {
            return 0;
        }

        $parents = ($includeExternalAncestors ? Person::query() : clone $scope)
            ->pluck('father_id', 'id');
        $maximum = 1;

        foreach ($targetIds as $id) {
            $depth = 1;
            $current = (int) $id;
            $seen = [];

            while (isset($parents[$current]) && ! isset($seen[$current])) {
                $seen[$current] = true;
                $current = (int) $parents[$current];
                $depth++;
            }

            $maximum = max($maximum, $depth);
        }

        return $maximum;
    }
}
