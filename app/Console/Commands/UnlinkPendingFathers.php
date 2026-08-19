<?php

namespace App\Console\Commands;

use App\Models\Person;
use App\Services\ChainNumberingService;
use Illuminate\Console\Command;

class UnlinkPendingFathers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'people:unlink-pending-fathers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Detach families whose father is still unknown from any temporary root and recompute chains.';

    /**
     * Execute the console command.
     */
    public function handle(ChainNumberingService $service): int
    {
        $affected = Person::query()
            ->where('pending_father', true)
            ->whereNotNull('father_id')
            ->update(['father_id' => null]);

        if ($affected > 0) {
            $service->recomputeAll();
        }

        $this->info(sprintf('Unlinked %d pending family entr(y/ies) and recomputed chains.', $affected));

        return Command::SUCCESS;
    }
}
