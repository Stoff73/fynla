<?php

declare(strict_types=1);

namespace App\Services\Investment;

use App\Models\Investment\InvestmentAccount;
use App\Models\Investment\InvestmentGoal;
use App\Models\Investment\InvestmentPlan;
use App\Models\Investment\RiskProfile;
use App\Services\Investment\AssetLocation\AssetLocationOptimizer;
use App\Services\Investment\Goals\GoalProgressAnalyzer;
use App\Services\Investment\Performance\PerformanceAttributionAnalyzer;
use App\Services\Investment\Rebalancing\DriftAnalyzer;
use App\Services\Investment\Tax\TaxOptimizationAnalyzer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Investment Plan Generator Service
 * Generates comprehensive investment plans similar to Protection module's comprehensive plan
 */
class InvestmentPlanGenerator
{
    public function __construct(
        private PortfolioAnalyzer $portfolioAnalyzer,
        private TaxOptimizationAnalyzer $taxAnalyzer,
        private FeeAnalyzer $feeAnalyzer,
        private DriftAnalyzer $driftAnalyzer,
        private PerformanceAttributionAnalyzer $performanceAnalyzer,
        private AssetLocationOptimizer $assetLocationOptimizer,
        private GoalProgressAnalyzer $goalProgressAnalyzer
    ) {}

    /**
     * Generate a comprehensive investment plan for a user
     */
    public function generatePlan(int $userId, array $options = []): array
    {
        Log::info('Generating investment plan', ['user_id' => $userId]);

        try {
            DB::beginTransaction();

            // 1. Executive Summary
            $executiveSummary = $this->generateExecutiveSummary($userId);

            // 2. Current Situation Analysis
            $currentSituation = $this->analyzeCurrentSituation($userId);

            // 3. Goal Progress Review
            $goalProgress = $this->reviewGoalProgress($userId);

            // 4. Risk Analysis
            $riskAnalysis = $this->analyzeRisk($userId);

            // 5. Tax Optimization Strategy
            $taxStrategy = $this->analyzeTaxStrategy($userId);

            // 6. Fee Analysis
            $feeAnalysis = $this->analyzeFees($userId);

            // 7. Recommendations
            $recommendations = $this->generateRecommendations($userId, [
                'executiveSummary' => $executiveSummary,
                'currentSituation' => $currentSituation,
                'goalProgress' => $goalProgress,
                'riskAnalysis' => $riskAnalysis,
                'taxStrategy' => $taxStrategy,
                'feeAnalysis' => $feeAnalysis,
            ]);

            // 8. Action Plan
            $actionPlan = $this->generateActionPlan($recommendations);

            // Build complete plan
            $plan = [
                'executive_summary' => $executiveSummary,
                'current_situation' => $currentSituation,
                'goal_progress' => $goalProgress,
                'risk_analysis' => $riskAnalysis,
                'tax_strategy' => $taxStrategy,
                'fee_analysis' => $feeAnalysis,
                'recommendations' => $recommendations,
                'action_plan' => $actionPlan,
                'metadata' => [
                    'generated_at' => now()->toIso8601String(),
                    'version' => '1.0',
                ],
            ];

            // Save plan to database
            $savedPlan = $this->savePlan($userId, $plan);

            DB::commit();

            return [
                'success' => true,
                'plan' => $plan,
                'plan_id' => $savedPlan->id,
                'portfolio_health_score' => $executiveSummary['portfolio_health_score'],
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Investment plan generation failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Generate executive summary
     */
    private function generateExecutiveSummary(int $userId): array
    {
        $accounts = InvestmentAccount::where('user_id', $userId)->with('holdings')->get();
        $totalValue = $accounts->sum(fn ($account) => $account->holdings->sum('current_value'));

        // Calculate portfolio health score (0-100)
        $healthScore = $this->calculatePortfolioHealthScore($userId);

        // Get top 3 priorities
        $priorities = $this->getTopPriorities($userId);

        // Get asset allocation
        $allocation = $this->portfolioAnalyzer->calculateAllocation($userId);

        return [
            'portfolio_health_score' => $healthScore,
            'total_portfolio_value' => $totalValue,
            'number_of_accounts' => $accounts->count(),
            'number_of_holdings' => $accounts->sum(fn ($account) => $account->holdings->count()),
            'asset_allocation' => $allocation,
            'top_priorities' => $priorities,
            'health_interpretation' => $this->interpretHealthScore($healthScore),
        ];
    }

    /**
     * Analyze current situation
     */
    private function analyzeCurrentSituation(int $userId): array
    {
        $accounts = InvestmentAccount::where('user_id', $userId)->with('holdings')->get();
        $riskProfile = RiskProfile::where('user_id', $userId)->first();

        $allocation = $this->portfolioAnalyzer->calculateAllocation($userId);
        $diversification = $this->portfolioAnalyzer->calculateDiversification($userId);

        return [
            'accounts' => $accounts->map(function ($account) {
                return [
                    'id' => $account->id,
                    'account_name' => $account->account_name,
                    'account_type' => $account->account_type,
                    'platform' => $account->platform,
                    'total_value' => $account->holdings->sum('current_value'),
                    'holdings_count' => $account->holdings->count(),
                ];
            }),
            'asset_allocation' => $allocation,
            'diversification' => $diversification,
            'risk_profile' => $riskProfile ? [
                'risk_level' => $riskProfile->risk_level,
                'risk_score' => $riskProfile->risk_score,
                'target_allocation' => $riskProfile->target_allocation,
            ] : null,
        ];
    }

    /**
     * Review goal progress
     */
    private function reviewGoalProgress(int $userId): array
    {
        $goals = InvestmentGoal::where('user_id', $userId)->get();

        if ($goals->isEmpty()) {
            return [
                'has_goals' => false,
                'message' => 'No investment goals defined yet',
            ];
        }

        $goalsAnalysis = $goals->map(function ($goal) use ($userId) {
            $progress = $this->goalProgressAnalyzer->analyzeGoalProgress($userId, $goal->id);

            return [
                'id' => $goal->id,
                'goal_name' => $goal->goal_name,
                'target_amount' => $goal->target_amount,
                'target_date' => $goal->target_date,
                'progress' => $progress,
                'status' => $this->determineGoalStatus($progress),
            ];
        });

        return [
            'has_goals' => true,
            'total_goals' => $goals->count(),
            'goals' => $goalsAnalysis,
            'on_track_count' => $goalsAnalysis->where('status', 'on_track')->count(),
            'off_track_count' => $goalsAnalysis->where('status', 'off_track')->count(),
        ];
    }

    /**
     * Analyze risk
     */
    private function analyzeRisk(int $userId): array
    {
        $riskMetrics = $this->performanceAnalyzer->getRiskMetrics($userId);
        $stressTest = $this->performStressTest($userId);

        return [
            'risk_metrics' => $riskMetrics,
            'stress_test' => $stressTest,
            'concentration_risk' => $this->analyzeConcentrationRisk($userId),
            'currency_exposure' => $this->analyzeCurrencyExposure($userId),
        ];
    }

    /**
     * Analyze tax strategy
     */
    private function analyzeTaxStrategy(int $userId): array
    {
        return $this->taxAnalyzer->analyzeCompleteTaxPosition($userId);
    }

    /**
     * Analyze fees
     */
    private function analyzeFees(int $userId): array
    {
        $feeAnalysis = $this->feeAnalyzer->analyzePortfolioFees($userId);
        $feeAnalysis['compound_impact'] = $this->calculateCompoundFeeImpact($feeAnalysis);

        return $feeAnalysis;
    }

    /**
     * Generate recommendations
     */
    private function generateRecommendations(int $userId, array $context): array
    {
        $recommendations = [];

        // Rebalancing recommendations
        if ($this->needsRebalancing($userId)) {
            $recommendations[] = [
                'category' => 'rebalancing',
                'priority' => 1,
                'title' => 'Portfolio Rebalancing Required',
                'description' => 'Your portfolio has drifted from your target allocation',
                'action_required' => 'Review and execute rebalancing trades',
                'impact_level' => 'high',
            ];
        }

        // Tax optimization recommendations
        if ($context['taxStrategy']['isa_efficiency'] < 80) {
            $recommendations[] = [
                'category' => 'tax',
                'priority' => 2,
                'title' => 'ISA Allowance Underutilized',
                'description' => 'You have unused ISA allowance for this tax year',
                'action_required' => 'Transfer holdings to ISA to maximize tax efficiency',
                'impact_level' => 'high',
                'potential_saving' => $context['taxStrategy']['potential_isa_savings'] ?? 0,
            ];
        }

        // Fee reduction recommendations
        if ($context['feeAnalysis']['total_annual_fee_percent'] > 1.0) {
            $recommendations[] = [
                'category' => 'fees',
                'priority' => 3,
                'title' => 'High Portfolio Fees',
                'description' => 'Your portfolio fees are above recommended levels',
                'action_required' => 'Consider switching to lower-cost alternatives',
                'impact_level' => 'medium',
                'potential_saving' => $context['feeAnalysis']['potential_annual_saving'] ?? 0,
            ];
        }

        // Sort by priority
        usort($recommendations, fn ($a, $b) => $a['priority'] <=> $b['priority']);

        return $recommendations;
    }

    /**
     * Generate action plan
     */
    private function generateActionPlan(array $recommendations): array
    {
        $immediate = [];
        $shortTerm = [];
        $longTerm = [];

        foreach ($recommendations as $rec) {
            if ($rec['priority'] <= 3) {
                $immediate[] = $rec;
            } elseif ($rec['priority'] <= 6) {
                $shortTerm[] = $rec;
            } else {
                $longTerm[] = $rec;
            }
        }

        return [
            'immediate' => [
                'title' => 'Immediate Actions (Next 30 Days)',
                'actions' => $immediate,
            ],
            'short_term' => [
                'title' => 'Short-term Actions (3-6 Months)',
                'actions' => $shortTerm,
            ],
            'long_term' => [
                'title' => 'Long-term Actions (12+ Months)',
                'actions' => $longTerm,
            ],
        ];
    }

    /**
     * Save plan to database
     */
    private function savePlan(int $userId, array $planData): InvestmentPlan
    {
        $healthScore = $planData['executive_summary']['portfolio_health_score'];

        return InvestmentPlan::create([
            'user_id' => $userId,
            'plan_version' => '1.0',
            'plan_data' => $planData,
            'portfolio_health_score' => $healthScore,
            'is_complete' => true,
            'completeness_score' => 100,
            'generated_at' => now(),
        ]);
    }

    /**
     * Calculate portfolio health score (0-100)
     */
    private function calculatePortfolioHealthScore(int $userId): int
    {
        $scores = [
            'diversification' => $this->scoreDiversification($userId) * 0.25,
            'risk_alignment' => $this->scoreRiskAlignment($userId) * 0.20,
            'tax_efficiency' => $this->scoreTaxEfficiency($userId) * 0.20,
            'fee_efficiency' => $this->scoreFeeEfficiency($userId) * 0.15,
            'goal_progress' => $this->scoreGoalProgress($userId) * 0.20,
        ];

        return (int) round(array_sum($scores));
    }

    /**
     * Helper scoring methods
     */
    private function scoreDiversification(int $userId): int
    {
        $diversification = $this->portfolioAnalyzer->calculateDiversification($userId);

        return min(100, (int) ($diversification['score'] ?? 70));
    }

    private function scoreRiskAlignment(int $userId): int
    {
        // Placeholder: Compare actual vs target allocation
        return 80;
    }

    private function scoreTaxEfficiency(int $userId): int
    {
        $taxAnalysis = $this->taxAnalyzer->analyzeCompleteTaxPosition($userId);

        return (int) ($taxAnalysis['isa_efficiency'] ?? 75);
    }

    private function scoreFeeEfficiency(int $userId): int
    {
        $feeAnalysis = $this->feeAnalyzer->analyzePortfolioFees($userId);
        $avgFee = $feeAnalysis['total_annual_fee_percent'] ?? 1.0;

        // Score based on fees: <0.5% = 100, 1.0% = 70, >1.5% = 40
        if ($avgFee < 0.5) {
            return 100;
        } elseif ($avgFee < 1.0) {
            return 85;
        } elseif ($avgFee < 1.5) {
            return 70;
        } else {
            return 40;
        }
    }

    private function scoreGoalProgress(int $userId): int
    {
        $goals = InvestmentGoal::where('user_id', $userId)->get();

        if ($goals->isEmpty()) {
            return 70; // Neutral score if no goals
        }

        $onTrackCount = $goals->filter(function ($goal) use ($userId) {
            $progress = $this->goalProgressAnalyzer->analyzeGoalProgress($userId, $goal->id);

            return ($progress['funding_ratio'] ?? 0) >= 0.9;
        })->count();

        return (int) (($onTrackCount / $goals->count()) * 100);
    }

    /**
     * Interpret health score
     */
    private function interpretHealthScore(int $score): string
    {
        if ($score >= 90) {
            return 'Excellent - Your portfolio is well-optimized';
        } elseif ($score >= 75) {
            return 'Good - Minor improvements recommended';
        } elseif ($score >= 60) {
            return 'Adequate - Several areas need attention';
        } else {
            return 'Needs Improvement - Significant optimization required';
        }
    }

    /**
     * Get top 3 priorities
     */
    private function getTopPriorities(int $userId): array
    {
        $priorities = [];

        // Check rebalancing
        if ($this->needsRebalancing($userId)) {
            $priorities[] = 'Rebalance portfolio to target allocation';
        }

        // Check tax efficiency
        $taxAnalysis = $this->taxAnalyzer->analyzeCompleteTaxPosition($userId);
        if (($taxAnalysis['isa_efficiency'] ?? 100) < 80) {
            $priorities[] = 'Maximize ISA allowance utilization';
        }

        // Check fees
        $feeAnalysis = $this->feeAnalyzer->analyzePortfolioFees($userId);
        if (($feeAnalysis['total_annual_fee_percent'] ?? 0) > 1.0) {
            $priorities[] = 'Reduce portfolio fees';
        }

        return array_slice($priorities, 0, 3);
    }

    /**
     * Check if portfolio needs rebalancing
     */
    private function needsRebalancing(int $userId): bool
    {
        $riskProfile = RiskProfile::where('user_id', $userId)->first();

        if (! $riskProfile || ! $riskProfile->target_allocation) {
            return false;
        }

        $accounts = InvestmentAccount::where('user_id', $userId)->with('holdings')->get();
        $holdings = $accounts->flatMap->holdings;

        if ($holdings->isEmpty()) {
            return false;
        }

        $driftAnalysis = $this->driftAnalyzer->analyzeDrift($holdings, $riskProfile->target_allocation);

        return ($driftAnalysis['drift_score'] ?? 0) > 75; // Threshold: 75/100
    }

    /**
     * Perform stress test
     */
    private function performStressTest(int $userId): array
    {
        // Placeholder for stress testing
        return [
            'market_crash_20' => ['loss' => -20, 'recovery_months' => 8],
            'market_crash_40' => ['loss' => -40, 'recovery_months' => 24],
        ];
    }

    /**
     * Analyze concentration risk
     */
    private function analyzeConcentrationRisk(int $userId): array
    {
        $accounts = InvestmentAccount::where('user_id', $userId)->with('holdings')->get();
        $holdings = $accounts->flatMap->holdings;
        $totalValue = $holdings->sum('current_value');

        if ($totalValue == 0) {
            return ['has_concentration_risk' => false];
        }

        $concentratedHoldings = $holdings->filter(function ($holding) use ($totalValue) {
            return ($holding->current_value / $totalValue) > 0.10; // >10%
        });

        return [
            'has_concentration_risk' => $concentratedHoldings->isNotEmpty(),
            'concentrated_holdings' => $concentratedHoldings->map(fn ($h) => [
                'name' => $h->holding_name,
                'percentage' => ($h->current_value / $totalValue) * 100,
            ]),
        ];
    }

    /**
     * Analyze currency exposure
     */
    private function analyzeCurrencyExposure(int $userId): array
    {
        // Placeholder: Would analyze currency exposure from holdings
        return [
            'GBP' => 50,
            'USD' => 35,
            'EUR' => 10,
            'OTHER' => 5,
        ];
    }

    /**
     * Calculate compound fee impact
     */
    private function calculateCompoundFeeImpact(array $feeAnalysis): array
    {
        $portfolioValue = $feeAnalysis['total_portfolio_value'] ?? 100000;
        $annualFee = $feeAnalysis['total_annual_fees'] ?? 1000;
        $feePercent = ($annualFee / $portfolioValue) * 100;
        $returnRate = 7.0; // Assumed 7% annual return

        $years = [10, 20, 30];
        $impacts = [];

        foreach ($years as $year) {
            $withoutFees = $portfolioValue * pow(1 + ($returnRate / 100), $year);
            $withFees = $portfolioValue * pow(1 + (($returnRate - $feePercent) / 100), $year);
            $impacts[$year.'_years'] = $withoutFees - $withFees;
        }

        return $impacts;
    }

    /**
     * Determine goal status
     */
    private function determineGoalStatus(array $progress): string
    {
        $fundingRatio = $progress['funding_ratio'] ?? 0;

        if ($fundingRatio >= 0.95) {
            return 'on_track';
        } elseif ($fundingRatio >= 0.80) {
            return 'needs_attention';
        } else {
            return 'off_track';
        }
    }
}
