<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Agents\EstateAgent;
use App\Agents\GoalsAgent;
use App\Agents\InvestmentAgent;
use App\Agents\ProtectionAgent;
use App\Agents\RetirementAgent;
use App\Agents\SavingsAgent;
use App\Models\User;
use App\Services\Dashboard\DashboardAggregator;
use App\Services\NetWorth\NetWorthService;
use App\Traits\StructuredLogging;
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
            'emergency_fund_target_months' => (int) ($emergencyFund['target_months'] ?? 6),
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
     * The dashboard's net worth — **NetWorthService's figure, published as sent**
     * (2026-10-01, one figure every surface, audit item 25).
     *
     * This used to be a second engine. It summed the asset classes itself, added a
     * `cash_accounts` total no other surface counts (and no path in the app writes),
     * and charged every liability the user recorded at its FULL balance — so a joint
     * loan was wholly on the recorder's dashboard and absent from the co-owner's,
     * while `/net-worth` charged each their share. The dashboard and `/net-worth`
     * now read the same cached blob (`getCachedNetWorth`, cleared on every data
     * change by `CacheInvalidationService`), so they cannot answer differently.
     *
     * The payload keeps the shape web, `/m` and iOS already decode — `total`,
     * `breakdown.{assets,liabilities,total_assets,total_liabilities}` — and each
     * value is NetWorthService's field verbatim: `assets` is its `breakdown`,
     * `liabilities` its `liabilities_breakdown`.
     */
    private function calculateNetWorth(int $userId): array
    {
        try {
            $user = User::find($userId);

            if (! $user) {
                return [
                    'total' => 0.0,
                    'breakdown' => [],
                    'has_db_pensions' => false,
                    'db_pension_disclosure' => null,
                ];
            }

            $netWorth = $this->netWorthService->getCachedNetWorth($user);

            return [
                'total' => $netWorth['net_worth'],
                'breakdown' => [
                    'assets' => $netWorth['breakdown'],
                    'liabilities' => $netWorth['liabilities_breakdown'],
                    'total_assets' => $netWorth['total_assets'],
                    'total_liabilities' => $netWorth['total_liabilities'],
                ],
                // Same keys, same meaning, same source as `/net-worth` (W-0241).
                'has_db_pensions' => $netWorth['has_db_pensions'],
                'db_pension_disclosure' => $netWorth['db_pension_disclosure'],
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
