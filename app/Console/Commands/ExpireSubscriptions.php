<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Payment\SubscriptionExpiryService;
use Illuminate\Console\Command;

class ExpireSubscriptions extends Command
{
    protected $signature = 'subscriptions:expire';

    protected $description = 'Expire paid subscription access after its entitled period ends';

    public function handle(SubscriptionExpiryService $subscriptionExpiryService): int
    {
        $expiredCount = $subscriptionExpiryService->expireCancelledSubscriptions();
        $this->info("Expired {$expiredCount} ended subscription(s).");

        return Command::SUCCESS;
    }
}
