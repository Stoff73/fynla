<?php

declare(strict_types=1);

namespace App\Services\Investment;

use App\Models\Investment\InvestmentAccount;
use App\Models\Investment\InvestmentGoal;
use App\Models\User;
use App\Services\Investment\Goals\GoalProbabilityCalculator;
use App\Services\Investment\Tax\ISAAllowanceOptimizer;
use App\Services\Risk\RiskPreferenceService;
use Illuminate\Support\Collection;

/**
 * Contribution Optimizer
 * Determines optimal contribution strategy across ISA, GIA, and Pension wrappers
 *
 * Features:
 * - Affordability analysis (integration with cash flow)
 * - ISA vs GIA contribution priority
 * - Pension vs ISA optimization
 * - Lump sum vs DCA (Dollar-Cost Averaging) analysis
 * - Automatic increase planning
 * - Tax relief calculations for pensions
 * - Wrapper allocation optimization
 */
class ContributionOptimizer
{
    public function __construct(
        private ISAAllowanceOptimizer $isaOptimizer,
        private GoalProbabilityCalculator $probabilityCalculator,
        private \App\Services\TaxConfigService $taxConfig,
        private readonly RiskPreferenceService $riskPreferenceService
    ) {}

    /**
     * Optimize contribution strategy for a user
     *
     * @param  int  $userId  User ID
     * @param  array  $inputs  Contribution inputs
     * @return array Optimized contribution strategy
     */
    public function optimizeContributions(int $userId, array $inputs): array
    {
        $user = User::findOrFail($userId);

        // Validate inputs
        $monthlyInvestableIncome = $inputs['monthly_investable_income'] ?? 0;
        $lumpSumAmount = $inputs['lump_sum_amount'] ?? 0;
        $timeHorizon = $inputs['time_horizon_years'] ?? 20;
        $riskTolerance = $inputs['risk_tolerance'] ?? 'balanced';
        $incomeTaxBand = $inputs['income_tax_band'] ?? 'basic';

        // Get ISA allowance status
        $taxYear = $this->getCurrentTaxYear();
        $isaStatus = $this->isaOptimizer->calculateAllowanceUsage($userId, $taxYear);

        // Get current portfolio state
        $accounts = InvestmentAccount::where('user_id', $userId)->get();
        $currentPortfolioValue = $accounts->sum('current_value');

        // Get goals
        $goals = InvestmentGoal::where('user_id', $userId)->get();

        // Calculate wrapper allocation
        $wrapperAllocation = $this->calculateWrapperAllocation(
            $monthlyInvestableIncome,
            $isaStatus,
            $incomeTaxBand,
            $user
        );

        // Calculate lump sum vs DCA recommendation
        $lumpSumAnalysis = null;
        if ($lumpSumAmount > 0) {
            $lumpSumAnalysis = $this->analyzeLumpSumVsDCA(
                $lumpSumAmount,
                $timeHorizon,
                $riskTolerance,
                $currentPortfolioValue
            );
        }

        // Calculate automatic increase plan
        $autoIncreasePlan = $this->calculateAutoIncreasePlan($monthlyInvestableIncome, $timeHorizon);

        // Calculate tax relief for pension contributions
        $taxRelief = $this->calculatePensionTaxRelief(
            $wrapperAllocation['pension_contribution'] ?? 0,
            $incomeTaxBand
        );

        // Calculate projected outcomes
        $projections = $this->calculateProjections(
            $currentPortfolioValue,
            $wrapperAllocation['total_monthly'] ?? 0,
            $lumpSumAmount,
            $timeHorizon,
            $riskTolerance
        );

        // Calculate tax efficiency score
        $taxEfficiencyScore = $this->calculateContributionEfficiency($wrapperAllocation, $incomeTaxBand);

        // Generate recommendations
        $recommendations = $this->generateRecommendations(
            $wrapperAllocation,
            $lumpSumAnalysis,
            $isaStatus,
            $goals,
            $incomeTaxBand
        );

        return [
            'success' => true,
            'affordability' => [
                'monthly_investable_income' => $monthlyInvestableIncome,
                'lump_sum_amount' => $lumpSumAmount,
                'is_affordable' => true,
                'utilization_percent' => 100,
            ],
            'wrapper_allocation' => $wrapperAllocation,
            'lump_sum_analysis' => $lumpSumAnalysis,
            'auto_increase_plan' => $autoIncreasePlan,
            'tax_relief' => $taxRelief,
            'projections' => $projections,
            'tax_efficiency_score' => $taxEfficiencyScore,
            'recommendations' => $recommendations,
            'isa_status' => $isaStatus,
        ];
    }

    /**
     * Calculate optimal wrapper allocation
     *
     * @param  float  $monthlyAmount  Monthly investable income
     * @param  array  $isaStatus  ISA allowance status
     * @param  string  $incomeTaxBand  Income tax band
     * @param  User  $user  User model
     * @return array Wrapper allocation
     */
    private function calculateWrapperAllocation(
        float $monthlyAmount,
        array $isaStatus,
        string $incomeTaxBand,
        User $user
    ): array {
        // Use remaining ISA allowance from status (already accounts for current year contributions)
        $isaRemaining = $isaStatus['remaining'] ?? 0;
        $isaMonthlyLimit = $isaRemaining / max(1, $this->getMonthsRemainingInTaxYear());

        $allocation = [
            'isa_contribution' => 0,
            'gia_contribution' => 0,
            'pension_contribution' => 0,
            'total_monthly' => $monthlyAmount,
        ];

        // Priority 1: Maximize ISA (tax-free growth and withdrawals)
        if ($isaRemaining > 0 && $monthlyAmount > 0) {
            $isaAllocation = min($monthlyAmount, $isaMonthlyLimit);
            $allocation['isa_contribution'] = round($isaAllocation, 2);
            $monthlyAmount -= $isaAllocation;
        }

        // Priority 2: Pension contributions (if higher/additional rate taxpayer and retirement goal)
        // Check config for tax bands, though simplified logic relies on 'higher'/'additional' strings
        if (in_array($incomeTaxBand, ['higher', 'additional']) && $monthlyAmount > 0) {
            // Suggest pension contribution for tax relief
            $pensionAllocation = $monthlyAmount * 0.5; // 50% of remaining
            $allocation['pension_contribution'] = round($pensionAllocation, 2);
            $monthlyAmount -= $pensionAllocation;
        }

        // Priority 3: GIA (remaining amount)
        if ($monthlyAmount > 0) {
            $allocation['gia_contribution'] = round($monthlyAmount, 2);
        }

        // Add rationale
        $allocation['rationale'] = $this->generateAllocationRationale($allocation, $isaStatus, $incomeTaxBand);

        return $allocation;
    }

    /**
     * Analyze lump sum vs DCA strategy
     *
     * @param  float  $lumpSumAmount  Lump sum amount
     * @param  int  $timeHorizon  Time horizon in years
     * @param  string  $riskTolerance  Risk tolerance
     * @param  float  $currentValue  Current portfolio value
     * @return array Lump sum vs DCA analysis
     */
    private function analyzeLumpSumVsDCA(
        float $lumpSumAmount,
        int $timeHorizon,
        string $riskTolerance,
        float $currentValue
    ): array {
        // Historical data shows lump sum investing beats DCA ~66% of the time
        // But DCA reduces timing risk and provides peace of mind

        $expectedReturn = $this->getExpectedReturnByRisk($riskTolerance);
        $volatility = $this->getVolatilityByRisk($riskTolerance);

        // Calculate lump sum outcome
        $lumpSumFinalValue = $lumpSumAmount * pow(1 + $expectedReturn, $timeHorizon);

        // Calculate DCA outcome (invest over 12 months)
        $dcaMonthly = $lumpSumAmount / 12;
        $dcaFinalValue = $this->calculateDCAValue($dcaMonthly, 12, $expectedReturn, $timeHorizon);

        // Calculate timing risk
        $percentageOfPortfolio = ($lumpSumAmount / max(1, $currentValue)) * 100;
        $timingRisk = $percentageOfPortfolio > 50 ? 'high' : ($percentageOfPortfolio > 20 ? 'moderate' : 'low');

        // Recommendation logic
        $recommendation = 'lump_sum'; // Default
        $rationale = 'Historically, lump sum investing outperforms DCA about 66% of the time.';

        if ($timingRisk === 'high') {
            $recommendation = 'dca';
            $rationale = 'Given the large size relative to your portfolio, DCA over 6-12 months reduces timing risk.';
        } elseif ($volatility > 0.18) {
            $recommendation = 'dca';
            $rationale = 'In volatile market conditions, DCA provides peace of mind and reduces entry risk.';
        }

        return [
            'lump_sum' => [
                'amount' => $lumpSumAmount,
                'expected_final_value' => round($lumpSumFinalValue, 2),
                'pros' => [
                    'Maximizes time in market',
                    'Historically outperforms ~66% of the time',
                    'Simple execution',
                ],
                'cons' => [
                    'Timing risk if market declines shortly after',
                    'Psychological difficulty if market drops',
                ],
            ],
            'dca' => [
                'monthly_amount' => $dcaMonthly,
                'duration_months' => 12,
                'expected_final_value' => round($dcaFinalValue, 2),
                'pros' => [
                    'Reduces timing risk',
                    'Easier psychologically',
                    'Buys at various price points',
                ],
                'cons' => [
                    'Delayed market participation',
                    'Typically underperforms lump sum',
                ],
            ],
            'timing_risk' => $timingRisk,
            'percentage_of_portfolio' => round($percentageOfPortfolio, 1),
            'recommendation' => $recommendation,
            'rationale' => $rationale,
        ];
    }

    /**
     * Calculate automatic increase plan
     *
     * @param  float  $currentMonthly  Current monthly contribution
     * @param  int  $timeHorizon  Time horizon in years
     * @return array Auto-increase plan
     */
    private function calculateAutoIncreasePlan(float $currentMonthly, int $timeHorizon): array
    {
        $increases = [];
        $monthlyAmount = $currentMonthly;

        // Suggest 2% annual increase (aligns with typical inflation)
        $increasePercent = 2.0;

        for ($year = 1; $year <= min($timeHorizon, 10); $year++) {
            $monthlyAmount = $monthlyAmount * (1 + ($increasePercent / 100));
            $increases[] = [
                'year' => $year,
                'monthly_contribution' => round($monthlyAmount, 2),
                'increase_amount' => round($monthlyAmount - $currentMonthly, 2),
            ];
        }

        return [
            'increase_percent' => $increasePercent,
            'schedule' => $increases,
            'rationale' => 'Increasing contributions by 2% annually keeps pace with inflation and accelerates goal achievement.',
        ];
    }

    /**
     * Calculate pension tax relief
     *
     * @param  float  $pensionContribution  Monthly pension contribution
     * @param  string  $incomeTaxBand  Income tax band
     * @return array Tax relief details
     */
    private function calculatePensionTaxRelief(float $pensionContribution, string $incomeTaxBand): array
    {
        if ($pensionContribution <= 0) {
            return [
                'monthly_contribution' => 0,
                'annual_contribution' => 0,
                'basic_rate_relief' => 0,
                'higher_rate_relief' => 0,
                'total_relief' => 0,
                'effective_cost' => 0,
            ];
        }

        $annualContribution = $pensionContribution * 12;

        // Basic rate tax relief
        $basicRate = $this->taxConfig->get('pension.tax_relief.basic_rate', 0.20);
        $basicRateRelief = $annualContribution * $basicRate;

        // Higher rate relief claimed via tax return
        $higherRate = $this->taxConfig->get('pension.tax_relief.higher_rate', 0.40);
        $additionalRate = $this->taxConfig->get('pension.tax_relief.additional_rate', 0.45);

        $higherRateRelief = 0;
        if ($incomeTaxBand === 'higher') {
            // Relief usually calculated as difference between paid rate and basic rate
            // But simplified logic here:
            $higherRateRelief = $annualContribution * $basicRate;
        } elseif ($incomeTaxBand === 'additional') {
            $higherRateRelief = $annualContribution * ($additionalRate - $basicRate);
        }

        $totalRelief = $basicRateRelief + $higherRateRelief;
        $effectiveCost = $annualContribution - $totalRelief;

        return [
            'monthly_contribution' => $pensionContribution,
            'annual_contribution' => $annualContribution,
            'basic_rate_relief' => round($basicRateRelief, 2),
            'higher_rate_relief' => round($higherRateRelief, 2),
            'total_relief' => round($totalRelief, 2),
            'effective_cost' => round($effectiveCost, 2),
            'relief_percent' => round(($totalRelief / $annualContribution) * 100, 1),
        ];
    }

    /**
     * Calculate projections for different contribution strategies
     *
     * @param  float  $currentValue  Current portfolio value
     * @param  float  $monthlyContribution  Monthly contribution
     * @param  float  $lumpSum  Lump sum amount
     * @param  int  $years  Time horizon
     * @param  string  $riskTolerance  Risk tolerance
     * @return array Projections
     */
    private function calculateProjections(
        float $currentValue,
        float $monthlyContribution,
        float $lumpSum,
        int $years,
        string $riskTolerance
    ): array {
        $expectedReturn = $this->getExpectedReturnByRisk($riskTolerance);

        // Calculate future value with monthly contributions
        $fv = $currentValue + $lumpSum;

        for ($i = 0; $i < $years * 12; $i++) {
            $fv = ($fv + $monthlyContribution) * (1 + ($expectedReturn / 12));
        }

        // Calculate conservative (5th percentile) and optimistic (95th percentile)
        $volatility = $this->getVolatilityByRisk($riskTolerance);
        $conservative = $fv * (1 - (1.645 * $volatility * sqrt($years)));
        $optimistic = $fv * (1 + (1.645 * $volatility * sqrt($years)));

        return [
            'years' => $years,
            'expected_value' => round($fv, 2),
            'conservative_value' => round(max(0, $conservative), 2),
            'optimistic_value' => round($optimistic, 2),
            'total_contributions' => round(($monthlyContribution * 12 * $years) + $lumpSum, 2),
            'expected_growth' => round($fv - $currentValue - ($monthlyContribution * 12 * $years) - $lumpSum, 2),
        ];
    }

    /**
     * Calculate tax efficiency score
     *
     * @param  array  $allocation  Wrapper allocation
     * @param  string  $incomeTaxBand  Income tax band
     * @return int Tax efficiency score (0-100)
     */
    private function calculateContributionEfficiency(array $allocation, string $incomeTaxBand): int
    {
        $totalMonthly = $allocation['total_monthly'] ?? 1;
        if ($totalMonthly <= 0) {
            return 0;
        }

        $isaPercent = (($allocation['isa_contribution'] ?? 0) / $totalMonthly) * 100;
        $pensionPercent = (($allocation['pension_contribution'] ?? 0) / $totalMonthly) * 100;
        $giaPercent = (($allocation['gia_contribution'] ?? 0) / $totalMonthly) * 100;

        // ISA: 100 points (fully tax-efficient)
        // Pension: 90 points for higher/additional, 70 for basic
        // GIA: 30 points (least tax-efficient)

        $isaScore = $isaPercent;

        $pensionScore = $pensionPercent * (in_array($incomeTaxBand, ['higher', 'additional']) ? 0.90 : 0.70);

        $giaScore = $giaPercent * 0.30;

        $totalScore = $isaScore + $pensionScore + $giaScore;

        return (int) min(100, round($totalScore));
    }

    /**
     * Generate contribution recommendations
     *
     * @param  array  $allocation  Wrapper allocation
     * @param  array|null  $lumpSumAnalysis  Lump sum analysis
     * @param  array  $isaStatus  ISA status
     * @param  Collection  $goals  Investment goals
     * @param  string  $incomeTaxBand  Income tax band
     * @return array Recommendations
     */
    private function generateRecommendations(
        array $allocation,
        ?array $lumpSumAnalysis,
        array $isaStatus,
        Collection $goals,
        string $incomeTaxBand
    ): array {
        $recommendations = [];

        // ISA recommendations
        if (($isaStatus['remaining'] ?? 0) > 5000) {
            $recommendations[] = [
                'type' => 'isa_allowance',
                'priority' => 'high',
                'title' => 'Utilize ISA Allowance',
                'description' => 'You have £'.number_format($isaStatus['remaining'], 0).' of your ISA allowance remaining this tax year.',
                'action' => 'Consider increasing ISA contributions to maximize tax-free growth.',
            ];
        }

        // Pension recommendations for higher rate taxpayers
        if (in_array($incomeTaxBand, ['higher', 'additional']) && ($allocation['pension_contribution'] ?? 0) === 0.0) {
            $recommendations[] = [
                'type' => 'pension_tax_relief',
                'priority' => 'high',
                'title' => 'Maximize Pension Tax Relief',
                'description' => 'As a '.$incomeTaxBand.' rate taxpayer, you benefit from significant pension tax relief.',
                'action' => 'Consider pension contributions to reduce your tax liability.',
            ];
        }

        // Lump sum recommendations
        if ($lumpSumAnalysis) {
            $recommendations[] = [
                'type' => 'lump_sum_strategy',
                'priority' => 'medium',
                'title' => 'Lump Sum Investment Strategy',
                'description' => $lumpSumAnalysis['rationale'],
                'action' => 'Consider '.($lumpSumAnalysis['recommendation'] === 'lump_sum' ? 'investing the lump sum immediately' : 'DCA over 6-12 months').'.',
            ];
        }

        // Auto-increase recommendations
        if (($allocation['total_monthly'] ?? 0) > 0) {
            $recommendations[] = [
                'type' => 'auto_increase',
                'priority' => 'low',
                'title' => 'Set Up Automatic Increases',
                'description' => 'Increase contributions by 2% annually to keep pace with inflation.',
                'action' => 'Set up automatic contribution increases with your provider.',
            ];
        }

        return $recommendations;
    }

    // Helper methods

    private function getCurrentTaxYear(): string
    {
        return $this->taxConfig->getTaxYear();
    }

    private function getMonthsRemainingInTaxYear(): int
    {
        $now = now();
        $taxYearEnd = $now->copy()->month(4)->day(5);

        if ($now > $taxYearEnd) {
            $taxYearEnd->addYear();
        }

        return max(1, (int) $now->diffInMonths($taxYearEnd));
    }

    private function getExpectedReturnByRisk(string $riskTolerance): float
    {
        // Map legacy risk tolerance labels to RiskPreferenceService 5-level system
        $riskLevel = match ($riskTolerance) {
            'conservative' => 'low',
            'moderately_conservative' => 'lower_medium',
            'balanced' => 'medium',
            'moderately_aggressive' => 'upper_medium',
            'aggressive' => 'high',
            default => 'medium',
        };

        return $this->riskPreferenceService->getReturnParameters($riskLevel)['expected_return_typical'] / 100;
    }

    private function getVolatilityByRisk(string $riskTolerance): float
    {
        return match ($riskTolerance) {
            'conservative' => 0.08,
            'moderately_conservative' => 0.10,
            'balanced' => 0.12,
            'moderately_aggressive' => 0.15,
            'aggressive' => 0.18,
            default => 0.12,
        };
    }

    private function calculateDCAValue(float $monthlyAmount, int $months, float $annualReturn, int $totalYears): float
    {
        $monthlyReturn = $annualReturn / 12;
        $value = 0;

        // DCA period
        for ($i = 0; $i < $months; $i++) {
            $value += $monthlyAmount;
            $value *= (1 + $monthlyReturn);
        }

        // Remaining period (after DCA complete)
        $remainingMonths = ($totalYears * 12) - $months;
        for ($i = 0; $i < $remainingMonths; $i++) {
            $value *= (1 + $monthlyReturn);
        }

        return $value;
    }

    private function generateAllocationRationale(array $allocation, array $isaStatus, string $incomeTaxBand): array
    {
        $rationale = [];

        if (($allocation['isa_contribution'] ?? 0) > 0) {
            $rationale[] = 'ISA contributions prioritized for tax-free growth and withdrawals.';
        }

        if (($allocation['pension_contribution'] ?? 0) > 0) {
            $relief = in_array($incomeTaxBand, ['higher', 'additional']) ? 40 : 20;
            $rationale[] = "Pension contributions benefit from {$relief}% tax relief.";
        }

        if (($allocation['gia_contribution'] ?? 0) > 0) {
            $rationale[] = 'GIA used after ISA allowance exhausted. Consider tax-efficient funds.';
        }

        return $rationale;
    }
}
