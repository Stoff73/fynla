<?php

declare(strict_types=1);

namespace App\Services\Investment\AssetLocation;

use App\Constants\TaxDefaults;
use App\Models\Investment\InvestmentAccount;
use App\Models\User;
use App\Services\Risk\RiskPreferenceService;
use App\Services\TaxConfigService;
use App\Traits\ResolvesIncome;

/**
 * Asset Location Optimizer
 * Master service for optimizing asset placement across account types
 *
 * Coordinates TaxDragCalculator and AccountTypeRecommender to provide
 * comprehensive asset location analysis and recommendations.
 */
class AssetLocationOptimizer
{
    use ResolvesIncome;

    public function __construct(
        private readonly TaxDragCalculator $taxDragCalculator,
        private readonly AccountTypeRecommender $recommender,
        private readonly TaxConfigService $taxConfig,
        private readonly RiskPreferenceService $riskPreferenceService
    ) {}

    /**
     * Perform comprehensive asset location analysis
     *
     * @param  int  $userId  User ID
     * @param  array  $options  Optional parameters
     * @return array Complete analysis
     */
    public function analyzeAssetLocation(int $userId, array $options = []): array
    {
        $user = User::find($userId);
        if (! $user) {
            return [
                'success' => false,
                'message' => 'User not found',
            ];
        }

        // Build user tax profile
        $userTaxProfile = $this->buildUserTaxProfile($user, $options);

        // Calculate current portfolio tax drag
        $portfolioTaxDrag = $this->taxDragCalculator->calculatePortfolioTaxDrag($userId, $userTaxProfile);

        // Generate placement recommendations
        $recommendations = $this->recommender->generateRecommendations($userId, $userTaxProfile);

        // Calculate current vs optimal allocation
        $allocationAnalysis = $this->analyzeCurrentAllocation($userId);

        // Generate optimization score
        $optimizationScore = $this->calculateOptimizationScore(
            $portfolioTaxDrag,
            $recommendations,
            $allocationAnalysis
        );

        // Generate summary
        $summary = $this->generateSummary(
            $portfolioTaxDrag,
            $recommendations,
            $optimizationScore
        );

        return [
            'success' => true,
            'portfolio_tax_drag' => $portfolioTaxDrag,
            'recommendations' => $recommendations['recommendations'],
            'allocation_analysis' => $allocationAnalysis,
            'optimization_score' => $optimizationScore,
            'summary' => $summary,
            'potential_savings' => [
                'annual' => $recommendations['total_potential_annual_saving'],
                'ten_year' => $recommendations['total_potential_10_year_saving'],
            ],
        ];
    }

    /**
     * Build user tax profile from user data and options
     *
     * @param  User  $user  User
     * @param  array  $options  Options
     * @return array Tax profile
     */
    private function buildUserTaxProfile(User $user, array $options): array
    {
        // Get user income from all sources and calculate tax rate
        $annualIncome = $this->resolveGrossAnnualIncome($user);
        $incomeTaxRate = $this->calculateIncomeTaxRate($annualIncome);

        // CGT rate (basic or higher depending on income)
        $incomeTaxBands = $this->taxConfig->getIncomeTax();
        $higherRateThreshold = (float) ($incomeTaxBands['bands'][0]['upper_limit'] ?? 50270);
        $cgtConfig = $this->taxConfig->getCapitalGainsTax();
        $cgtBasicRate = (float) ($cgtConfig['basic_rate'] ?? TaxDefaults::CGT_BASIC_RATE);
        $cgtHigherRate = (float) ($cgtConfig['higher_rate'] ?? TaxDefaults::CGT_HIGHER_RATE);
        $cgtRate = $annualIncome <= $higherRateThreshold ? $cgtBasicRate : $cgtHigherRate;

        // ISA allowance
        $isaAllowance = $this->taxConfig->getISAAllowances()['annual_allowance'];
        $isaAllowanceUsed = $options['isa_allowance_used'] ?? 0;
        $isaAllowanceRemaining = max(0, $isaAllowance - $isaAllowanceUsed);

        // Years to retirement (for pension tax drag calculation)
        $age = $user->date_of_birth
            ? \Carbon\Carbon::parse($user->date_of_birth)->age
            : 45;
        $yearsToRetirement = max(0, 67 - $age);

        return [
            'annual_income' => $annualIncome,
            'income_tax_rate' => $incomeTaxRate,
            'cgt_rate' => $cgtRate,
            'isa_allowance_remaining' => $isaAllowanceRemaining,
            'cgt_allowance_used' => $options['cgt_allowance_used'] ?? 0,
            'dividend_allowance_used' => $options['dividend_allowance_used'] ?? 0,
            'psa_used' => $options['psa_used'] ?? 0,
            'expected_return' => $options['expected_return'] ?? $this->riskPreferenceService->getReturnParameters(
                $this->riskPreferenceService->getMainRiskLevel($user->id) ?? 'medium'
            )['expected_return_typical'] / 100,
            'years_to_retirement' => $yearsToRetirement,
            'expected_withdrawal_tax_rate' => $options['expected_withdrawal_tax_rate']
                ?? (float) ($this->taxConfig->getIncomeTax()['bands'][0]['rate'] ?? 0.20),
            'prefer_pension' => $options['prefer_pension'] ?? false,
        ];
    }

    /**
     * Calculate income tax rate
     *
     * @param  float  $income  Annual income
     * @return float Tax rate
     */
    private function calculateIncomeTaxRate(float $income): float
    {
        $incomeTax = $this->taxConfig->getIncomeTax();
        $personalAllowance = (float) ($incomeTax['personal_allowance'] ?? 12570);
        $basicRateThreshold = (float) ($incomeTax['bands'][0]['upper_limit'] ?? 50270);
        $additionalRateThreshold = (float) ($incomeTax['bands'][1]['upper_limit'] ?? 125140);
        $basicRate = (float) ($incomeTax['bands'][0]['rate'] ?? 0.20);
        $higherRate = (float) ($incomeTax['bands'][1]['rate'] ?? 0.40);
        $additionalRate = (float) ($incomeTax['bands'][2]['rate'] ?? 0.45);

        if ($income <= $personalAllowance) {
            return 0.0;
        } elseif ($income <= $basicRateThreshold) {
            return $basicRate;
        } elseif ($income <= $additionalRateThreshold) {
            return $higherRate;
        } else {
            return $additionalRate;
        }
    }

    /**
     * Analyze current allocation across account types
     *
     * @param  int  $userId  User ID
     * @return array Allocation analysis
     */
    private function analyzeCurrentAllocation(int $userId): array
    {
        $accounts = InvestmentAccount::where('user_id', $userId)
            ->with('holdings')
            ->get();

        $allocationByType = [
            'isa' => ['value' => 0, 'holdings_count' => 0],
            'gia' => ['value' => 0, 'holdings_count' => 0],
            'pension' => ['value' => 0, 'holdings_count' => 0],
        ];

        $totalValue = 0;

        foreach ($accounts as $account) {
            $accountValue = 0;
            $holdingsCount = 0;

            foreach ($account->holdings as $holding) {
                if ($holding->current_value) {
                    $accountValue += $holding->current_value;
                    $holdingsCount++;
                    $totalValue += $holding->current_value;
                }
            }

            // Categorize account type
            $category = match ($account->account_type) {
                'isa', 'stocks_shares_isa', 'cash_isa', 'lifetime_isa' => 'isa',
                'sipp', 'personal_pension' => 'pension',
                default => 'gia',
            };

            $allocationByType[$category]['value'] += $accountValue;
            $allocationByType[$category]['holdings_count'] += $holdingsCount;
        }

        // Calculate percentages
        foreach ($allocationByType as &$allocation) {
            $allocation['percentage'] = $totalValue > 0
                ? ($allocation['value'] / $totalValue) * 100
                : 0;
        }

        return [
            'total_value' => $totalValue,
            'allocation' => $allocationByType,
            'ideal_allocation' => [
                'isa' => ['percentage' => 40, 'rationale' => 'Maximize tax-free growth for high-return assets'],
                'pension' => ['percentage' => 40, 'rationale' => 'Tax relief on contributions, shelter tax-inefficient assets'],
                'gia' => ['percentage' => 20, 'rationale' => 'Tax-efficient assets with accessible liquidity'],
            ],
        ];
    }

    /**
     * Calculate optimization score (0-100)
     *
     * @param  array  $portfolioTaxDrag  Portfolio tax drag analysis
     * @param  array  $recommendations  Recommendations
     * @param  array  $allocationAnalysis  Allocation analysis
     * @return array Optimization score with breakdown
     */
    private function calculateOptimizationScore(
        array $portfolioTaxDrag,
        array $recommendations,
        array $allocationAnalysis
    ): array {
        $score = 100;
        $deductions = [];

        // Deduct for current tax drag
        $avgTaxDrag = $portfolioTaxDrag['average_tax_drag_percent'] ?? 0;
        if ($avgTaxDrag > 2) {
            $deduction = min(30, ($avgTaxDrag - 2) * 10);
            $score -= $deduction;
            $deductions[] = [
                'reason' => 'High average tax drag',
                'deduction' => $deduction,
            ];
        }

        // Deduct for unrealized savings potential
        $potentialSaving = $recommendations['total_potential_annual_saving'] ?? 0;
        $totalValue = $portfolioTaxDrag['total_portfolio_value'] ?? 0;
        $savingPercent = $totalValue > 0 ? ($potentialSaving / $totalValue) * 100 : 0;

        if ($savingPercent > 0.5) {
            $deduction = min(25, ($savingPercent - 0.5) * 20);
            $score -= $deduction;
            $deductions[] = [
                'reason' => 'Significant optimization opportunity missed',
                'deduction' => $deduction,
            ];
        }

        // Deduct for poor account allocation
        $allocation = $allocationAnalysis['allocation'];
        $giaPercent = $allocation['gia']['percentage'] ?? 0;

        if ($giaPercent > 40) {
            $deduction = min(20, ($giaPercent - 40) * 0.5);
            $score -= $deduction;
            $deductions[] = [
                'reason' => 'Excessive holdings in taxable accounts',
                'deduction' => $deduction,
            ];
        }

        // Deduct for high-priority recommendations not implemented
        $highPriorityCount = count(array_filter(
            $recommendations['recommendations'] ?? [],
            fn ($rec) => $rec['priority'] === 'high'
        ));

        if ($highPriorityCount > 3) {
            $deduction = min(15, ($highPriorityCount - 3) * 3);
            $score -= $deduction;
            $deductions[] = [
                'reason' => 'Multiple high-priority improvements available',
                'deduction' => $deduction,
            ];
        }

        $score = max(0, $score);

        return [
            'score' => round($score, 1),
            'grade' => $this->getGrade($score),
            'interpretation' => $this->getScoreInterpretation($score),
            'deductions' => $deductions,
        ];
    }

    /**
     * Get grade from score
     *
     * @param  float  $score  Score
     * @return string Grade
     */
    private function getGrade(float $score): string
    {
        return match (true) {
            $score >= 90 => 'A',
            $score >= 80 => 'B',
            $score >= 70 => 'C',
            $score >= 60 => 'D',
            $score >= 50 => 'E',
            default => 'F',
        };
    }

    /**
     * Get score interpretation
     *
     * @param  float  $score  Score
     * @return string Interpretation
     */
    private function getScoreInterpretation(float $score): string
    {
        return match (true) {
            $score >= 90 => 'Excellent - Assets are optimally located for tax efficiency',
            $score >= 80 => 'Good - Minor improvements possible',
            $score >= 70 => 'Acceptable - Some optimization opportunities available',
            $score >= 60 => 'Fair - Significant tax savings possible through better asset location',
            $score >= 50 => 'Poor - Substantial tax drag from suboptimal asset placement',
            default => 'Critical - Urgent need to optimize asset location',
        };
    }

    /**
     * Generate executive summary
     *
     * @param  array  $portfolioTaxDrag  Tax drag analysis
     * @param  array  $recommendations  Recommendations
     * @param  array  $optimizationScore  Optimization score
     * @return array Summary
     */
    private function generateSummary(
        array $portfolioTaxDrag,
        array $recommendations,
        array $optimizationScore
    ): array {
        $highPriorityRecs = array_filter(
            $recommendations['recommendations'] ?? [],
            fn ($rec) => $rec['priority'] === 'high'
        );

        return [
            'optimization_grade' => $optimizationScore['grade'],
            'current_tax_drag' => $portfolioTaxDrag['total_annual_tax_drag'],
            'potential_annual_saving' => $recommendations['total_potential_annual_saving'],
            'total_recommendations' => count($recommendations['recommendations'] ?? []),
            'high_priority_count' => count($highPriorityRecs),
            'key_insight' => $this->generateKeyInsight($optimizationScore, $highPriorityRecs),
        ];
    }

    /**
     * Generate key insight message
     *
     * @param  array  $optimizationScore  Optimization score
     * @param  array  $highPriorityRecs  High priority recommendations
     * @return string Key insight
     */
    private function generateKeyInsight(array $optimizationScore, array $highPriorityRecs): string
    {
        if ($optimizationScore['score'] >= 85) {
            return 'Your asset location is well-optimized. Continue maximizing ISA allowance each year.';
        }

        if (count($highPriorityRecs) > 0) {
            $topRec = $highPriorityRecs[0];
            $saving = number_format($topRec['potential_annual_saving'], 0);

            return sprintf(
                'Priority action: %s could save £%s annually.',
                $topRec['action'],
                $saving
            );
        }

        return 'Review recommendations to optimize your asset location and reduce tax drag.';
    }
}
