<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Payment\TrialService;
use Illuminate\Console\Command;

class ExpireTrials extends Command
{
    protected $signature = 'trials:expire';

    protected $description = 'Expire trials that have passed their trial_ends_at date';

    public function handle(TrialService $trialService): int
    {
        $count = $trialService->expireTrials();

        $this->info("Expired {$count} trial(s).");

        return Command::SUCCESS;
    }
}
