<?php

declare(strict_types=1);

namespace App\Agents;

use App\Constants\TaxDefaults;
use App\Models\DBPension;
use App\Models\DCPension;
use App\Models\RetirementProfile;
use App\Models\StatePension;
use App\Services\Investment\AssetAllocationOptimizer;
use App\Services\Investment\FeeAnalyzer;
use App\Services\Investment\MonteCarloSimulator;
use App\Services\Investment\PortfolioAnalyzer;
use App\Services\Investment\TaxEfficiencyCalculator;
use App\Services\Plans\PlanConfigService;
use App\Services\Retirement\AnnualAllowanceChecker;
use App\Services\Retirement\ContributionOptimizer;
use App\Services\Retirement\DecumulationPlanner;
use App\Services\Retirement\PensionPortfolioAnalyzer;
use App\Services\Retirement\PensionProjector;
use App\Services\TaxConfigService;

/**
 * Retirement Agent
 *
 * Orchestrates retirement planning analysis including pension projections,
 * contribution optimization, and decumulation planning.
 */
class RetirementAgent extends BaseAgent
{
    protected int $cacheTtl = 3600;

    public function __construct(
        private readonly PensionProjector $projector,
        private readonly AnnualAllowanceChecker $allowanceChecker,
        private readonly ContributionOptimizer $optimizer,
        private readonly DecumulationPlanner $planner,
        private readonly PensionPortfolioAnalyzer $pensionPortfolioAnalyzer,
        private readonly TaxConfigService $taxConfig,
        // Portfolio optimization services (shared with Investment module)
        private readonly PortfolioAnalyzer $portfolioAnalyzer,
        private readonly MonteCarloSimulator $monteCarloSimulator,
        private readonly AssetAllocationOptimizer $allocationOptimizer,
        private readonly FeeAnalyzer $feeAnalyzer,
        private readonly TaxEfficiencyCalculator $taxCalculator,
        private readonly ?PlanConfigService $planConfig = null
    ) {
        if ($this->planConfig) {
            $this->cacheTtl = $this->planConfig->getRetirementCacheTTL();
        }
    }

    /**
     * Analyze user's retirement position.
     */
    public function analyze(int $userId): array
    {
        $cacheKey = "retirement_analysis_{$userId}";
        $cacheTags = ['retirement', 'user_'.$userId];

        return $this->remember($cacheKey, function () use ($userId) {
            // Get all retirement data
            $profile = RetirementProfile::where('user_id', $userId)->first();
            $dcPensions = DCPension::where('user_id', $userId)->get();
            $dbPensions = DBPension::where('user_id', $userId)->get();
            $statePension = StatePension::where('user_id', $userId)->first();

            if (! $profile) {
                return $this->response(false, 'No retirement profile found', []);
            }

            // Project total retirement income
            $incomeProjection = $this->projector->projectTotalRetirementIncome($userId);

            $targetIncome = (float) $profile->target_retirement_income;
            $statePensionAge = $statePension->state_pension_age ?? 67;
            $retirementAge = $profile->target_retirement_age;

            // Income at retirement: only include state pension if retiring at or after SPA
            $incomeAtRetirement = ($incomeProjection['dc_annual_income'] ?? 0)
                + ($incomeProjection['db_annual_income'] ?? 0);
            $retiresBeforeSPA = $retirementAge < $statePensionAge;
            $statePensionIncome = $incomeProjection['state_pension_income'] ?? 0;

            if (! $retiresBeforeSPA) {
                $incomeAtRetirement += $statePensionIncome;
            }

            $incomeGap = max(0, $targetIncome - $incomeAtRetirement);

            // Income after SPA (when state pension kicks in)
            $incomeAfterSPA = $incomeAtRetirement + ($retiresBeforeSPA ? $statePensionIncome : 0);
            $incomeGapAfterSPA = max(0, $targetIncome - $incomeAfterSPA);

            // Check annual allowance
            $taxYear = $this->taxConfig->getTaxYear();
            $allowance = $this->allowanceChecker->checkAnnualAllowance($userId, $taxYear);

            // Calculate years to retirement
            $yearsToRetirement = max(0, $retirementAge - $profile->current_age);

            // Summary metrics
            $currentDcValue = (float) $dcPensions->sum('current_fund_value');
            $summary = [
                'years_to_retirement' => $yearsToRetirement,
                'target_retirement_age' => $retirementAge,
                'projected_retirement_income' => $incomeAtRetirement,
                'target_retirement_income' => $targetIncome,
                'income_gap' => $incomeGap,
                'retires_before_spa' => $retiresBeforeSPA,
                'state_pension_age' => $statePensionAge,
                'state_pension_income' => $statePensionIncome,
                'income_after_spa' => $retiresBeforeSPA ? $incomeAfterSPA : null,
                'income_gap_after_spa' => $retiresBeforeSPA ? $incomeGapAfterSPA : null,
                'current_dc_value' => $currentDcValue,
                'total_dc_value' => $incomeProjection['dc_total_value'],
                'total_pensions_count' => $dcPensions->count() + $dbPensions->count() + ($statePension ? 1 : 0),
            ];

            // Detailed breakdown
            $breakdown = [
                'dc_pensions' => $this->formatDCPensions($dcPensions, $incomeProjection),
                'db_pensions' => $this->formatDBPensions($dbPensions),
                'state_pension' => $this->formatStatePension($statePension, $incomeProjection),
            ];

            return $this->response(true, 'Retirement analysis completed', [
                'summary' => $summary,
                'income_projection' => $incomeProjection,
                'breakdown' => $breakdown,
                'annual_allowance' => $allowance,
                'profile' => $profile,
            ]);
        }, null, $cacheTags);
    }

    /**
     * Generate retirement recommendations.
     */
    public function generateRecommendations(array $analysisData): array
    {
        $userId = $analysisData['profile']['user_id'];
        $profile = RetirementProfile::find($analysisData['profile']['id']);
        $dcPensions = DCPension::where('user_id', $userId)->get();

        $recommendations = [];
        $priority = 1;

        $incomeGap = $analysisData['summary']['income_gap'] ?? 0;

        // Contribution optimization
        $optimization = $this->optimizer->optimizeContributions($profile, $dcPensions);
        foreach ($optimization['recommendations'] as $rec) {
            $title = match ($rec['type']) {
                'employer_match' => 'Maximise Employer Pension Match',
                'start_contributions' => 'Start Pension Contributions',
                'contribution_increase' => 'Increase Pension Contributions',
                'tax_relief' => 'Optimise Pension Tax Relief',
                default => $rec['message'],
            };

            $recData = [
                'priority' => $priority++,
                'category' => ucfirst($rec['type']),
                'title' => $title,
                'description' => $rec['message'],
                'action' => 'See detailed recommendations',
                'impact' => ucfirst($rec['priority']),
            ];

            // Match account-specific recs to their DC pension by scheme_name
            if (in_array($rec['type'], ['employer_match', 'start_contributions']) && isset($rec['scheme_name'])) {
                $matchedPension = $dcPensions->first(fn ($p) => $p->scheme_name === $rec['scheme_name']);
                if ($matchedPension) {
                    $recData['scope'] = 'account';
                    $recData['account_id'] = $matchedPension->id;
                    $recData['account_name'] = $matchedPension->scheme_name;
                } else {
                    $recData['scope'] = 'portfolio';
                }
            } else {
                $recData['scope'] = 'portfolio';
            }

            $recommendations[] = $recData;
        }

        // Annual allowance warnings
        if ($analysisData['annual_allowance']['has_excess']) {
            $recommendations[] = [
                'priority' => 1, // High priority
                'category' => 'Tax Planning',
                'title' => 'Annual Allowance Exceeded',
                'description' => sprintf(
                    'You have exceeded your annual allowance by £%s. This may result in tax charges.',
                    number_format($analysisData['annual_allowance']['excess_contributions'], 2)
                ),
                'action' => 'Consult with a financial adviser to minimize tax charges.',
                'impact' => 'High',
                'scope' => 'portfolio',
            ];
        }

        // State Pension optimization — only flag if unlikely to reach 35 years through continued employment
        $statePension = StatePension::where('user_id', $userId)->first();
        if ($statePension && $statePension->ni_years_completed < $statePension->ni_years_required) {
            $yearsShort = $statePension->ni_years_required - $statePension->ni_years_completed;
            $yearsUntilSPA = max(0, ($statePension->state_pension_age ?? 67) - ($profile->current_age ?? 0));
            $willReachNaturally = ($statePension->ni_years_completed + $yearsUntilSPA) >= $statePension->ni_years_required;

            if (! $willReachNaturally) {
                $recommendations[] = [
                    'priority' => $priority++,
                    'category' => 'State Pension',
                    'title' => 'National Insurance Gaps',
                    'description' => sprintf(
                        'You need %d more qualifying years but only have %d years until State Pension age. Consider voluntary contributions to fill the gap.',
                        $yearsShort,
                        $yearsUntilSPA
                    ),
                    'action' => 'Check your NI record and consider making voluntary contributions if cost-effective.',
                    'impact' => 'High',
                    'scope' => 'portfolio',
                ];
            }
        }

        // Retirement age adjustment - suggest if income gap > 10% of target
        $targetIncome = $analysisData['summary']['target_retirement_income'] ?? 0;
        $retirementAge = $analysisData['summary']['target_retirement_age'] ?? 0;
        if ($targetIncome > 0 && $incomeGap > ($targetIncome * 0.10) && $retirementAge > 0) {
            $suggestedAge = min($retirementAge + 3, 70);
            $recommendations[] = [
                'priority' => $priority++,
                'category' => 'Retirement Planning',
                'title' => 'Consider Adjusting Retirement Age',
                'description' => sprintf(
                    'Retiring at %d instead of %d would allow additional years of contributions and growth, significantly reducing your income shortfall.',
                    $suggestedAge,
                    $retirementAge
                ),
                'action' => sprintf('Review scenarios for retiring at %d.', $suggestedAge),
                'impact' => 'High',
                'scope' => 'portfolio',
            ];
        }

        return [
            'recommendations' => $recommendations,
            'total_count' => count($recommendations),
            'high_priority_count' => count(array_filter($recommendations, fn ($r) => $r['priority'] <= 2)),
        ];
    }

    /**
     * Build what-if retirement scenarios.
     */
    public function buildScenarios(int $userId, array $parameters): array
    {
        $profile = RetirementProfile::where('user_id', $userId)->first();
        $dcPensions = DCPension::where('user_id', $userId)->get();

        if (! $profile) {
            return $this->response(false, 'No retirement profile found', []);
        }

        $scenarios = [];

        // Scenario 1: Current trajectory
        $scenarios['current'] = $this->buildCurrentScenario($userId, $profile);

        // Scenario 2: Increased contributions (support both parameter names)
        $additionalContribution = $parameters['increased_contribution'] ?? $parameters['additional_contribution'] ?? null;
        if ($additionalContribution) {
            $scenarios['increased_contribution'] = $this->buildIncreasedContributionScenario(
                $userId,
                $profile,
                $dcPensions,
                (float) $additionalContribution
            );
        }

        // Scenario 3: Later retirement age
        if (isset($parameters['later_retirement_age'])) {
            $scenarios['later_retirement'] = $this->buildLaterRetirementScenario(
                $userId,
                $profile,
                (int) $parameters['later_retirement_age']
            );
        }

        // Scenario 4: Lower target income
        if (isset($parameters['lower_target_income'])) {
            $scenarios['lower_target'] = $this->buildLowerTargetScenario(
                $profile,
                (float) $parameters['lower_target_income']
            );
        }

        return $this->response(true, 'Scenarios generated', [
            'scenarios' => $scenarios,
            'comparison' => $this->compareScenarios($scenarios),
        ]);
    }

    /**
     * Build current trajectory scenario.
     */
    private function buildCurrentScenario(int $userId, RetirementProfile $profile): array
    {
        $incomeProjection = $this->projector->projectTotalRetirementIncome($userId);
        $targetIncome = (float) $profile->target_retirement_income;
        $projectedIncome = $incomeProjection['total_projected_income'];

        return [
            'name' => 'Current Trajectory',
            'description' => 'Based on your current contributions and retirement age',
            'retirement_age' => $profile->target_retirement_age,
            'projected_income' => $projectedIncome,
            'target_income' => $targetIncome,
            'income_gap' => $targetIncome - $projectedIncome,
        ];
    }

    /**
     * Build increased contribution scenario.
     */
    private function buildIncreasedContributionScenario(
        int $userId,
        RetirementProfile $profile,
        $dcPensions,
        float $additionalMonthlyContribution
    ): array {
        // Simulate increased contributions
        $yearsToRetirement = max(0, $profile->target_retirement_age - $profile->current_age);
        $additionalAnnualContribution = $additionalMonthlyContribution * 12;
        $growthRate = $this->planConfig?->getDefaultGrowthRate() ?? TaxDefaults::DEFAULT_GROWTH_RATE;
        $withdrawalRate = $this->planConfig?->getWithdrawalRate() ?? TaxDefaults::SAFE_WITHDRAWAL_RATE;

        $additionalValue = 0.0;
        if ($yearsToRetirement > 0 && $growthRate > 0) {
            $additionalValue = $additionalAnnualContribution * ((pow(1 + $growthRate, $yearsToRetirement) - 1) / $growthRate);
        }

        $currentProjection = $this->projector->projectTotalRetirementIncome($userId);
        $newDCValue = $currentProjection['dc_total_value'] + $additionalValue;
        $newDCIncome = $newDCValue * $withdrawalRate;
        $newTotalIncome = $newDCIncome + $currentProjection['db_annual_income'] + $currentProjection['state_pension_income'];

        $targetIncome = (float) $profile->target_retirement_income;

        return [
            'name' => 'Increased Contributions',
            'description' => sprintf('Adding £%s per month to pension contributions', number_format($additionalMonthlyContribution, 2)),
            'retirement_age' => $profile->target_retirement_age,
            'additional_monthly_contribution' => $additionalMonthlyContribution,
            'additional_pot_value' => round($additionalValue, 2),
            'projected_income' => $newTotalIncome,
            'target_income' => $targetIncome,
            'income_gap' => $targetIncome - $newTotalIncome,
        ];
    }

    /**
     * Build later retirement scenario.
     */
    private function buildLaterRetirementScenario(int $userId, RetirementProfile $profile, int $newRetirementAge): array
    {
        $additionalYears = $newRetirementAge - $profile->target_retirement_age;

        // Simulate additional years of contributions and growth
        $dcPensions = DCPension::where('user_id', $userId)->get();
        $currentMonthlyContributions = $dcPensions->sum('monthly_contribution_amount');
        $additionalContributions = ($currentMonthlyContributions * 12) * $additionalYears;

        $currentProjection = $this->projector->projectTotalRetirementIncome($userId);

        // Rough calculation: additional years of growth on current pot plus new contributions
        $growthRate = $this->planConfig?->getDefaultGrowthRate() ?? TaxDefaults::DEFAULT_GROWTH_RATE;
        $withdrawalRate = $this->planConfig?->getWithdrawalRate() ?? TaxDefaults::SAFE_WITHDRAWAL_RATE;
        $additionalGrowth = $currentProjection['dc_total_value'] * (pow(1 + $growthRate, $additionalYears) - 1);
        $additionalFromContributions = $additionalContributions * (1 + $growthRate * ($additionalYears / 2)); // Simplified

        $newDCValue = $currentProjection['dc_total_value'] + $additionalGrowth + $additionalFromContributions;
        $newDCIncome = $newDCValue * $withdrawalRate;
        $newTotalIncome = $newDCIncome + $currentProjection['db_annual_income'] + $currentProjection['state_pension_income'];

        $targetIncome = (float) $profile->target_retirement_income;

        return [
            'name' => 'Later Retirement',
            'description' => sprintf('Retiring at age %d instead of %d', $newRetirementAge, $profile->target_retirement_age),
            'retirement_age' => $newRetirementAge,
            'additional_years' => $additionalYears,
            'projected_income' => $newTotalIncome,
            'target_income' => $targetIncome,
            'income_gap' => $targetIncome - $newTotalIncome,
        ];
    }

    /**
     * Build lower target income scenario.
     */
    private function buildLowerTargetScenario(RetirementProfile $profile, float $newTargetIncome): array
    {
        $userId = $profile->user_id;
        $currentProjection = $this->projector->projectTotalRetirementIncome($userId);
        $projectedIncome = $currentProjection['total_projected_income'];

        return [
            'name' => 'Adjusted Lifestyle',
            'description' => sprintf('Reducing target retirement income to £%s', number_format($newTargetIncome, 2)),
            'retirement_age' => $profile->target_retirement_age,
            'projected_income' => $projectedIncome,
            'target_income' => $newTargetIncome,
            'savings_required' => (float) $profile->target_retirement_income - $newTargetIncome,
            'income_gap' => $newTargetIncome - $projectedIncome,
        ];
    }

    /**
     * Compare scenarios side by side.
     */
    private function compareScenarios(array $scenarios): array
    {
        $comparison = [
            'best_scenario' => null,
            'smallest_gap' => PHP_FLOAT_MAX,
        ];

        foreach ($scenarios as $key => $scenario) {
            $gap = $scenario['income_gap'] ?? PHP_FLOAT_MAX;
            if ($gap < $comparison['smallest_gap']) {
                $comparison['smallest_gap'] = $gap;
                $comparison['best_scenario'] = $key;
            }
        }

        return $comparison;
    }

    /**
     * Format DC pensions for output.
     */
    private function formatDCPensions($dcPensions, array $incomeProjection): array
    {
        $formatted = [];

        foreach ($dcPensions as $pension) {
            $formatted[] = [
                'id' => $pension->id,
                'scheme_name' => $pension->scheme_name,
                'scheme_type' => $pension->scheme_type,
                'provider' => $pension->provider,
                'current_value' => (float) $pension->current_fund_value,
                'monthly_contribution' => (float) $pension->monthly_contribution_amount,
                'projected_value' => (float) ($pension->projected_value_at_retirement ?? 0),
            ];
        }

        return $formatted;
    }

    /**
     * Format DB pensions for output.
     */
    private function formatDBPensions($dbPensions): array
    {
        $formatted = [];

        foreach ($dbPensions as $pension) {
            $formatted[] = [
                'id' => $pension->id,
                'scheme_name' => $pension->scheme_name,
                'scheme_type' => $pension->scheme_type,
                'accrued_annual_pension' => (float) $pension->accrued_annual_pension,
                'normal_retirement_age' => $pension->normal_retirement_age,
            ];
        }

        return $formatted;
    }

    /**
     * Format State Pension for output.
     */
    private function formatStatePension($statePension, array $incomeProjection): ?array
    {
        if (! $statePension) {
            return null;
        }

        return [
            'ni_years_completed' => $statePension->ni_years_completed,
            'ni_years_required' => $statePension->ni_years_required,
            'forecast_annual' => $incomeProjection['state_pension_income'],
            'state_pension_age' => $statePension->state_pension_age,
        ];
    }

    /**
     * Analyze DC pension portfolio holdings (portfolio optimization)
     *
     * Delegates to PensionPortfolioAnalyzer service for:
     * - Risk metrics (Alpha, Beta, Sharpe Ratio, Volatility, Max Drawdown, VaR)
     * - Asset allocation analysis
     * - Diversification scoring
     * - Fee analysis
     */
    public function analyzeDCPensionPortfolio(int $userId, ?int $dcPensionId = null): array
    {
        $cacheKey = $dcPensionId
            ? "dc_pension_{$dcPensionId}_portfolio"
            : "dc_pensions_portfolio_{$userId}";
        $cacheTags = ['retirement', 'user_'.$userId];

        return $this->remember($cacheKey, function () use ($userId, $dcPensionId) {
            return $this->pensionPortfolioAnalyzer->analyze($userId, $dcPensionId);
        }, null, $cacheTags);
    }
}
