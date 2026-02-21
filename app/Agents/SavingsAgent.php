<?php

declare(strict_types=1);

namespace App\Agents;

use App\Models\SavingsAccount;
use App\Models\SavingsGoal;
use App\Models\User;
use App\Services\Savings\EmergencyFundCalculator;
use App\Services\Savings\GoalProgressCalculator;
use App\Services\Savings\ISATracker;
use App\Services\Savings\LiquidityAnalyzer;
use App\Services\Savings\RateComparator;
use App\Traits\ResolvesExpenditure;

class SavingsAgent extends BaseAgent
{
    use ResolvesExpenditure;
    protected int $cacheTtl = 1800; // 30 minutes

    public function __construct(
        private readonly EmergencyFundCalculator $emergencyFundCalculator,
        private readonly ISATracker $isaTracker,
        private readonly GoalProgressCalculator $goalProgressCalculator,
        private readonly LiquidityAnalyzer $liquidityAnalyzer,
        private readonly RateComparator $rateComparator
    ) {}

    /**
     * Analyze user's savings situation
     */
    public function analyze(int $userId): array
    {
        return $this->remember("savings_analysis_{$userId}", function () use ($userId) {
            // Get user data
            $accounts = SavingsAccount::where('user_id', $userId)->get();
            $goals = SavingsGoal::where('user_id', $userId)->get();
            $user = User::find($userId);

            $totalSavings = $accounts->sum('current_balance');

            // Resolve monthly expenditure using standardised fallback chain
            $resolved = $user ? $this->resolveMonthlyExpenditure($user) : ['amount' => 0.0, 'source' => 'none', 'label' => 'Not Set'];
            $monthlyExpenditure = $resolved['amount'];

            // Emergency Fund Analysis
            $runway = $this->emergencyFundCalculator->calculateRunway(
                $totalSavings,
                $monthlyExpenditure
            );
            $adequacy = $this->emergencyFundCalculator->calculateAdequacy($runway, 6);
            $adequacyCategory = $this->emergencyFundCalculator->categorizeAdequacy($runway);

            // ISA Allowance Status
            $taxYear = $this->isaTracker->getCurrentTaxYear();
            $isaAllowance = $this->isaTracker->getISAAllowanceStatus($userId, $taxYear);

            // Liquidity Profile
            $liquidityProfile = $this->liquidityAnalyzer->categorizeLiquidity($accounts);
            $liquiditySummary = $this->liquidityAnalyzer->getLiquiditySummary($accounts);
            $liquidityLadder = $this->liquidityAnalyzer->buildLiquidityLadder($accounts);

            // Rate Comparison
            $rateComparisons = $accounts->map(function ($account) {
                return [
                    'account_id' => $account->id,
                    'institution' => $account->institution,
                    'comparison' => $this->rateComparator->compareToMarketRates($account),
                    'potential_gain' => $this->rateComparator->calculateInterestDifference(
                        $account,
                        $this->rateComparator->compareToMarketRates($account)['market_rate']
                    ),
                ];
            });

            // Goals Progress
            $goalsProgress = $goals->map(function ($goal) {
                return [
                    'goal_id' => $goal->id,
                    'goal_name' => $goal->goal_name,
                    'priority' => $goal->priority,
                    'progress' => $this->goalProgressCalculator->calculateProgress($goal),
                ];
            });

            $prioritizedGoals = $this->goalProgressCalculator->prioritizeGoals($goals);

            return [
                'summary' => [
                    'total_savings' => $this->roundToPenny($totalSavings),
                    'total_accounts' => $accounts->count(),
                    'total_goals' => $goals->count(),
                    'monthly_expenditure' => $this->roundToPenny($monthlyExpenditure),
                    'expenditure_source' => $resolved['source'],
                    'expenditure_label' => $resolved['label'],
                ],
                'emergency_fund' => [
                    'runway_months' => $runway,
                    'adequacy' => $adequacy,
                    'category' => $adequacyCategory,
                    'recommendation' => $this->getEmergencyFundRecommendation($adequacy),
                ],
                'isa_allowance' => $isaAllowance,
                'liquidity' => [
                    'summary' => $liquiditySummary,
                    'ladder' => $liquidityLadder,
                ],
                'rate_comparisons' => $rateComparisons,
                'goals' => [
                    'progress' => $goalsProgress,
                    'prioritized' => $prioritizedGoals->map(fn ($g) => [
                        'id' => $g->id,
                        'name' => $g->goal_name,
                        'priority' => $g->priority,
                        'target_date' => $g->target_date->format('Y-m-d'),
                    ]),
                ],
            ];
        }, null, ['savings', 'user_'.$userId]);
    }

    /**
     * Generate personalized recommendations
     */
    public function generateRecommendations(array $analysisData): array
    {
        $recommendations = [];

        // Emergency Fund Recommendations
        if ($analysisData['emergency_fund']['adequacy']['adequacy_score'] < 100) {
            $shortfall = $analysisData['emergency_fund']['adequacy']['shortfall'];
            $monthlyTopUp = $this->emergencyFundCalculator->calculateMonthlyTopUp($shortfall * $analysisData['summary']['monthly_expenditure'], 12);

            $recommendations[] = [
                'category' => 'emergency_fund',
                'priority' => 'high',
                'title' => 'Build Emergency Fund',
                'description' => sprintf(
                    'Your emergency fund covers %.1f months of expenses. Aim for 6 months. Consider saving £%.2f per month to reach your target in 12 months.',
                    $analysisData['emergency_fund']['runway_months'],
                    $monthlyTopUp
                ),
                'action' => 'Set up automatic transfer to emergency fund',
            ];
        }

        // ISA Recommendations
        if ($analysisData['isa_allowance']['remaining'] > 0) {
            $recommendations[] = [
                'category' => 'isa_allowance',
                'priority' => 'medium',
                'title' => 'Utilize ISA Allowance',
                'description' => sprintf(
                    'You have £%.2f remaining in your ISA allowance for %s. Consider maximizing this tax-efficient saving.',
                    $analysisData['isa_allowance']['remaining'],
                    $this->isaTracker->getCurrentTaxYear()
                ),
                'action' => 'Open or contribute to ISA account',
            ];
        }

        // Rate Improvement Recommendations
        foreach ($analysisData['rate_comparisons'] as $comparison) {
            if ($comparison['comparison']['category'] === 'Poor' && $comparison['potential_gain'] > 100) {
                $recommendations[] = [
                    'category' => 'rate_improvement',
                    'priority' => 'medium',
                    'title' => 'Switch to Better Rate',
                    'description' => sprintf(
                        '%s account could earn £%.2f more per year with a better rate.',
                        $comparison['institution'],
                        $comparison['potential_gain']
                    ),
                    'action' => 'Review market rates and consider switching',
                ];
            }
        }

        // Liquidity Recommendations
        if ($analysisData['liquidity']['summary']['risk_level'] === 'High') {
            $recommendations[] = [
                'category' => 'liquidity',
                'priority' => 'high',
                'title' => 'Improve Liquidity',
                'description' => 'Too much of your savings is locked in fixed-term accounts. Consider maintaining more easily accessible funds.',
                'action' => 'Review account mix and increase liquid savings',
            ];
        }

        return $recommendations;
    }

    /**
     * Build what-if scenarios
     */
    public function buildScenarios(int $userId, array $parameters): array
    {
        $scenarios = [];

        // Scenario 1: Increased monthly savings
        if (isset($parameters['increased_monthly_savings'])) {
            $amount = $parameters['increased_monthly_savings'];
            $interestRate = $parameters['interest_rate'] ?? 0.04;
            $years = $parameters['years'] ?? 5;

            $finalAmount = $this->calculateFutureValueWithContributions(0, $amount, $interestRate, $years);

            $scenarios['increased_savings'] = [
                'name' => 'Increased Monthly Savings',
                'parameters' => [
                    'monthly_contribution' => $amount,
                    'interest_rate' => $interestRate,
                    'years' => $years,
                ],
                'result' => [
                    'final_amount' => $this->roundToPenny($finalAmount),
                    'total_contributed' => $this->roundToPenny($amount * 12 * $years),
                    'interest_earned' => $this->roundToPenny($finalAmount - ($amount * 12 * $years)),
                ],
            ];
        }

        // Scenario 2: Goal achievement timeline
        if (isset($parameters['goal_id'])) {
            $goal = SavingsGoal::find($parameters['goal_id']);
            if ($goal) {
                $monthlyContribution = $parameters['monthly_contribution'] ?? 0;
                $interestRate = $parameters['interest_rate'] ?? 0.04;

                $projection = $this->goalProgressCalculator->projectGoalAchievement(
                    $goal,
                    $monthlyContribution,
                    $interestRate
                );

                $scenarios['goal_achievement'] = [
                    'name' => 'Goal Achievement Projection',
                    'goal' => $goal->goal_name,
                    'parameters' => [
                        'monthly_contribution' => $monthlyContribution,
                        'interest_rate' => $interestRate,
                    ],
                    'result' => $projection,
                ];
            }
        }

        return $scenarios;
    }

    /**
     * Calculate future value with regular contributions
     */
    private function calculateFutureValueWithContributions(
        float $currentAmount,
        float $monthlyContribution,
        float $annualRate,
        int $years
    ): float {
        $monthlyRate = $annualRate / 12;
        $months = $years * 12;

        if ($monthlyRate > 0) {
            $compoundFactor = pow(1 + $monthlyRate, $months);

            return $currentAmount * $compoundFactor
                + $monthlyContribution * (($compoundFactor - 1) / $monthlyRate);
        }

        return $currentAmount + ($monthlyContribution * $months);
    }

    /**
     * Get emergency fund recommendation text
     */
    private function getEmergencyFundRecommendation(array $adequacy): string
    {
        $score = $adequacy['adequacy_score'];

        return match (true) {
            $score >= 100 => 'Your emergency fund is well-funded. Excellent!',
            $score >= 75 => 'Your emergency fund is adequate, but could be improved.',
            $score >= 50 => 'Your emergency fund needs attention. Priority: Medium.',
            default => 'Your emergency fund is critical. Immediate action recommended.',
        };
    }
}
