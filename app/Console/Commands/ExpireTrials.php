<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Payment\TrialService;
use Illuminate\Console\Command;

class ExpireTrials extends Command
{
    protected $signature = 'trials:expire';

    protected $description = 'Expire trials and cancelled subscriptions that have passed their end date';

    public function handle(TrialService $trialService): int
    {
        $trialCount = $trialService->expireTrials();
        $this->info("Expired {$trialCount} trial(s).");

        $cancelledCount = $trialService->expireCancelledSubscriptions();
        $this->info("Expired {$cancelledCount} cancelled subscription(s).");

        return Command::SUCCESS;
    }
}
