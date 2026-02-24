<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SubscriptionPlan;
use App\Services\Payment\RevolutService;
use Illuminate\Console\Command;

class SeedRevolutPlans extends Command
{
    protected $signature = 'revolut:seed-plans {--force : Recreate plans even if already seeded}';

    protected $description = 'Create Revolut subscription plans via the API and store plan/variation IDs locally';

    public function handle(RevolutService $revolutService): int
    {
        $plans = SubscriptionPlan::active()->orderBy('sort_order')->get();

        if ($plans->isEmpty()) {
            $this->error('No active subscription plans found. Run SubscriptionPlanSeeder first.');

            return Command::FAILURE;
        }

        foreach ($plans as $plan) {
            if ($plan->revolut_plan_id && ! $this->option('force')) {
                $this->line("  Skipping {$plan->name} (already seeded: {$plan->revolut_plan_id})");

                continue;
            }

            $this->info("Creating Revolut plan: {$plan->name}");

            try {
                $revolutPlan = $revolutService->createPlan(
                    $plan->name,
                    $plan->monthly_price,
                    $plan->yearly_price,
                    $plan->trial_days
                );
            } catch (\RuntimeException $e) {
                $this->error("  Failed: {$e->getMessage()}");

                return Command::FAILURE;
            }

            $revolutPlanId = $revolutPlan['id'];
            $variations = $revolutPlan['variations'] ?? [];

            $monthlyVariationId = null;
            $yearlyVariationId = null;

            foreach ($variations as $variation) {
                $cycleDuration = $variation['phases'][0]['cycle_duration'] ?? null;
                if ($cycleDuration === 'P1M') {
                    $monthlyVariationId = $variation['id'];
                } elseif ($cycleDuration === 'P1Y') {
                    $yearlyVariationId = $variation['id'];
                }
            }

            $plan->update([
                'revolut_plan_id' => $revolutPlanId,
                'revolut_monthly_variation_id' => $monthlyVariationId,
                'revolut_yearly_variation_id' => $yearlyVariationId,
            ]);

            $this->line("  Plan ID: {$revolutPlanId}");
            $this->line("  Monthly variation: {$monthlyVariationId}");
            $this->line("  Yearly variation: {$yearlyVariationId}");
        }

        $this->info('All Revolut plans seeded successfully.');

        return Command::SUCCESS;
    }
}
