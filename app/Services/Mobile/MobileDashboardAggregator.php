<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Agents\EstateAgent;
use App\Agents\GoalsAgent;
use App\Agents\InvestmentAgent;
use App\Agents\ProtectionAgent;
use App\Agents\RetirementAgent;
use App\Agents\SavingsAgent;
use App\Constants\PensionDisclosure;
use App\Models\BusinessInterest;
use App\Models\Chattel;
use App\Models\Investment\InvestmentAccount;
use App\Models\User;
use App\Services\Dashboard\DashboardAggregator;
use App\Services\NetWorth\NetWorthService;
use App\Services\Shared\CrossModuleAssetAggregator;
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
        private readonly CrossModuleAssetAggregator $assetAggregator,
        private readonly NetWorthService $netWorthService,
        private readonly DailyInsightService $dailyInsight,
    ) {}

    /**
     * Each module's own `analyze()` payload from the last `aggregateModules()` run,
     * kept so the insight can be composed from data already fetched rather than
     * calling every agent a second time.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $modulePayloads = [];

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
            // W-0478 — this used to call a second, prose-only insight composer that
            // lived in this class, while a richer one with real figures and the
            // Inheritance Tax caveat sat unreachable behind an endpoint no client
            // called. One composer now, reading the payloads `aggregateModules()`
            // already fetched (Rule 20).
            $fynInsight = $this->dailyInsight->select(
                $this->dailyInsight->compose($this->modulePayloads)
            )['insight'];

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

        $this->modulePayloads = [];

        foreach ($agentMap as $moduleName => $agent) {
            try {
                $analysis = $agent->analyze($userId);
                $this->modulePayloads[$moduleName] = isset($analysis['success'])
                    ? ($analysis['data'] ?? [])
                    : $analysis;
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
        // W-0479 — this counted `$gapData['gap']` over `gaps`, a shape
        // `CoverageGapAnalyzer` has never emitted, so it read 0 for every household
        // in the application's history. The analyzer now publishes the count itself
        // and both dashboards read it, rather than each re-deriving it from a guess
        // (Rule 20).
        $criticalGaps = (int) ($data['gaps']['critical_gap_count'] ?? 0);

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
     *
     * **Every figure here comes from `RetirementAgent::analyze()` and nowhere else.**
     * This method used to read the pension records directly whenever the agent
     * declined to answer, because without a `retirement_profiles` row the agent
     * returned `success: false` with an empty data array (W-0238's workaround). The
     * agent now answers with the facts and a null projection, so the second
     * mechanism is deleted and the card has one home again (W-0244, Rule 20).
     *
     * What the user HAS and what they are AIMING AT are separate facts, and
     * `summary.has_retirement_target` — not `success` — is what distinguishes them.
     */
    private function extractRetirementSummary(array $data, array $raw): array
    {
        // A readiness-blocked response is NOT an empty one. `RetirementAgent` fills
        // its `summary` with the record-derived facts on that path too, precisely so
        // a household with an NHS scheme and no income on file is not told it has no
        // retirement provision. Blanking the summary here would have thrown those
        // facts away and reinstated the bug one level up (W-0244).
        $summary = (isset($raw['success']) && $raw['success'] === false)
            ? []
            : ($data['summary'] ?? $data);
        $summary = is_array($summary) ? $summary : [];
        $totalPensions = (int) ($summary['total_pensions_count'] ?? 0);

        // "Not set up" means holding nothing, not aiming at nothing. A household
        // with pensions on file never lands here however incomplete its profile is.
        if ($totalPensions === 0) {
            return [
                'status' => 'not_configured',
                'message' => 'Retirement profile not yet set up.',
            ];
        }

        return [
            'status' => 'active',
            'years_to_retirement' => (int) ($summary['years_to_retirement'] ?? 0),
            // Current defined contribution pot — the card headline where there is one.
            'pot_value' => round((float) ($summary['current_dc_value'] ?? 0), 2),
            // Annual income already secured, for the user whose provision has no pot
            // to show: a defined benefit scheme and the State Pension are worth an
            // income, not a balance, and a card that can only render a balance shows
            // them nothing. Computed once, in the agent.
            'guaranteed_income' => round((float) ($summary['guaranteed_annual_income'] ?? 0), 2),
            'projected_income' => round((float) ($summary['projected_retirement_income'] ?? 0), 2),
            'target_income' => round((float) ($summary['target_retirement_income'] ?? 0), 2),
            'income_gap' => round((float) ($summary['income_gap'] ?? 0), 2),
            'total_pensions' => $totalPensions,
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
                    'has_db_pensions' => false,
                    'db_pension_disclosure' => null,
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

            // What a pension contributes to net worth has one home, and it is
            // NetWorthService — the same rule `/net-worth` reads on all three
            // surfaces. This used to be two local sums, the second of which read
            // `db_pensions.transfer_value`, a column that has never existed. Over a
            // Collection a missing attribute reads as null, so it silently summed to
            // £0 for every user, forever, while the code read as though Defined
            // Benefit schemes were being valued (W-0241).
            $pensionBreakdown = $this->netWorthService->calculatePensionBreakdown($userId);
            $pensionValue = round($pensionBreakdown['dc'], 2);

            $businessValue = $this->sumUserShares($user->businessInterests, $userId);
            $businessValue += $this->sumJointOwnerShares(BusinessInterest::class, $userId);

            $chattelValue = $this->sumUserShares($user->chattels, $userId);
            $chattelValue += $this->sumJointOwnerShares(Chattel::class, $userId);

            $cashValue = (float) $user->cashAccounts->sum('current_balance');

            // Calculate liabilities. One home for what this user owes on the
            // mortgages — reach-complete across both legs, and share-correct per
            // W-0228's ruling.
            $mortgageBalance = $this->assetAggregator->calculateMortgageTotal($userId);

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
                // Same keys, same meaning, same source as `/net-worth` returns
                // (W-0241). The pensions figure above is Defined Contribution only;
                // these are how a surface knows to say so instead of presenting the
                // total as complete — and the sentence comes from its one home, so
                // no surface keeps its own copy of the wording (Rule 20).
                'has_db_pensions' => $pensionBreakdown['has_db'],
                'db_pension_disclosure' => $pensionBreakdown['has_db']
                    ? PensionDisclosure::DEFINED_BENEFIT_EXCLUDED
                    : null,
            ];
        } catch (\Throwable $e) {
            $this->logError('Mobile dashboard: failed to calculate net worth', [
                'user_id' => $userId,
            ], $e);

            return [
                'total' => 0.0,
                'breakdown' => [],
                'has_db_pensions' => false,
                'db_pension_disclosure' => null,
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

    /*
     * `sumMortgageShares()` and `sumMortgageJointOwnerShares()` were deleted here
     * (W-0228).
     *
     * Both reached mortgages by the owner columns on the MORTGAGE row — one
     * through `$user->mortgages`, the other by filtering `joint_owner_id`. Under
     * CSJ's ruling a debt is shared as the property securing it is shared, so a
     * user can owe part of a mortgage whose row names someone else entirely, and
     * a mortgage-keyed reach cannot see it. The fraction was fixed in
     * `CalculatesOwnershipShare`; the reach had to move with it, or the figure
     * would be a correct share of an incomplete set.
     *
     * `CrossModuleAssetAggregator::calculateMortgageTotal()` already reaches both
     * legs — mortgages the user holds, and mortgages on properties the user owns —
     * and is what `/net-worth` and the wealth summary read. The dashboard now
     * reads it too, so the two cannot disagree.
     */

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
     * Clear the mobile dashboard cache for a user.
     */
    public function clearCache(int $userId): void
    {
        Cache::forget("mobile_dashboard_{$userId}");
    }
}
