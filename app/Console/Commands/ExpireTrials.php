<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Payment\TrialService;
use Illuminate\Console\Command;

class ExpireTrials extends Command
{
    protected $signature = 'trials:expire';

    protected $description = 'Expire cancelled subscriptions that have passed their current period end date';

    public function handle(TrialService $trialService): int
    {
        $cancelledCount = $trialService->expireCancelledSubscriptions();
        $this->info("Expired {$cancelledCount} cancelled subscription(s).");

        return Command::SUCCESS;
    }
}
