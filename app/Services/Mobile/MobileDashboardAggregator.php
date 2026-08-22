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
use App\Models\User;
use App\Services\Dashboard\DashboardAggregator;
use App\Services\Retirement\PensionProjector;
use App\Services\Stores\MortgageStore;
use App\Services\Stores\PensionStore;
use App\Services\Stores\PropertyStore;
use App\Services\Stores\SavingsStore;
use App\Traits\CalculatesOwnershipShare;
use App\Traits\StructuredLogging;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Aggregates all module summaries, net worth, alerts, and Fyn insight
 * into a single response. Served to the web dashboard (`GamifiedDashboard.vue`),
 * the `/m` dashboard and native iOS from the one endpoint
 * `GET /api/v1/mobile/dashboard` — so every figure here is a figure on three
 * surfaces (Rule 19/20).
 *
 * **The cache is a backstop, not the freshness mechanism.** The blob is
 * invalidated on data change by `UserDataCacheObserver` →
 * `CacheInvalidationService`, which clears it for the owner, the joint owner and
 * both spouses. The TTL exists only so an entry cannot live forever if every
 * invalidation path is missed.
 *
 * The docblock here used to read "Uses a 5-minute cache per user" beside a
 * constant of 86,400 seconds. It was wrong for long enough that a wrong
 * dashboard was served for 21 hours and a shipped fix stayed invisible, because
 * everyone reading the class believed the comment (W-0239).
 */
class MobileDashboardAggregator
{
    use CalculatesOwnershipShare;
    use StructuredLogging;

    /** Backstop only — freshness comes from invalidation, not expiry. See the class docblock. */
    private const CACHE_TTL = 86400;

    public function __construct(
        private readonly ProtectionAgent $protectionAgent,
        private readonly SavingsAgent $savingsAgent,
        private readonly InvestmentAgent $investmentAgent,
        private readonly RetirementAgent $retirementAgent,
        private readonly EstateAgent $estateAgent,
        private readonly GoalsAgent $goalsAgent,
        private readonly DashboardAggregator $dashboardAggregator,
        private readonly SavingsStore $savingsStore,
        private readonly PropertyStore $propertyStore,
        private readonly MortgageStore $mortgageStore,
        private readonly PensionStore $pensionStore,
        private readonly PensionProjector $pensionProjector,
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

            // The retirement card used to be patched here, after the fact, from
            // `net_worth…pensions`. That patch could only ever help a user whose
            // provision has a capital value: it reads dbPensions->sum('transfer_value'),
            // and db_pensions has no transfer_value column, so a spouse whose whole
            // retirement is a defined benefit scheme scored zero and was told to
            // "Plan your retirement" beside a page showing her £35,000 a year
            // (W-0238). extractRetirementSummary now reads the pension records
            // directly, so the patch has nothing left to do.
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
                $modules[$moduleName] = $this->extractModuleSummary($moduleName, $analysis, $userId);
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
    private function extractModuleSummary(string $module, array $analysis, int $userId): array
    {
        // Unwrap BaseAgent::response() envelope if present
        $data = isset($analysis['success']) ? ($analysis['data'] ?? []) : $analysis;

        return match ($module) {
            'protection' => $this->extractProtectionSummary($data, $analysis),
            'savings' => $this->extractSavingsSummary($data),
            'investment' => $this->extractInvestmentSummary($data),
            'retirement' => $this->extractRetirementSummary($data, $analysis, $userId),
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
        // Handle case where protection profile doesn't exist, or the readiness
        // gate blocked analysis (agent returns success=true, can_proceed=false,
        // coverage=null) — treat both as not-configured rather than active-with-0.
        if ((isset($raw['success']) && $raw['success'] === false)
            || ($data['can_proceed'] ?? true) === false) {
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
            // CoverageGapAnalyzer emits 'total_coverage' (life + critical illness);
            // 'life_coverage' is life-only. The prior 'total_life_cover' key was never
            // produced, so this card read £0 for every user with cover.
            'total_coverage' => round((float) ($coverage['total_coverage'] ?? 0), 2),
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
    private function extractRetirementSummary(array $data, array $raw, int $userId): array
    {
        // What the user HAS is a fact about their pension records. What they are
        // AIMING AT is a fact about their retirement profile, and RetirementAgent
        // returns success=false without one. Reading both from the analysis meant
        // a household with half a million in pensions and a defined benefit scheme
        // paying £35,000 a year was shown "Plan your retirement" on the strength of
        // a missing target — so the two are now read separately (W-0238).
        $pensions = $this->pensionProvision($userId);

        $profileMissing = (isset($raw['success']) && $raw['success'] === false)
            || ($data['can_proceed'] ?? true) === false;

        if ($profileMissing && $pensions['total_pensions'] === 0) {
            return [
                'status' => 'not_configured',
                'message' => 'Retirement profile not yet set up.',
            ];
        }

        $summary = $profileMissing ? [] : ($data['summary'] ?? $data);

        return [
            'status' => 'active',
            'years_to_retirement' => (int) ($summary['years_to_retirement'] ?? 0),
            // Current defined contribution pot — the card headline where there is
            // one. The agent's own figure whenever the agent answered, so this is
            // not a second mechanism; the records are read directly only when
            // there is no retirement profile and the agent returns nothing at all.
            'pot_value' => $profileMissing
                ? $pensions['pot_value']
                : round((float) ($summary['current_dc_value'] ?? 0), 2),
            // Annual income already secured, for the user whose provision has no
            // pot to show: a defined benefit scheme and the State Pension are worth
            // an income, not a balance, and a card that can only render a balance
            // shows them nothing.
            'guaranteed_income' => $pensions['guaranteed_income'],
            'projected_income' => round((float) ($summary['projected_retirement_income'] ?? 0), 2),
            'target_income' => round((float) ($summary['target_retirement_income'] ?? 0), 2),
            'income_gap' => round((float) ($summary['income_gap'] ?? 0), 2),
            'total_pensions' => (int) ($summary['total_pensions_count'] ?? $pensions['total_pensions']),
        ];
    }

    /**
     * What retirement provision this user actually holds, independent of whether
     * they have told us what they are aiming for.
     *
     * Both figures come from `PensionProjector::projectTotalRetirementIncome`, the
     * one home `RetirementAgent` itself reads, so the card and the module page
     * cannot give different answers.
     *
     * **Basis caveat, stated where it is consumed** (`app/Services/CLAUDE.md`): the
     * defined benefit component is nominal at retirement and the State Pension
     * component is in today's money. Summing them is what every other consumer of
     * this projector already does; this does not introduce the mixing, and it is
     * raised separately rather than changed silently here.
     *
     * @return array{pot_value: float, guaranteed_income: float, total_pensions: int}
     */
    private function pensionProvision(int $userId): array
    {
        try {
            $user = User::find($userId);

            if (! $user) {
                return ['pot_value' => 0.0, 'guaranteed_income' => 0.0, 'total_pensions' => 0];
            }

            $dcPensions = $this->pensionStore->forUserByType($user, 'dc');
            $dbPensions = $this->pensionStore->forUserByType($user, 'db');
            $statePension = $this->pensionStore->statePension($user);

            $projection = $this->pensionProjector->projectTotalRetirementIncome($userId);

            return [
                'pot_value' => round((float) $dcPensions->sum('current_fund_value'), 2),
                'guaranteed_income' => round(
                    (float) ($projection['db_annual_income'] ?? 0)
                    + (float) ($projection['state_pension_income'] ?? 0),
                    2
                ),
                'total_pensions' => $dcPensions->count() + $dbPensions->count() + ($statePension ? 1 : 0),
            ];
        } catch (\Throwable $e) {
            $this->logError('Mobile dashboard: failed to read pension provision', [
                'user_id' => $userId,
            ], $e);

            return ['pot_value' => 0.0, 'guaranteed_income' => 0.0, 'total_pensions' => 0];
        }
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
            $propertyValue += $this->sumPropertyJointOwnerShares($user, $userId);
            $savingsValue += $this->sumSavingsJointOwnerShares($user, $userId);
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
            $mortgageBalance += $this->sumMortgageJointOwnerShares($user, $userId);

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
     * @param  Collection  $assets  Collection of asset models
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
     * Sum savings joint-owner shares via SavingsStore, filtering to records
     * where the user is the joint_owner_id (avoids double-counting with
     * the primary-owner path that reads via $user->savingsAccounts relation).
     */
    private function sumSavingsJointOwnerShares(User $user, int $userId): float
    {
        $total = 0.0;

        foreach ($this->savingsStore->forUser($user)->filter(fn ($a) => $a->joint_owner_id === $userId) as $account) {
            $total += $this->calculateUserShare($account, $userId);
        }

        return $total;
    }

    /**
     * Sum property joint-owner shares via PropertyStore, filtering to records
     * where the user is the joint_owner_id (avoids double-counting with
     * the primary-owner path that reads via $user->properties relation).
     */
    private function sumPropertyJointOwnerShares(User $user, int $userId): float
    {
        $total = 0.0;

        foreach ($this->propertyStore->forUser($user)->filter(fn ($p) => $p->joint_owner_id === $userId) as $property) {
            $total += $this->calculateUserShare($property, $userId);
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
     * Sum mortgage joint-owner shares via MortgageStore, filtering to records
     * where the user is the joint_owner_id (avoids double-counting with
     * the primary-owner path that reads via $user->mortgages relation).
     */
    private function sumMortgageJointOwnerShares(User $user, int $userId): float
    {
        $total = 0.0;

        foreach ($this->mortgageStore->forUser($user)->filter(fn ($m) => $m->joint_owner_id === $userId) as $mortgage) {
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
