<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\DCPension;
use App\Models\Investment\InvestmentAccount;
use App\Services\Risk\RiskPreferenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job to recalculate a user's risk profile when their financial data changes.
 *
 * This job is dispatched by model observers when relevant data changes:
 * - User profile (income, education, employment, retirement age)
 * - Family members (dependants count)
 * - Savings accounts (emergency fund)
 * - Investment accounts (capacity for loss)
 * - DC Pensions (capacity for loss)
 */
class RecalculateRiskProfileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public $timeout = 30;

    public function __construct(
        private int $userId,
        private string $trigger = 'unknown'
    ) {
        // Set delay for debouncing - if multiple changes happen quickly, only the last job runs
        $this->delay = 5;
    }

    public function handle(RiskPreferenceService $riskPreferenceService): void
    {
        Log::info("Recalculating risk profile for user {$this->userId}", [
            'trigger' => $this->trigger,
        ]);

        try {
            $result = $riskPreferenceService->calculateAndSetRiskLevel($this->userId);
            $newRiskLevel = $result['risk_level'];

            Log::info("Risk profile recalculated for user {$this->userId}", [
                'new_level' => $newRiskLevel,
                'trigger' => $this->trigger,
            ]);

            // Backfill investment accounts without risk_preference
            $accountsUpdated = InvestmentAccount::where('user_id', $this->userId)
                ->whereNull('risk_preference')
                ->update(['risk_preference' => $newRiskLevel]);

            // Backfill DC pensions without risk_preference
            $pensionsUpdated = DCPension::where('user_id', $this->userId)
                ->whereNull('risk_preference')
                ->update(['risk_preference' => $newRiskLevel]);

            if ($accountsUpdated > 0 || $pensionsUpdated > 0) {
                Log::info("Backfilled risk_preference for user {$this->userId}", [
                    'accounts_updated' => $accountsUpdated,
                    'pensions_updated' => $pensionsUpdated,
                    'risk_level' => $newRiskLevel,
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Failed to recalculate risk profile for user {$this->userId}", [
                'error' => $e->getMessage(),
                'trigger' => $this->trigger,
            ]);
        }
    }

    /**
     * Get the unique ID for this job to prevent duplicate processing.
     */
    public function uniqueId(): string
    {
        return 'risk_recalc_'.$this->userId;
    }
}
