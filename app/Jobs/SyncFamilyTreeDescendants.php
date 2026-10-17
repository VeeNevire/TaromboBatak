<?php

namespace App\Jobs;

use App\Models\FamilyTree;
use App\Services\FamilyTreeDescendantSyncService;
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

    // A release while another worker syncs the tree also counts as a try.
    public int $tries = 5;

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

    public function uniqueId(): string
    {
        return (string) $this->familyTreeId;
    }

    /** @return array<int, object> */
    public function middleware(): array
    {
        // Two workers syncing the same tree lock the same rows and deadlock.
        return [
            (new WithoutOverlapping("family-tree-sync:{$this->familyTreeId}"))
                ->releaseAfter(30)
                ->expireAfter(900),
        ];
    }
}
