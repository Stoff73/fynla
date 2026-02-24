<?php

declare(strict_types=1);

namespace App\Services\Investment;

use App\Models\Investment\InvestmentAccount;
use App\Models\User;
use App\Services\Goals\LifeEventCashFlowService;
use App\Services\Risk\RiskPreferenceService;
use Illuminate\Support\Collection;

class InvestmentProjectionService
{
    private const DEFAULT_PROJECTION_PERIODS = [5, 10, 20, 30];

    private const MONTE_CARLO_ITERATIONS = 1000;

    public function __construct(
        private readonly MonteCarloSimulator $simulator,
        private readonly RiskPreferenceService $riskService,
        private readonly ContributionEstimatorService $contributionEstimator,
        private readonly LifeEventCashFlowService $lifeEventCashFlowService
    ) {}

    /**
     * Get the user's share value for an account (handles joint ownership).
     */
    private function getUserShareValue(InvestmentAccount $account): float
    {
        $fullValue = (float) $account->current_value;

        if ($account->ownership_type === 'joint') {
            $percentage = (float) ($account->ownership_percentage ?? 50) / 100;

            return $fullValue * $percentage;
        }

        return $fullValue;
    }

    /**
     * Get total portfolio value accounting for joint ownership.
     */
    private function getTotalPortfolioValue(Collection $accounts): float
    {
        return $accounts->sum(fn ($account) => $this->getUserShareValue($account));
    }

    /**
     * Get complete portfolio projections with account breakdowns.
     * Results are cached for 24 hours via MonteCarloSimulator.
     */
    public function getPortfolioProjections(
        User $user,
        array $projectionPeriods = self::DEFAULT_PROJECTION_PERIODS,
        ?array $contributionOverrides = null,
        ?int $selectedPeriod = null
    ): array {
        $accounts = InvestmentAccount::where('user_id', $user->id)
            ->with('holdings')
            ->get();

        if ($accounts->isEmpty()) {
            return [
                'portfolio' => null,
                'accounts' => [],
                'message' => 'No investment accounts found',
            ];
        }

        // Build projections - caching is handled by MonteCarloSimulator
        $result = $this->buildPortfolioProjections(
            $user,
            $accounts,
            $projectionPeriods,
            $selectedPeriod,
            $contributionOverrides
        );

        // Add life events applied metadata
        $maxPeriod = max($projectionPeriods);
        $result['life_events_applied'] = $this->lifeEventCashFlowService->getAppliedEvents(
            $user->id,
            'investment',
            $maxPeriod
        );

        return $result;
    }

    /**
     * Build portfolio projections.
     */
    private function buildPortfolioProjections(
        User $user,
        Collection $accounts,
        array $projectionPeriods,
        ?int $selectedPeriod,
        ?array $contributionOverrides = null
    ): array {
        // Calculate portfolio-level projection
        $portfolioProjection = $this->calculatePortfolioProjection(
            $user,
            $accounts,
            $projectionPeriods,
            $contributionOverrides
        );

        // Calculate per-account projections
        $accountProjections = [];
        foreach ($accounts as $account) {
            $accountProjections[] = $this->calculateAccountProjection(
                $account,
                $user,
                $projectionPeriods,
                $contributionOverrides[$account->id] ?? null
            );
        }

        return [
            'portfolio' => $portfolioProjection,
            'accounts' => $accountProjections,
            'projection_periods' => $projectionPeriods,
            'selected_period' => $selectedPeriod ?? $projectionPeriods[1] ?? 10,
        ];
    }

    private function calculatePortfolioProjection(
        User $user,
        Collection $accounts,
        array $periods,
        ?array $contributionOverrides
    ): array {
        $totalValue = $this->getTotalPortfolioValue($accounts);
        $monthlyContribution = $this->contributionEstimator->estimatePortfolioContribution(
            $accounts,
            $contributionOverrides
        );

        // Calculate weighted portfolio risk and determine if using default or profile
        $riskResult = $this->calculatePortfolioRiskWithSource($accounts, $user);
        $riskParams = $riskResult['params'];
        $riskLevel = $this->determineRiskLevel($riskParams['expected_return_typical']);
        $riskSource = $riskResult['source'];

        // Build life event cash flow maps and event hash for cache differentiation
        $eventHash = $this->lifeEventCashFlowService->getEventHash($user->id, 'investment');

        $projections = [];
        foreach ($periods as $years) {
            // Build cash flow map for this projection period
            $scheduledInjections = $this->lifeEventCashFlowService->buildCashFlowMap(
                $user->id,
                'investment',
                $years
            );

            // Build cache key for portfolio projection (only if no overrides)
            $cacheKey = empty($contributionOverrides)
                ? "user_{$user->id}_portfolio_{$years}y_e{$eventHash}"
                : null;

            $simulation = $this->simulator->simulate(
                $totalValue,
                $monthlyContribution,
                $riskParams['expected_return_typical'] / 100,
                $riskParams['volatility'] / 100,
                $years,
                self::MONTE_CARLO_ITERATIONS,
                $cacheKey,
                $scheduledInjections
            );

            $yearByYear = $this->extractProbabilityBands($simulation);
            $finalYear = end($yearByYear);

            $projections[$years] = [
                'years' => $years,
                'median_value' => $finalYear['percentile_50'] ?? $totalValue,
                'percentiles' => [
                    'p10' => $finalYear['percentile_10'] ?? $totalValue,
                    'p15' => $finalYear['percentile_15'] ?? $totalValue,
                    'p20' => $finalYear['percentile_20'] ?? $totalValue,
                    'p25' => $finalYear['percentile_25'] ?? $totalValue,
                    'p50' => $finalYear['percentile_50'] ?? $totalValue,
                    'p75' => $finalYear['percentile_75'] ?? $totalValue,
                    'p90' => $finalYear['percentile_90'] ?? $totalValue,
                ],
                'year_by_year' => $yearByYear,
            ];
        }

        return [
            'current_value' => round($totalValue, 2),
            'estimated_monthly_contribution' => round($monthlyContribution, 2),
            'risk_level' => $riskLevel,
            'risk_source' => $riskSource,
            'expected_return' => $riskParams['expected_return_typical'],
            'volatility' => $riskParams['volatility'],
            'projections' => $projections,
            'account_count' => $accounts->count(),
        ];
    }

    private function calculateAccountProjection(
        InvestmentAccount $account,
        User $user,
        array $periods,
        ?float $contributionOverride
    ): array {
        $value = $this->getUserShareValue($account);
        $monthlyContribution = $this->contributionEstimator->estimateMonthlyContribution(
            $account,
            $contributionOverride
        );

        // Get risk level for this account and track source
        $mainRiskLevel = $this->riskService->getMainRiskLevel($user->id);
        $riskSource = 'default';

        if ($account->risk_preference !== null) {
            $riskLevel = $account->risk_preference;
            $riskSource = 'profile';
        } elseif ($mainRiskLevel !== null) {
            $riskLevel = $mainRiskLevel;
            $riskSource = 'profile';
        } else {
            $riskLevel = 'medium';
        }

        $riskParams = $this->riskService->getReturnParameters($riskLevel);

        $projections = [];
        foreach ($periods as $years) {
            // Build cache key for account projection (only if no override)
            $cacheKey = ($contributionOverride === null)
                ? "user_{$user->id}_account_{$account->id}_{$years}y"
                : null;

            $simulation = $this->simulator->simulate(
                $value,
                $monthlyContribution,
                $riskParams['expected_return_typical'] / 100,
                $riskParams['volatility'] / 100,
                $years,
                self::MONTE_CARLO_ITERATIONS,
                $cacheKey
            );

            $yearByYear = $this->extractProbabilityBands($simulation);
            $finalYear = end($yearByYear);

            $projections[$years] = [
                'years' => $years,
                'median_value' => $finalYear['percentile_50'] ?? $value,
                'percentiles' => [
                    'p10' => $finalYear['percentile_10'] ?? $value,
                    'p15' => $finalYear['percentile_15'] ?? $value,
                    'p20' => $finalYear['percentile_20'] ?? $value,
                    'p25' => $finalYear['percentile_25'] ?? $value,
                    'p50' => $finalYear['percentile_50'] ?? $value,
                    'p75' => $finalYear['percentile_75'] ?? $value,
                    'p90' => $finalYear['percentile_90'] ?? $value,
                ],
                'year_by_year' => $yearByYear,
            ];
        }

        return [
            'account_id' => $account->id,
            'account_name' => $account->provider.' '.$this->formatAccountType($account->account_type),
            'account_type' => $account->account_type,
            'current_value' => round($value, 2),
            'estimated_monthly_contribution' => round($monthlyContribution, 2),
            'risk_level' => $riskLevel,
            'risk_source' => $riskSource,
            'expected_return' => $riskParams['expected_return_typical'],
            'volatility' => $riskParams['volatility'],
            'projections' => $projections,
        ];
    }

    /**
     * Extract probability bands from Monte Carlo results.
     */
    private function extractProbabilityBands(array $simulation): array
    {
        $result = [];
        $currentYear = (int) date('Y');
        $startValue = $simulation['summary']['start_value'] ?? 0;

        // Add year 0 (current year) with current value
        $result[] = [
            'year' => $currentYear,
            'year_number' => 0,
            'percentile_5' => round($startValue, 2),
            'percentile_10' => round($startValue, 2),
            'percentile_15' => round($startValue, 2),
            'percentile_20' => round($startValue, 2),
            'percentile_25' => round($startValue, 2),
            'percentile_50' => round($startValue, 2),
            'percentile_75' => round($startValue, 2),
            'percentile_90' => round($startValue, 2),
        ];

        foreach ($simulation['year_by_year'] as $yearData) {
            $yearIndex = $yearData['year'];
            $percentiles = $yearData['percentiles'];

            $p10 = $this->getPercentileValue($percentiles, '10th');
            $p25 = $this->getPercentileValue($percentiles, '25th');
            $p50 = $this->getPercentileValue($percentiles, '50th');
            $p75 = $this->getPercentileValue($percentiles, '75th');
            $p90 = $this->getPercentileValue($percentiles, '90th');

            // Interpolate 15th and 20th percentiles between 10th and 25th
            $spread = $p25 - $p10;
            $p15 = $p10 + ($spread * 0.33);
            $p20 = $p10 + ($spread * 0.67);

            // Extrapolate 5th percentile below 10th (conservative estimate)
            $p5 = $p10 - ($spread * 0.33);

            // Smooth transition for early years
            $blendFactor = 1.0;
            if ($yearIndex === 1) {
                $blendFactor = 0.7;
            } elseif ($yearIndex === 2) {
                $blendFactor = 0.9;
            }

            $p5 = $this->blendValue($p5, $startValue, $blendFactor);
            $p10 = $this->blendValue($p10, $startValue, $blendFactor);
            $p15 = $this->blendValue($p15, $startValue, $blendFactor);
            $p20 = $this->blendValue($p20, $startValue, $blendFactor);
            $p25 = $this->blendValue($p25, $startValue, $blendFactor);
            $p50 = $this->blendValue($p50, $startValue, $blendFactor);
            $p75 = $this->blendValue($p75, $startValue, $blendFactor);
            $p90 = $this->blendValue($p90, $startValue, $blendFactor);

            $result[] = [
                'year' => $currentYear + $yearIndex,
                'year_number' => $yearIndex,
                'percentile_5' => round($p5, 2),
                'percentile_10' => round($p10, 2),
                'percentile_15' => round($p15, 2),
                'percentile_20' => round($p20, 2),
                'percentile_25' => round($p25, 2),
                'percentile_50' => round($p50, 2),
                'percentile_75' => round($p75, 2),
                'percentile_90' => round($p90, 2),
            ];
        }

        return $result;
    }

    private function blendValue(float $monteCarloValue, float $startValue, float $blendFactor): float
    {
        return ($monteCarloValue * $blendFactor) + ($startValue * (1 - $blendFactor));
    }

    private function getPercentileValue(array $percentiles, string $key): float
    {
        foreach ($percentiles as $p) {
            if ($p['percentile'] === $key) {
                return (float) $p['value'];
            }
        }

        return 0.0;
    }

    private function calculatePortfolioRiskWithSource(Collection $accounts, User $user): array
    {
        $totalValue = $this->getTotalPortfolioValue($accounts);

        if ($totalValue <= 0) {
            return [
                'params' => $this->riskService->getReturnParameters('medium'),
                'source' => 'default',
            ];
        }

        $weightedReturn = 0.0;
        $weightedVolatility = 0.0;
        $hasProfileRisk = false;

        $mainRiskLevel = $this->riskService->getMainRiskLevel($user->id);
        if ($mainRiskLevel !== null) {
            $hasProfileRisk = true;
        }

        foreach ($accounts as $account) {
            $weight = $this->getUserShareValue($account) / $totalValue;
            $riskLevel = $account->risk_preference
                ?? $mainRiskLevel
                ?? 'medium';

            if ($account->risk_preference !== null) {
                $hasProfileRisk = true;
            }

            $params = $this->riskService->getReturnParameters($riskLevel);
            $weightedReturn += $weight * $params['expected_return_typical'];
            $weightedVolatility += $weight * $params['volatility'];
        }

        return [
            'params' => [
                'expected_return_typical' => $weightedReturn,
                'volatility' => $weightedVolatility,
                'expected_return_min' => $weightedReturn * 0.7,
                'expected_return_max' => $weightedReturn * 1.3,
            ],
            'source' => $hasProfileRisk ? 'profile' : 'default',
        ];
    }

    private function determineRiskLevel(float $typicalReturn): string
    {
        if ($typicalReturn <= 2.5) {
            return 'low';
        }
        if ($typicalReturn <= 4.25) {
            return 'lower_medium';
        }
        if ($typicalReturn <= 5.75) {
            return 'medium';
        }
        if ($typicalReturn <= 7.25) {
            return 'upper_medium';
        }

        return 'high';
    }

    private function formatAccountType(string $type): string
    {
        return match ($type) {
            'isa' => 'ISA',
            'gia' => 'GIA',
            'sipp' => 'SIPP',
            default => ucfirst($type),
        };
    }

    /**
     * Get the 80% probability (percentile_20) projected value for a single account.
     * Used by Retirement Income Planner to get Monte Carlo projections.
     */
    public function getAccountProjectedValue80(InvestmentAccount $account, User $user, int $years): float
    {
        $value = $this->getUserShareValue($account);
        $monthlyContribution = $this->contributionEstimator->estimateMonthlyContribution($account);

        // Get risk level
        $mainRiskLevel = $this->riskService->getMainRiskLevel($user->id);
        $riskLevel = $account->risk_preference ?? $mainRiskLevel ?? 'medium';
        $riskParams = $this->riskService->getReturnParameters($riskLevel);

        // Cache key for this projection
        $cacheKey = "user_{$user->id}_account_{$account->id}_{$years}y_p20";

        $simulation = $this->simulator->simulate(
            $value,
            $monthlyContribution,
            $riskParams['expected_return_typical'] / 100,
            $riskParams['volatility'] / 100,
            $years,
            self::MONTE_CARLO_ITERATIONS,
            $cacheKey
        );

        $yearByYear = $this->extractProbabilityBands($simulation);
        $finalYear = end($yearByYear);

        return (float) ($finalYear['percentile_20'] ?? $value);
    }

    /**
     * Calculate projections for a single account with optional risk level override.
     */
    public function getAccountProjectionWithRiskOverride(
        InvestmentAccount $account,
        User $user,
        ?string $riskLevelOverride = null,
        array $periods = self::DEFAULT_PROJECTION_PERIODS
    ): array {
        return $this->buildAccountProjection($account, $user, $riskLevelOverride, $periods);
    }

    /**
     * Build account projection.
     */
    private function buildAccountProjection(
        InvestmentAccount $account,
        User $user,
        ?string $riskLevelOverride,
        array $periods
    ): array {
        $value = $this->getUserShareValue($account);
        $monthlyContribution = $this->contributionEstimator->estimateMonthlyContribution($account);

        $mainRiskLevel = $this->riskService->getMainRiskLevel($user->id);
        $riskSource = 'default';

        if ($riskLevelOverride !== null) {
            $riskLevel = $riskLevelOverride;
            $riskSource = 'override';
        } elseif ($account->risk_preference !== null) {
            $riskLevel = $account->risk_preference;
            $riskSource = 'profile';
        } elseif ($mainRiskLevel !== null) {
            $riskLevel = $mainRiskLevel;
            $riskSource = 'profile';
        } else {
            $riskLevel = 'medium';
        }

        $riskParams = $this->riskService->getReturnParameters($riskLevel);

        $projections = [];
        foreach ($periods as $years) {
            // Cache key - null if there's an override (what-if scenario)
            $cacheKey = ($riskLevelOverride === null)
                ? "user_{$user->id}_account_{$account->id}_{$years}y"
                : null;

            $simulation = $this->simulator->simulate(
                $value,
                $monthlyContribution,
                $riskParams['expected_return_typical'] / 100,
                $riskParams['volatility'] / 100,
                $years,
                self::MONTE_CARLO_ITERATIONS,
                $cacheKey
            );

            $yearByYear = $this->extractProbabilityBands($simulation);
            $finalYear = end($yearByYear);

            $projections[$years] = [
                'years' => $years,
                'median_value' => $finalYear['percentile_50'] ?? $value,
                'percentiles' => [
                    'p10' => $finalYear['percentile_10'] ?? $value,
                    'p15' => $finalYear['percentile_15'] ?? $value,
                    'p20' => $finalYear['percentile_20'] ?? $value,
                    'p25' => $finalYear['percentile_25'] ?? $value,
                    'p50' => $finalYear['percentile_50'] ?? $value,
                    'p75' => $finalYear['percentile_75'] ?? $value,
                    'p90' => $finalYear['percentile_90'] ?? $value,
                ],
                'year_by_year' => $yearByYear,
            ];
        }

        return [
            'account_id' => $account->id,
            'account_name' => $account->provider.' '.$this->formatAccountType($account->account_type),
            'account_type' => $account->account_type,
            'current_value' => round($value, 2),
            'estimated_monthly_contribution' => round($monthlyContribution, 2),
            'risk_level' => $riskLevel,
            'risk_source' => $riskSource,
            'expected_return' => $riskParams['expected_return_typical'],
            'volatility' => $riskParams['volatility'],
            'projections' => $projections,
        ];
    }

    /**
     * Invalidate cached projections for a user (call when accounts change).
     */
    public function invalidateUserProjections(int $userId): void
    {
        $this->simulator->clearUserCache($userId);
    }

    /**
     * Invalidate cached projections for an account (call when account is updated).
     */
    public function invalidateAccountProjections(int $accountId): void
    {
        // This will be handled by clearUserCache when user updates account
    }
}
