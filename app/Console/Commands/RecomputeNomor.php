<?php

namespace App\Console\Commands;

use App\Models\Person;
use App\Services\TaromboNumberingService;
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
    protected $description = 'Recompute the auto silsilah numbers (nomor) for every patrilineal root lineage.';

    /**
     * Execute the console command.
     */
    public function handle(TaromboNumberingService $service): int
    {
        $roots = Person::query()
            ->whereNull('father_id')
            ->whereHas('children')
            ->orderBy('id')
            ->get();

        foreach ($roots as $root) {
            $service->recomputeFromAncestor($root);
        }

        $this->info(sprintf('Recomputed numbers for %d root lineage(s).', $roots->count()));

        return Command::SUCCESS;
    }
}
