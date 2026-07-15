<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Payment\TrialService;
use Illuminate\Console\Command;

class ExpireTrials extends Command
{
    protected $signature = 'trials:expire';

    protected $description = 'Expire ended cancelled or one-time subscription access';

    public function handle(TrialService $trialService): int
    {
        $expiredCount = $trialService->expireCancelledSubscriptions();
        $this->info("Expired {$expiredCount} ended subscription(s).");

        return Command::SUCCESS;
    }
}
