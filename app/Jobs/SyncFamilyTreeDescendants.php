<?php

namespace App\Jobs;

use App\Models\FamilyTree;
use App\Services\FamilyTreeDescendantSyncService;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Materialize missing descendants into one tree and the versions built on it.
 *
 * A high ancestor can sit in many large trees, so this runs on the queue
 * instead of inside the request that changed the lineage. Uniqueness lasts
 * only until processing starts, so a change made during a running sync still
 * queues one more pass.
 */
class SyncFamilyTreeDescendants implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    // Waiting for the sync lock releases the job; only real failures count.
    public int $maxExceptions = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 30];

    public int $timeout = 600;

    public function __construct(public int $familyTreeId) {}

    /**
     * Execute the job.
     */
    public function handle(FamilyTreeDescendantSyncService $sync): void
    {
        $tree = FamilyTree::query()->find($this->familyTreeId);

        if ($tree === null) {
            return;
        }

        $sync->syncTreeAndDescendantVersions($tree);
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addHour();
    }

    public function uniqueId(): string
    {
        return (string) $this->familyTreeId;
    }

    /** @return array<int, object> */
    public function middleware(): array
    {
        // One sync at a time across all trees: versions share nodes and people
        // rows, so parallel workers syncing different trees still deadlock.
        return [
            (new WithoutOverlapping('family-tree-sync'))
                ->releaseAfter(5)
                ->expireAfter(900),
        ];
    }
}
