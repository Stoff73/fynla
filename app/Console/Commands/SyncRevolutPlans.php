<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SubscriptionPlan;
use App\Services\Payment\RevolutSubscriptionService;
use Illuminate\Console\Command;

class SyncRevolutPlans extends Command
{
    protected $signature = 'revolut:sync-plans';

    protected $description = 'Create or update Revolut subscription plans for each Fynla subscription plan';

    public function handle(RevolutSubscriptionService $service): int
    {
        $plans = SubscriptionPlan::active()->orderBy('sort_order')->get();

        if ($plans->isEmpty()) {
            $this->warn('No active subscription plans found.');

            return self::FAILURE;
        }

        $this->info("Syncing {$plans->count()} subscription plans to Revolut...");

        foreach ($plans as $plan) {
            try {
                $this->line("  Creating plan: {$plan->name} ({$plan->slug})");
                $result = $service->createSubscriptionPlan($plan);

                $planId = $result['id'];
                $variations = $result['variations'] ?? [];

                $this->info("    Revolut plan ID: {$planId}");
                $this->info("    Variations: " . count($variations));

                foreach ($variations as $i => $variation) {
                    $phase = $variations[$i]['phases'][0] ?? null;
                    $cycle = $phase['cycle_duration'] ?? 'unknown';
                    $amount = $phase['amount'] ?? 0;
                    $this->line("      Variation {$variation['id']}: {$cycle} @ {$amount} pence");
                }
            } catch (\Throwable $e) {
                $this->error("    Failed: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info('Revolut plan sync complete.');

        return self::SUCCESS;
    }
}
