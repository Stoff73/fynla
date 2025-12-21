<?php

declare(strict_types=1);

namespace App\Services\Investment;

use App\Models\Investment\InvestmentAccount;
use App\Models\User;
use App\Services\Risk\RiskPreferenceService;
use Illuminate\Support\Collection;

class InvestmentProjectionService
{
    private const DEFAULT_PROJECTION_PERIODS = [5, 10, 20, 30];
    private const MONTE_CARLO_ITERATIONS = 1000;

    public function __construct(
        private MonteCarloSimulator $simulator,
        private RiskPreferenceService $riskService,
        private ContributionEstimatorService $contributionEstimator
    ) {}

    /**
     * Get complete portfolio projections with account breakdowns.
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
        $totalValue = $accounts->sum('current_value');
        $monthlyContribution = $this->contributionEstimator->estimatePortfolioContribution(
            $accounts,
            $contributionOverrides
        );

        // Calculate weighted portfolio risk
        $riskParams = $this->calculatePortfolioRisk($accounts, $user);
        $riskLevel = $this->determineRiskLevel($riskParams['expected_return_typical']);

        $projections = [];
        foreach ($periods as $years) {
            $simulation = $this->simulator->simulate(
                $totalValue,
                $monthlyContribution,
                $riskParams['expected_return_typical'] / 100,
                $riskParams['volatility'] / 100,
                $years,
                self::MONTE_CARLO_ITERATIONS
            );

            $yearByYear = $this->extractProbabilityBands($simulation);
            $finalYear = end($yearByYear);

            $projections[$years] = [
                'years' => $years,
                'median_value' => $finalYear['percentile_50'] ?? $totalValue,
                'percentiles' => [
                    'p5' => $finalYear['percentile_5'] ?? $totalValue,
                    'p10' => $finalYear['percentile_10'] ?? $totalValue,
                    'p15' => $finalYear['percentile_15'] ?? $totalValue,
                    'p20' => $finalYear['percentile_20'] ?? $totalValue,
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
        $value = (float) $account->current_value;
        $monthlyContribution = $this->contributionEstimator->estimateMonthlyContribution(
            $account,
            $contributionOverride
        );

        // Get risk level for this account
        $riskLevel = $account->risk_preference
            ?? $this->riskService->getMainRiskLevel($user->id)
            ?? 'medium';

        $riskParams = $this->riskService->getReturnParameters($riskLevel);

        $projections = [];
        foreach ($periods as $years) {
            $simulation = $this->simulator->simulate(
                $value,
                $monthlyContribution,
                $riskParams['expected_return_typical'] / 100,
                $riskParams['volatility'] / 100,
                $years,
                self::MONTE_CARLO_ITERATIONS
            );

            $yearByYear = $this->extractProbabilityBands($simulation);
            $finalYear = end($yearByYear);

            $projections[$years] = [
                'years' => $years,
                'median_value' => $finalYear['percentile_50'] ?? $value,
                'percentiles' => [
                    'p5' => $finalYear['percentile_5'] ?? $value,
                    'p10' => $finalYear['percentile_10'] ?? $value,
                    'p15' => $finalYear['percentile_15'] ?? $value,
                    'p20' => $finalYear['percentile_20'] ?? $value,
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
            'expected_return' => $riskParams['expected_return_typical'],
            'volatility' => $riskParams['volatility'],
            'projections' => $projections,
        ];
    }

    /**
     * Extract probability bands from Monte Carlo results.
     * Matches RetirementProjectionService pattern.
     */
    private function extractProbabilityBands(array $simulation): array
    {
        $result = [];
        $currentYear = (int) date('Y');

        foreach ($simulation['year_by_year'] as $yearData) {
            $yearIndex = $yearData['year'];
            $percentiles = $yearData['percentiles'];

            $p10 = $this->getPercentileValue($percentiles, '10th');
            $p25 = $this->getPercentileValue($percentiles, '25th');
            $p50 = $this->getPercentileValue($percentiles, '50th');
            $p75 = $this->getPercentileValue($percentiles, '75th');
            $p90 = $this->getPercentileValue($percentiles, '90th');

            // Interpolate 5th, 15th, 20th (same as Retirement)
            $spread = $p25 - $p10;
            $p5 = $p10 - ($spread * 0.33);
            $p15 = $p10 + ($spread * 0.33);
            $p20 = $p10 + ($spread * 0.67);

            $result[] = [
                'year' => $currentYear + $yearIndex,
                'year_number' => $yearIndex,
                'percentile_5' => round(max(0, $p5), 2),
                'percentile_10' => round($p10, 2),
                'percentile_15' => round($p15, 2),
                'percentile_20' => round($p20, 2),
                'percentile_50' => round($p50, 2),
                'percentile_75' => round($p75, 2),
                'percentile_90' => round($p90, 2),
            ];
        }

        return $result;
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

    private function calculatePortfolioRisk(Collection $accounts, User $user): array
    {
        $totalValue = $accounts->sum('current_value');

        if ($totalValue <= 0) {
            return $this->riskService->getReturnParameters('medium');
        }

        $weightedReturn = 0.0;
        $weightedVolatility = 0.0;

        foreach ($accounts as $account) {
            $weight = (float) $account->current_value / $totalValue;
            $riskLevel = $account->risk_preference
                ?? $this->riskService->getMainRiskLevel($user->id)
                ?? 'medium';

            $params = $this->riskService->getReturnParameters($riskLevel);
            $weightedReturn += $weight * $params['expected_return_typical'];
            $weightedVolatility += $weight * $params['volatility'];
        }

        return [
            'expected_return_typical' => $weightedReturn,
            'volatility' => $weightedVolatility,
            'expected_return_min' => $weightedReturn * 0.7,
            'expected_return_max' => $weightedReturn * 1.3,
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
}
