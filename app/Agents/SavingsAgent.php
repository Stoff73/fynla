<?php

declare(strict_types=1);

namespace App\Agents;

use App\Models\Goal;
use App\Models\Investment\InvestmentAccount;
use App\Models\LifeEvent;
use App\Models\SavingsAccount;
use App\Models\SavingsGoal;
use App\Models\User;
use App\Services\Goals\GoalProgressService;
use App\Services\Plans\PlanConfigService;
use App\Services\Savings\EmergencyFundCalculator;
use App\Services\Savings\FSCSAssessor;
use App\Services\Savings\GoalProgressCalculator;
use App\Services\Savings\ISATracker;
use App\Services\Savings\LiquidityAnalyzer;
use App\Services\Savings\PSACalculator;
use App\Services\Savings\RateComparator;
use App\Services\Savings\SavingsActionDefinitionService;
use App\Services\Savings\SavingsDataReadinessService;
use App\Traits\ResolvesExpenditure;

class SavingsAgent extends BaseAgent
{
    use ResolvesExpenditure;

    protected int $cacheTtl = 1800;

    public function __construct(
        private readonly EmergencyFundCalculator $emergencyFundCalculator,
        private readonly ISATracker $isaTracker,
        private readonly GoalProgressCalculator $goalProgressCalculator,
        private readonly LiquidityAnalyzer $liquidityAnalyzer,
        private readonly RateComparator $rateComparator,
        private readonly SavingsDataReadinessService $readinessService,
        private readonly ?SavingsActionDefinitionService $actionDefinitionService = null,
        private readonly ?PSACalculator $psaCalculator = null,
        private readonly ?FSCSAssessor $fscsAssessor = null,
        private readonly ?PlanConfigService $planConfig = null,
        private readonly ?GoalProgressService $goalProgressService = null
    ) {
        if ($this->planConfig) {
            $this->cacheTtl = $this->planConfig->getSavingsCacheTTL();
        }
    }

    /**
     * Analyze user's savings situation
     */
    public function analyze(int $userId): array
    {
        // Data readiness gate — return early if blocking checks fail
        $user = User::with('savingsAccounts')->find($userId);
        if ($user) {
            $readiness = $this->readinessService->assess($user);
            if (! $readiness['can_proceed']) {
                return [
                    'can_proceed' => false,
                    'readiness_checks' => $readiness,
                    'summary' => null,
                    'emergency_fund' => null,
                    'isa_allowance' => null,
                    'liquidity' => null,
                    'rate_comparisons' => null,
                    'goals' => null,
                ];
            }
        }

        return $this->remember("savings_analysis_{$userId}", function () use ($userId, $user) {
            // User already loaded above with savings accounts eager-loaded
            $accounts = $user?->savingsAccounts ?? collect();
            $goals = SavingsGoal::where('user_id', $userId)->get();

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

            // PSA position (Personal Savings Allowance)
            $psaPosition = null;
            if ($this->psaCalculator && $user) {
                try {
                    $psaPosition = $this->psaCalculator->assessPSAPosition($user);
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            // FSCS exposure
            $fscsExposure = null;
            if ($this->fscsAssessor && $accounts->isNotEmpty()) {
                try {
                    $fscsExposure = $this->fscsAssessor->assessExposure($accounts);
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            // Employment-based emergency fund target
            $emergencyFundTarget = $this->calculateEmploymentBasedTarget($user, $monthlyExpenditure);

            // Per-child Junior ISA status
            $childrenSavings = $this->buildChildrenSavingsStatus($user, $accounts);

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
                    'target' => $emergencyFundTarget,
                ],
                'isa_allowance' => $isaAllowance,
                'psa_position' => $psaPosition,
                'fscs_exposure' => $fscsExposure,
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
                'children_savings' => $childrenSavings,
            ];
        }, null, ['savings', 'user_'.$userId]);
    }

    /**
     * Generate personalized recommendations.
     *
     * Delegates to SavingsActionDefinitionService when available (DB-driven),
     * falling back to inline logic for backward compatibility.
     */
    public function generateRecommendations(array $analysisData): array
    {
        // Delegate to DB-driven action definition service if available
        if ($this->actionDefinitionService) {
            $userId = $analysisData['user_id'] ?? 0;

            $savingsAccounts = $userId > 0
                ? SavingsAccount::forUserOrJoint($userId)->get()
                : collect();
            $investmentAccounts = $userId > 0
                ? InvestmentAccount::forUserOrJoint($userId)->get()
                : collect();

            $investmentAnalysis = $analysisData['investment_analysis'] ?? [];

            $result = $this->actionDefinitionService->evaluateAgentActions(
                $analysisData,
                $investmentAnalysis,
                $savingsAccounts,
                $investmentAccounts,
                $userId
            );

            $recommendations = $result['recommendations'] ?? [];
            $goalRecommendations = $this->buildGoalRecommendations($analysisData, $userId);

            return array_merge($recommendations, $goalRecommendations);
        }

        // Fallback: inline recommendation logic for backward compatibility
        return $this->generateInlineRecommendations($analysisData);
    }

    /**
     * Inline recommendation logic (legacy fallback).
     */
    private function generateInlineRecommendations(array $analysisData): array
    {
        $recommendations = [];

        // Emergency Fund Recommendations
        if (($analysisData['emergency_fund']['adequacy']['adequacy_score'] ?? 100) < 100) {
            $shortfall = $analysisData['emergency_fund']['adequacy']['shortfall'] ?? 0;
            $monthlyTopUp = $this->emergencyFundCalculator->calculateMonthlyTopUp(
                $shortfall * ($analysisData['summary']['monthly_expenditure'] ?? 0),
                12
            );

            $recommendations[] = [
                'category' => 'emergency_fund',
                'priority' => 'high',
                'title' => 'Build Emergency Fund',
                'description' => sprintf(
                    'Your emergency fund covers %.1f months of expenses. Aim for 6 months. Consider saving %s per month to reach your target in 12 months.',
                    $analysisData['emergency_fund']['runway_months'] ?? 0,
                    $this->formatCurrency($monthlyTopUp)
                ),
                'action' => 'Set up automatic transfer to emergency fund',
            ];
        }

        // ISA Recommendations
        if (($analysisData['isa_allowance']['remaining'] ?? 0) > 0) {
            $recommendations[] = [
                'category' => 'isa_allowance',
                'priority' => 'medium',
                'title' => 'Use ISA Allowance',
                'description' => sprintf(
                    'You have %s remaining in your ISA allowance for %s. Consider maximising this tax-efficient saving.',
                    $this->formatCurrency($analysisData['isa_allowance']['remaining']),
                    $this->isaTracker->getCurrentTaxYear()
                ),
                'action' => 'Open or contribute to ISA account',
            ];
        }

        // Rate Improvement Recommendations
        foreach ($analysisData['rate_comparisons'] ?? [] as $comparison) {
            if (($comparison['comparison']['category'] ?? '') === 'Poor' && ($comparison['potential_gain'] ?? 0) > 100) {
                $recommendations[] = [
                    'category' => 'rate_improvement',
                    'priority' => 'medium',
                    'title' => 'Switch to Better Rate',
                    'description' => sprintf(
                        '%s account could earn %s more per year with a better rate.',
                        $comparison['institution'] ?? 'Unknown',
                        $this->formatCurrency($comparison['potential_gain'] ?? 0)
                    ),
                    'action' => 'Review market rates and consider switching',
                ];
            }
        }

        // Liquidity Recommendations
        if (($analysisData['liquidity']['summary']['risk_level'] ?? '') === 'High') {
            $recommendations[] = [
                'category' => 'liquidity',
                'priority' => 'high',
                'title' => 'Improve Liquidity',
                'description' => 'Too much of your savings is locked in fixed-term accounts. Consider maintaining more easily accessible funds.',
                'action' => 'Review account mix and increase liquid savings',
            ];
        }

        // Goal-aware recommendations
        $userId = $analysisData['user_id'] ?? 0;
        $goalRecommendations = $this->buildGoalRecommendations($analysisData, $userId);

        return array_merge($recommendations, $goalRecommendations);
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
     * Calculate employment-based emergency fund target.
     *
     * Self-employed/contractors: 9 months; unemployed/career break: 12 months; otherwise: 6 months.
     */
    private function calculateEmploymentBasedTarget(?User $user, float $monthlyExpenditure): array
    {
        $baseMonths = 6;

        if ($user && ! empty($user->employment_status)) {
            $targetMonths = match ($user->employment_status) {
                'self_employed', 'contractor', 'freelance' => 9,
                'unemployed', 'career_break' => 12,
                default => $baseMonths,
            };
        } else {
            $targetMonths = $baseMonths;
        }

        return [
            'target_months' => $targetMonths,
            'target_amount' => $this->roundToPenny($monthlyExpenditure * $targetMonths),
            'employment_status' => $user->employment_status ?? null,
            'rationale' => match ($targetMonths) {
                9 => 'Self-employed and contractor income can be irregular, so a larger buffer is recommended.',
                12 => 'During periods without employment, a 12-month fund provides essential security.',
                default => 'The standard recommendation is 6 months of essential expenditure.',
            },
        ];
    }

    /**
     * Build per-child savings status including Junior ISA details.
     */
    private function buildChildrenSavingsStatus(?User $user, $accounts): array
    {
        if (! $user) {
            return [];
        }

        $children = $user->familyMembers()
            ->where('relationship', 'child')
            ->get();

        if ($children->isEmpty()) {
            return [];
        }

        $isaConfig = $this->isaTracker->getTotalAllowance($this->isaTracker->getCurrentTaxYear());
        $jisaAllowance = 9000.0; // Default JISA allowance

        // Try to get from tax config
        try {
            $isaAllowances = app(\App\Services\TaxConfigService::class)->getISAAllowances();
            $jisaAllowance = (float) ($isaAllowances['junior_isa']['annual_allowance'] ?? 9000);
        } catch (\Throwable $e) {
            // Use default
        }

        return $children->map(function ($child) use ($accounts, $jisaAllowance) {
            $dob = $child->date_of_birth;
            $age = $dob ? (int) \Carbon\Carbon::parse($dob)->age : null;
            $isUnder18 = $age !== null && $age < 18;

            // Find JISA accounts for this child
            $jisaAccounts = $accounts->filter(
                fn ($a) => $a->is_isa && $a->isa_type === 'junior_isa' && $a->beneficiary_id === $child->id
            );

            $totalJisaBalance = $jisaAccounts->sum('current_balance');
            $totalJisaSubscription = $jisaAccounts->sum('isa_subscription_amount');
            $jisaRemaining = max(0, $jisaAllowance - (float) $totalJisaSubscription);

            // Find non-JISA savings for this child
            $otherAccounts = $accounts->filter(
                fn ($a) => $a->beneficiary_id === $child->id && (! $a->is_isa || $a->isa_type !== 'junior_isa')
            );
            $totalOtherBalance = $otherAccounts->sum('current_balance');

            return [
                'child_id' => $child->id,
                'child_name' => $child->name,
                'age' => $age,
                'is_under_18' => $isUnder18,
                'has_jisa' => $jisaAccounts->isNotEmpty(),
                'jisa_balance' => $this->roundToPenny((float) $totalJisaBalance),
                'jisa_allowance' => $jisaAllowance,
                'jisa_used' => $this->roundToPenny((float) $totalJisaSubscription),
                'jisa_remaining' => $this->roundToPenny($jisaRemaining),
                'other_savings_balance' => $this->roundToPenny((float) $totalOtherBalance),
                'total_savings' => $this->roundToPenny((float) $totalJisaBalance + (float) $totalOtherBalance),
            ];
        })->values()->toArray();
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

    /**
     * Build goal-aware recommendations: behind-schedule goals, emergency fund
     * suggestion, and life event cash buffer.
     *
     * @return array<int, array{category: string, priority: string, title: string, description: string, action: string}>
     */
    private function buildGoalRecommendations(array $analysisData, int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $recommendations = [];

        // Goal behind-schedule recommendations
        $activeGoals = Goal::forUserOrJoint($userId)
            ->where('status', 'active')
            ->where('assigned_module', 'savings')
            ->get();

        if ($this->goalProgressService) {
            foreach ($activeGoals as $goal) {
                $progress = $this->goalProgressService->calculateProgress($goal);
                if (! $progress['is_on_track'] && $progress['current_amount'] > 0) {
                    $remaining = $progress['target_amount'] - $progress['current_amount'];
                    $monthsLeft = max(1, $progress['days_remaining'] / 30);
                    $requiredMonthly = round($remaining / $monthsLeft, 2);

                    $recommendations[] = [
                        'category' => 'goal_behind_schedule',
                        'priority' => $goal->priority ?? 'medium',
                        'title' => "{$goal->goal_name} is behind schedule",
                        'description' => "Your {$goal->goal_name} goal needs {$this->formatCurrency($remaining)} more to reach its target. Consider increasing your monthly contribution to {$this->formatCurrency($requiredMonthly)} per month.",
                        'action' => 'Increase monthly contribution',
                    ];
                }
            }
        }

        // Suggest emergency fund goal if none exists and runway is short
        $hasEmergencyFundGoal = Goal::forUserOrJoint($userId)
            ->where('goal_type', 'emergency_fund')
            ->where('status', 'active')
            ->exists();

        $runwayMonths = $analysisData['emergency_fund']['runway_months'] ?? 0;
        if (! $hasEmergencyFundGoal && $runwayMonths < 3) {
            $monthlyExpenditure = $analysisData['summary']['monthly_expenditure'] ?? 0;
            $targetAmount = $monthlyExpenditure * 3;
            if ($targetAmount > 0) {
                $recommendations[] = [
                    'category' => 'create_emergency_fund_goal',
                    'priority' => 'high',
                    'title' => 'Create an emergency fund goal',
                    'description' => 'You have '.round($runwayMonths, 1)." months of emergency savings. Consider creating an emergency fund goal of {$this->formatCurrency($targetAmount)} to cover 3 months of expenses.",
                    'action' => 'Create emergency fund goal',
                ];
            }
        }

        // Life event cash buffer recommendations (expense events within 12 months)
        $upcomingEvents = LifeEvent::forUserOrJoint($userId)
            ->where('impact_type', 'expense')
            ->where('expected_date', '>', now())
            ->where('expected_date', '<=', now()->addMonths(12))
            ->whereIn('certainty', ['confirmed', 'likely'])
            ->active()
            ->get();

        foreach ($upcomingEvents as $event) {
            $monthsUntil = max(1, (int) now()->diffInMonths($event->expected_date));
            $monthlySaving = round((float) $event->amount / $monthsUntil, 2);

            $recommendations[] = [
                'category' => 'life_event_cash_buffer',
                'priority' => 'high',
                'title' => "Build cash reserve for {$event->event_name}",
                'description' => "{$event->event_name} is expected in {$monthsUntil} months costing {$this->formatCurrency((float) $event->amount)}. Consider saving {$this->formatCurrency($monthlySaving)} per month to prepare.",
                'action' => 'Set up savings for upcoming event',
            ];
        }

        return $recommendations;
    }
}
