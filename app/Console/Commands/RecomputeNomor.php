<?php

namespace App\Console\Commands;

use App\Models\Person;
use App\Services\ChainNumberingService;
use Illuminate\Console\Command;

class RecomputeNomor extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'people:recompute-nomor';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recompute the auto silsilah chain numbers for every patrilineal root lineage.';

    /**
     * Execute the console command.
     */
    public function handle(ChainNumberingService $service): int
    {
        $roots = Person::query()
            ->whereNull('father_id')
            ->orderBy('id')
            ->get();

        $service->recomputeAll();

        $this->info(sprintf('Recomputed chain numbers for %d root lineage(s).', $roots->count()));

        return Command::SUCCESS;
    }
}
