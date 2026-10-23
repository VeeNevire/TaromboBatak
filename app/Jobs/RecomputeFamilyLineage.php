<?php

namespace App\Jobs;

use App\Models\FamilyTree;
use App\Models\Person;
use App\Services\ChainNumberingService;
use App\Services\FamilyEntryService;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Recompute the chain numbers of the lineages a family form touched, then
 * copy them into the account's family-tree nodes.
 *
 * A lineage under a high ancestor holds thousands of people, so this runs on
 * the queue instead of inside the request that saved the family.
 */
class RecomputeFamilyLineage implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    // Waiting for the numbering lock releases the job; only real failures count.
    public int $maxExceptions = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 30];

    public int $timeout = 600;

    /**
     * @param  array<int, int>  $branchPersonIds  People moved to another father: only their own path and branch change.
     * @param  array<int, int>  $lineagePersonIds  People whose whole patrilineal lineage is recomputed.
     * @param  array<int, int>  $familyTreeIds  Trees whose nodes copy the new chains.
     */
    public function __construct(
        public array $branchPersonIds = [],
        public array $lineagePersonIds = [],
        public array $familyTreeIds = [],
    ) {}

    /**
     * Execute the job.
     */
    public function handle(ChainNumberingService $numbering, FamilyEntryService $entries): void
    {
        Person::query()
            ->whereKey($this->branchPersonIds)
            ->get()
            ->each(fn (Person $person) => $numbering->recomputeBranch($person));

        Person::query()
            ->whereKey($this->lineagePersonIds)
            ->get()
            ->each(fn (Person $person) => $numbering->recomputeFromAncestor($person));

        FamilyTree::query()
            ->whereKey($this->familyTreeIds)
            ->get()
            ->each(function (FamilyTree $tree) use ($entries): void {
                $entries->syncTreeNodes($tree);
                $tree->touch();
            });
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addHour();
    }

    public function uniqueId(): string
    {
        return md5(json_encode([
            collect($this->branchPersonIds)->sort()->values(),
            collect($this->lineagePersonIds)->sort()->values(),
            collect($this->familyTreeIds)->sort()->values(),
        ]));
    }

    /** @return array<int, object> */
    public function middleware(): array
    {
        // Every lineage shares one numbering lock; run one recompute at a time.
        return [
            (new WithoutOverlapping('family-lineage-recompute'))
                ->releaseAfter(5)
                ->expireAfter(900),
        ];
    }
}
