<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Services\Payment\RevolutSubscriptionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckOverdueSubscriptions extends Command
{
    protected $signature = 'subscriptions:check-overdue';

    protected $description = 'Safety net: check active subscriptions past their period end for missed webhooks';

    public function handle(RevolutSubscriptionService $service): int
    {
        $overdueSubscriptions = Subscription::where('status', 'active')
            ->whereNotNull('revolut_subscription_id')
            ->where('current_period_end', '<', now())
            ->get();

        if ($overdueSubscriptions->isEmpty()) {
            $this->info('No overdue subscriptions found.');

            return self::SUCCESS;
        }

        $this->warn("Found {$overdueSubscriptions->count()} potentially overdue subscription(s).");

        foreach ($overdueSubscriptions as $subscription) {
            try {
                $revolutSub = $service->getSubscription($subscription->revolut_subscription_id);
                $revolutState = $revolutSub['state'] ?? 'unknown';

                $this->line("  Sub #{$subscription->id} (User #{$subscription->user_id}): Revolut state = {$revolutState}");

                if ($revolutState === 'overdue') {
                    $subscription->update(['status' => 'past_due']);
                    $this->warn("    Updated to past_due");
                    Log::info('Overdue check: subscription marked past_due', [
                        'subscription_id' => $subscription->id,
                        'revolut_subscription_id' => $subscription->revolut_subscription_id,
                    ]);
                } elseif ($revolutState === 'cancelled') {
                    $subscription->update([
                        'status' => 'cancelled',
                        'cancelled_at' => now(),
                        'auto_renew' => false,
                    ]);
                    $this->warn("    Updated to cancelled");
                } elseif ($revolutState === 'active') {
                    // Revolut says active but our period expired — likely a webhook was missed
                    // Check cycles to update period dates
                    $this->line("    Revolut says active — checking cycles...");
                    $cycles = $service->getSubscriptionCycles($subscription->revolut_subscription_id, 1);
                    $latestCycle = ($cycles['cycles'] ?? [])[0] ?? null;

                    if ($latestCycle && isset($latestCycle['end_date'])) {
                        $subscription->update([
                            'current_period_start' => $latestCycle['start_date'],
                            'current_period_end' => $latestCycle['end_date'],
                        ]);
                        $this->info("    Period updated from cycle data");
                    }
                }
            } catch (\Throwable $e) {
                $this->error("  Sub #{$subscription->id}: API error — {$e->getMessage()}");
                Log::error('Overdue check: API error', [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return self::SUCCESS;
    }
}
