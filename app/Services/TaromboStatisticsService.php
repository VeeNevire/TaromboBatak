<?php

namespace App\Services;

use App\Models\Person;
use Illuminate\Database\Eloquent\Builder;

class TaromboStatisticsService
{
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
