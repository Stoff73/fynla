<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Mortgage;
use App\Services\Mobile\PushNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendMortgageRateAlerts extends Command
{
    protected $signature = 'notifications:mortgage-rate-alerts';

    protected $description = 'Send push notifications for fixed rate mortgages expiring at 90/60/30 days';

    /**
     * System-scope read — iterates ALL users' fixed-rate mortgages by rate-expiry
     * date to dispatch push notifications. MortgageStore's contract is user-scoped
     * (every read method takes a User), so it cannot express "every fixed-rate
     * mortgage in the system whose rate_fix_end_date is on $targetDate". Keeping
     * this as a raw Eloquent read matches the cross-link KEEP precedent in
     * CrossModuleAssetAggregator::getMortgages (PR 5b).
     *
     * Reads are not policed by MortgageStoreBoundaryTest (writes only), so this
     * raw read does not require an allowlist entry.
     */
    public function handle(PushNotificationService $pushService): int
    {
        $thresholds = [90, 60, 30];
        $count = 0;

        foreach ($thresholds as $days) {
            $targetDate = now()->addDays($days)->toDateString();

            $mortgages = Mortgage::where('rate_type', 'fixed')
                ->whereDate('rate_fix_end_date', $targetDate)
                ->with('property')
                ->get();

            foreach ($mortgages as $mortgage) {
                $userId = $mortgage->user_id;

                if (! $pushService->shouldSend($userId, 'mortgage_rate_alerts')) {
                    continue;
                }

                try {
                    $address = $mortgage->property?->address_line_1 ?? 'your property';
                    $message = "Your fixed rate on {$address} expires in {$days} days. Now might be a good time to review your options.";

                    $pushService->sendToUser($userId, 'Mortgage Rate Alert', $message, [
                        'type' => 'mortgage_rate_alert',
                        'deepLink' => '/m/more/summary/savings',
                    ]);
                    $count++;
                } catch (\Exception $e) {
                    Log::warning('Failed to send mortgage rate alert', [
                        'user_id' => $userId,
                        'mortgage_id' => $mortgage->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $this->info("Sent {$count} mortgage rate alerts.");

        return self::SUCCESS;
    }
}
