<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Agents\EstateAgent;
use App\Agents\GoalsAgent;
use App\Agents\InvestmentAgent;
use App\Agents\ProtectionAgent;
use App\Agents\RetirementAgent;
use App\Agents\SavingsAgent;
use App\Models\BusinessInterest;
use App\Models\Chattel;
use App\Models\Investment\InvestmentAccount;
use App\Models\Mortgage;
use App\Models\Property;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Dashboard\DashboardAggregator;
use App\Traits\CalculatesOwnershipShare;
use App\Traits\StructuredLogging;
use Illuminate\Support\Facades\Cache;

/**
 * Aggregates all module summaries, net worth, alerts, and Fyn insight
 * into a single response optimised for the mobile app.
 *
 * Uses a 5-minute cache per user.
 */
class MobileDashboardAggregator
{
    use CalculatesOwnershipShare;
    use StructuredLogging;

    private const CACHE_TTL = 86400; // 24 hours — invalidated on data change

    public function __construct(
        private readonly ProtectionAgent $protectionAgent,
        private readonly SavingsAgent $savingsAgent,
        private readonly InvestmentAgent $investmentAgent,
        private readonly RetirementAgent $retirementAgent,
        private readonly EstateAgent $estateAgent,
        private readonly GoalsAgent $goalsAgent,
        private readonly DashboardAggregator $dashboardAggregator
    ) {}

    /**
     * Get aggregated dashboard data for the mobile app.
     *
     * @param  int  $userId  The user ID
     * @return array Aggregated dashboard data
     */
    public function getAggregatedDashboard(int $userId): array
    {
        return Cache::remember("mobile_dashboard_{$userId}", self::CACHE_TTL, function () use ($userId) {
            $modules = $this->aggregateModules($userId);
            $netWorth = $this->calculateNetWorth($userId);
            $alerts = $this->getAlerts($userId);
            $fynInsight = $this->generateFynInsight($modules, $netWorth);

            return [
                'modules' => $modules,
                'net_worth' => $netWorth,
                'alerts' => $alerts,
                'fyn_insight' => $fynInsight,
                'cached_at' => now()->toIso8601String(),
            ];
        });
    }

    /**
     * Aggregate summaries from all module agents.
     * If one module fails, the rest still return.
     */
    private function aggregateModules(int $userId): array
    {
        $modules = [];

        $agentMap = [
            'protection' => $this->protectionAgent,
            'savings' => $this->savingsAgent,
            'investment' => $this->investmentAgent,
            'retirement' => $this->retirementAgent,
            'estate' => $this->estateAgent,
            'goals' => $this->goalsAgent,
        ];

        foreach ($agentMap as $moduleName => $agent) {
            try {
                $analysis = $agent->analyze($userId);
                $modules[$moduleName] = $this->extractModuleSummary($moduleName, $analysis);
            } catch (\Throwable $e) {
                $this->logError("Mobile dashboard: failed to load {$moduleName} module", [
                    'user_id' => $userId,
                    'module' => $moduleName,
                ], $e);

                $modules[$moduleName] = [
                    'status' => 'unavailable',
                    'message' => 'Unable to load module data at this time.',
                ];
            }
        }

        return $modules;
    }

    /**
     * Extract a mobile-friendly summary from a module's full analysis.
     *
     * Agents return two formats:
     * - BaseAgent::response() format: ['success', 'message', 'data', 'timestamp']
     * - Raw array format (SavingsAgent, InvestmentAgent, GoalsAgent)
     */
    private function extractModuleSummary(string $module, array $analysis): array
    {
        // Unwrap BaseAgent::response() envelope if present
        $data = isset($analysis['success']) ? ($analysis['data'] ?? []) : $analysis;

        return match ($module) {
            'protection' => $this->extractProtectionSummary($data, $analysis),
            'savings' => $this->extractSavingsSummary($data),
            'investment' => $this->extractInvestmentSummary($data),
            'retirement' => $this->extractRetirementSummary($data, $analysis),
            'estate' => $this->extractEstateSummary($data, $analysis),
            'goals' => $this->extractGoalsSummary($data),
            default => ['status' => 'unknown'],
        };
    }

    /**
     * Extract protection module summary.
     */
    private function extractProtectionSummary(array $data, array $raw): array
    {
        // Handle case where protection profile doesn't exist
        if (isset($raw['success']) && $raw['success'] === false) {
            return [
                'status' => 'not_configured',
                'message' => 'Protection profile not yet set up.',
            ];
        }

        $coverage = $data['coverage'] ?? [];
        $gaps = $data['gaps'] ?? [];
        $criticalGaps = 0;

        foreach ($gaps as $gapType => $gapData) {
            if (is_array($gapData) && ($gapData['gap'] ?? 0) > 0) {
                $criticalGaps++;
            }
        }

        // Count total policies across all types
        $policies = $data['policies'] ?? [];
        $policyCount = 0;
        foreach ($policies as $typeGroup) {
            if (is_countable($typeGroup)) {
                $policyCount += count($typeGroup);
            }
        }

        return [
            'status' => 'active',
            'total_coverage' => round((float) ($coverage['total_life_cover'] ?? 0), 2),
            'policy_count' => $policyCount,
            'critical_gaps' => $criticalGaps,
            'has_income_protection' => (float) ($coverage['income_protection_coverage'] ?? 0) > 0,
        ];
    }

    /**
     * Extract savings module summary.
     */
    private function extractSavingsSummary(array $data): array
    {
        $summary = $data['summary'] ?? [];
        $emergencyFund = $data['emergency_fund'] ?? [];

        return [
            'status' => 'active',
            'total_savings' => round((float) ($summary['total_savings'] ?? 0), 2),
            'total_accounts' => (int) ($summary['total_accounts'] ?? 0),
            'emergency_fund_months' => round((float) ($emergencyFund['runway_months'] ?? 0), 1),
            'emergency_fund_status' => $emergencyFund['category'] ?? 'Unknown',
        ];
    }

    /**
     * Extract investment module summary.
     */
    private function extractInvestmentSummary(array $data): array
    {
        // Handle empty portfolio
        if (($data['accounts_count'] ?? null) === 0) {
            return [
                'status' => 'not_configured',
                'message' => 'No investment accounts found.',
            ];
        }

        $portfolioSummary = $data['portfolio_summary'] ?? [];

        return [
            'status' => 'active',
            'portfolio_value' => round((float) ($portfolioSummary['total_value'] ?? 0), 2),
            'accounts_count' => (int) ($portfolioSummary['accounts_count'] ?? 0),
            'holdings_count' => (int) ($portfolioSummary['holdings_count'] ?? 0),
        ];
    }

    /**
     * Extract retirement module summary.
     */
    private function extractRetirementSummary(array $data, array $raw): array
    {
        // Handle case where retirement profile doesn't exist
        if (isset($raw['success']) && $raw['success'] === false) {
            return [
                'status' => 'not_configured',
                'message' => 'Retirement profile not yet set up.',
            ];
        }

        $summary = $data['summary'] ?? $data;

        return [
            'status' => 'active',
            'years_to_retirement' => (int) ($summary['years_to_retirement'] ?? 0),
            'projected_income' => round((float) ($summary['projected_retirement_income'] ?? 0), 2),
            'target_income' => round((float) ($summary['target_retirement_income'] ?? 0), 2),
            'income_gap' => round((float) ($summary['income_gap'] ?? 0), 2),
            'total_pensions' => (int) ($summary['total_pensions_count'] ?? 0),
        ];
    }

    /**
     * Extract estate module summary.
     */
    private function extractEstateSummary(array $data, array $raw): array
    {
        if (isset($raw['success']) && $raw['success'] === false) {
            return [
                'status' => 'not_configured',
                'message' => 'Estate planning not yet set up.',
            ];
        }

        $summary = $data['summary'] ?? [];

        return [
            'status' => 'active',
            'net_estate' => round((float) ($summary['net_estate'] ?? 0), 2),
            'iht_liability' => round((float) ($summary['iht_liability'] ?? 0), 2),
            'effective_tax_rate' => round((float) ($summary['effective_tax_rate'] ?? 0), 2),
        ];
    }

    /**
     * Extract goals module summary.
     */
    private function extractGoalsSummary(array $data): array
    {
        if (! ($data['has_goals'] ?? false)) {
            return [
                'status' => 'not_configured',
                'message' => $data['message'] ?? 'No goals set yet.',
            ];
        }

        $summary = $data['summary'] ?? [];

        return [
            'status' => 'active',
            'total_goals' => (int) ($data['goals_count'] ?? 0),
            'completed_goals' => (int) ($data['completed_count'] ?? 0),
            'total_target' => round((float) ($summary['total_target'] ?? 0), 2),
            'total_saved' => round((float) ($summary['total_saved'] ?? 0), 2),
        ];
    }

    /**
     * Calculate user's net worth with joint asset ownership shares.
     */
    private function calculateNetWorth(int $userId): array
    {
        try {
            $user = User::with([
                'properties',
                'savingsAccounts',
                'investmentAccounts',
                'dcPensions',
                'dbPensions',
                'mortgages',
                'liabilities',
                'businessInterests',
                'chattels',
                'cashAccounts',
            ])->find($userId);

            if (! $user) {
                return [
                    'total' => 0.0,
                    'breakdown' => [],
                ];
            }

            // Calculate asset totals using ownership shares
            $propertyValue = $this->sumUserShares($user->properties, $userId);
            $savingsValue = $this->sumUserShares($user->savingsAccounts, $userId);
            $investmentValue = $this->sumUserShares($user->investmentAccounts, $userId);

            // Also include joint assets where user is the joint_owner_id
            $propertyValue += $this->sumJointOwnerShares(Property::class, $userId);
            $savingsValue += $this->sumJointOwnerShares(SavingsAccount::class, $userId);
            $investmentValue += $this->sumJointOwnerShares(InvestmentAccount::class, $userId);

            $dcPensionValue = (float) $user->dcPensions->sum('current_fund_value');
            $dbPensionValue = (float) $user->dbPensions->sum('transfer_value');
            $pensionValue = round($dcPensionValue + $dbPensionValue, 2);

            $businessValue = $this->sumUserShares($user->businessInterests, $userId);
            $businessValue += $this->sumJointOwnerShares(BusinessInterest::class, $userId);

            $chattelValue = $this->sumUserShares($user->chattels, $userId);
            $chattelValue += $this->sumJointOwnerShares(Chattel::class, $userId);

            $cashValue = (float) $user->cashAccounts->sum('current_balance');

            // Calculate liabilities
            $mortgageBalance = $this->sumMortgageShares($user->mortgages, $userId);
            $mortgageBalance += $this->sumJointMortgageShares($userId);

            $liabilityBalance = (float) $user->liabilities->sum('current_balance');

            $totalAssets = round(
                $propertyValue + $savingsValue + $investmentValue +
                $pensionValue + $businessValue + $chattelValue + $cashValue,
                2
            );
            $totalLiabilities = round($mortgageBalance + $liabilityBalance, 2);
            $netWorth = round($totalAssets - $totalLiabilities, 2);

            return [
                'total' => $netWorth,
                'breakdown' => [
                    'assets' => [
                        'property' => round($propertyValue, 2),
                        'savings' => round($savingsValue, 2),
                        'investments' => round($investmentValue, 2),
                        'pensions' => $pensionValue,
                        'business' => round($businessValue, 2),
                        'chattels' => round($chattelValue, 2),
                        'cash' => round($cashValue, 2),
                    ],
                    'liabilities' => [
                        'mortgages' => round($mortgageBalance, 2),
                        'other_liabilities' => round($liabilityBalance, 2),
                    ],
                    'total_assets' => $totalAssets,
                    'total_liabilities' => $totalLiabilities,
                ],
            ];
        } catch (\Throwable $e) {
            $this->logError('Mobile dashboard: failed to calculate net worth', [
                'user_id' => $userId,
            ], $e);

            return [
                'total' => 0.0,
                'breakdown' => [],
            ];
        }
    }

    /**
     * Sum user's ownership shares for a collection of assets (where user is primary owner).
     *
     * @param  \Illuminate\Support\Collection  $assets  Collection of asset models
     * @param  int  $userId  The user ID
     * @return float The total value of user's shares
     */
    private function sumUserShares($assets, int $userId): float
    {
        $total = 0.0;

        foreach ($assets as $asset) {
            $total += $this->calculateUserShare($asset, $userId);
        }

        return $total;
    }

    /**
     * Sum user's ownership shares for assets where the user is the joint_owner_id.
     */
    private function sumJointOwnerShares(string $modelClass, int $userId): float
    {
        if (! class_exists($modelClass)) {
            return 0.0;
        }

        $assets = $modelClass::where('joint_owner_id', $userId)->get();
        $total = 0.0;

        foreach ($assets as $asset) {
            $total += $this->calculateUserShare($asset, $userId);
        }

        return $total;
    }

    /**
     * Sum user's share of mortgages (where user is primary owner).
     */
    private function sumMortgageShares($mortgages, int $userId): float
    {
        $total = 0.0;

        foreach ($mortgages as $mortgage) {
            $total += $this->calculateUserMortgageShare($mortgage, $userId);
        }

        return $total;
    }

    /**
     * Sum user's share of mortgages where user is joint_owner_id.
     */
    private function sumJointMortgageShares(int $userId): float
    {
        $mortgages = Mortgage::where('joint_owner_id', $userId)->get();
        $total = 0.0;

        foreach ($mortgages as $mortgage) {
            $total += $this->calculateUserMortgageShare($mortgage, $userId);
        }

        return $total;
    }

    /**
     * Get aggregated alerts from the existing DashboardAggregator.
     */
    private function getAlerts(int $userId): array
    {
        try {
            return $this->dashboardAggregator->aggregateAlerts($userId);
        } catch (\Throwable $e) {
            $this->logError('Mobile dashboard: failed to aggregate alerts', [
                'user_id' => $userId,
            ], $e);

            return [];
        }
    }

    /**
     * Generate a contextual daily insight based on the aggregated data.
     */
    private function generateFynInsight(array $modules, array $netWorth): string
    {
        // Check for protection gaps
        $protectionStatus = $modules['protection']['status'] ?? 'unavailable';
        if ($protectionStatus === 'active') {
            $gaps = $modules['protection']['critical_gaps'] ?? 0;
            if ($gaps > 0) {
                return $gaps === 1
                    ? 'You have 1 protection gap to review. Ensuring adequate cover helps safeguard your family against unexpected events.'
                    : "You have {$gaps} protection gaps to review. Addressing these will strengthen your financial safety net.";
            }
        }

        // Check for emergency fund
        $savingsStatus = $modules['savings']['status'] ?? 'unavailable';
        if ($savingsStatus === 'active') {
            $runwayMonths = $modules['savings']['emergency_fund_months'] ?? 0;
            if ($runwayMonths < 3) {
                return 'Building your emergency fund towards 3-6 months of expenses is a key priority. Even small regular contributions make a difference.';
            }
        }

        // Check retirement income gap
        $retirementStatus = $modules['retirement']['status'] ?? 'unavailable';
        if ($retirementStatus === 'active') {
            $incomeGap = $modules['retirement']['income_gap'] ?? 0;
            if ($incomeGap > 0) {
                return 'Your projected retirement income has a gap to your target. Reviewing pension contributions or exploring additional savings could help close this.';
            }
        }

        // Check estate IHT
        $estateStatus = $modules['estate']['status'] ?? 'unavailable';
        if ($estateStatus === 'active') {
            $ihtLiability = $modules['estate']['iht_liability'] ?? 0;
            if ($ihtLiability > 0) {
                return 'Your estate may have an inheritance tax liability. Consider exploring gifting strategies or trust arrangements to help reduce this.';
            }
        }

        // Goals insight
        $goalsStatus = $modules['goals']['status'] ?? 'unavailable';
        if ($goalsStatus === 'active') {
            $completed = $modules['goals']['completed_goals'] ?? 0;
            $total = $modules['goals']['total_goals'] ?? 0;
            if ($total > 0 && $completed > 0) {
                return "Well done! You have completed {$completed} of your {$total} financial goals. Keep the momentum going.";
            }
        }

        // Net worth insight
        $total = $netWorth['total'] ?? 0;
        if ($total > 0) {
            return 'Your financial plan is taking shape. Regular reviews help ensure you stay on track with your goals.';
        }

        return 'Welcome to Fynla. Start by setting up your financial profile to receive personalised insights and guidance.';
    }

    /**
     * Clear the mobile dashboard cache for a user.
     */
    public function clearCache(int $userId): void
    {
        Cache::forget("mobile_dashboard_{$userId}");
    }
}
