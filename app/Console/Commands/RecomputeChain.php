<?php

namespace App\Console\Commands;

use App\Models\Person;
use App\Services\ChainNumberingService;
use Illuminate\Console\Command;

class RecomputeChain extends Command
{
    /** @var string */
    protected $signature = 'people:recompute-chain';

    /** @var string */
    protected $description = 'Recompute chain silsilah untuk seluruh rumpun patrilineal.';

    public function handle(ChainNumberingService $service): int
    {
        $rootCount = Person::query()->whereNull('father_id')->count();

        $service->recomputeAll();

        $this->info(sprintf('Recomputed chain untuk %d rumpun root.', $rootCount));

        return Command::SUCCESS;
    }
}
