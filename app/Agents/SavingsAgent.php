<?php

declare(strict_types=1);

namespace App\Agents;

use App\Events\Eval\EngineCalled;
use App\Models\Goal;
use App\Models\Investment\InvestmentAccount;
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
use App\Services\Shared\CrossModuleAssetAggregator;
use App\Services\Stores\SavingsStore;
use App\Services\TaxConfigService;
use App\Traits\CalculatesOwnershipShare;
use App\Traits\ResolvesExpenditure;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class SavingsAgent extends BaseAgent
{
    use CalculatesOwnershipShare;
    use ResolvesExpenditure;

    protected int $cacheTtl = 1800;

    public function __construct(
        private readonly EmergencyFundCalculator $emergencyFundCalculator,
        private readonly ISATracker $isaTracker,
        private readonly GoalProgressCalculator $goalProgressCalculator,
        private readonly LiquidityAnalyzer $liquidityAnalyzer,
        private readonly RateComparator $rateComparator,
        private readonly SavingsDataReadinessService $readinessService,
        private readonly SavingsStore $savingsStore,
        private readonly CrossModuleAssetAggregator $assetAggregator,
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
        $analyzeStart = microtime(true);
        $result = (function () use ($userId): array {
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
                // Reach, then fraction (F-0019). `$user->savingsAccounts` is a plain
                // hasMany on `user_id`, so a joint account recorded on the spouse's
                // login was invisible here while the same account was counted whole
                // against the recorder — the two halves of W-0238. The store's read
                // is `forUserOrJoint`, and `userShareView` charges each account at
                // this user's share, so every figure below is derived from a
                // reach-complete set at the right fraction.
                $accounts = $this->atUserShare(
                    $user ? $this->savingsStore->forUser($user) : new EloquentCollection,
                    $userId
                );
                $goals = SavingsGoal::where('user_id', $userId)->get();

                // The one home for what this user's cash is worth (Rule 20) — the
                // same figure `/net-worth` and the dashboard's net worth block read,
                // so the card cannot disagree with the total beside it.
                $totalSavings = $this->assetAggregator->calculateCashTotal($userId);

                // Resolve monthly expenditure using standardised fallback chain
                $resolved = $user ? $this->resolveMonthlyExpenditure($user) : ['amount' => 0.0, 'source' => 'none', 'label' => 'Not Set'];
                $monthlyExpenditure = $resolved['amount'];

                // Emergency Fund Analysis
                $runway = $this->emergencyFundCalculator->calculateRunway(
                    $totalSavings,
                    $monthlyExpenditure
                );
                // Runway is measured in months against the employment-based target; no
                // grade label (Rule 12, CSJ 2026-09-09) — every consumer shows months.
                $targetMonths = $this->emergencyFundCalculator->getTargetMonths($user?->employment_status);

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

                // S1.6.b — structured gap list for the LLM to ask about,
                // computed from already-loaded data (no extra queries).
                $missingForQualityAdvice = $this->findMissingForQualityAdvice($user, $resolved, $accounts);

                return [
                    'user_id' => $userId,
                    'summary' => [
                        'total_savings' => $this->roundToPenny($totalSavings),
                        'total_accounts' => $accounts->count(),
                        'total_goals' => $goals->count(),
                        'monthly_expenditure' => $this->roundToPenny($monthlyExpenditure),
                        'expenditure_source' => $resolved['source'],
                        'expenditure_label' => $resolved['label'],
                    ],
                    'emergency_fund' => [
                        // No adequacy score here (Rule 12): every LLM tool result and
                        // API payload is built from this block, and a 0-100 figure in it
                        // was voiced to a user as "44.67 out of 100".
                        'runway_months' => $runway,
                        'target_months' => $targetMonths,
                        'recommendation' => $this->getEmergencyFundRecommendation($runway, $targetMonths),
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
                    'missing_for_quality_advice' => $missingForQualityAdvice,
                ];
            }, null, ['savings', 'user_'.$userId]);
        })();

        $resultPath = match (true) {
            isset($result['can_proceed']) && $result['can_proceed'] === false => 'readiness_blocked',
            isset($result['success']) && $result['success'] === false => 'success_false',
            default => 'happy',
        };
        event(new EngineCalled(
            engine: 'savings_analysis',
            params: ['user_id' => $userId],
            resultSummary: ['result_path' => $resultPath, 'keys_returned' => array_keys($result)],
            durationMs: (int) round((microtime(true) - $analyzeStart) * 1000),
            atMicrotime: microtime(true),
        ));

        return $result;
    }

    /**
     * Per-agent contract gap field (S1.6.b).
     *
     * @param  array{amount: float, source: string, label: string}  $resolvedExpenditure
     * @return list<array{field: string, why: string, severity: 'blocking'|'soft'}>
     */
    private function findMissingForQualityAdvice(?User $user, array $resolvedExpenditure, $accounts): array
    {
        $gaps = [];

        if ($resolvedExpenditure['source'] === 'none' || $resolvedExpenditure['amount'] <= 0) {
            $gaps[] = [
                'field' => 'monthly_expenditure',
                'why' => 'Emergency-fund runway and target amount cannot be calculated without monthly outgoings.',
                'severity' => 'blocking',
            ];
        }

        if ($user && empty($user->employment_status)) {
            $gaps[] = [
                'field' => 'employment_status',
                'why' => 'Self-employed and contractor users need 9 months of cover; the default falls back to 6 without this field.',
                'severity' => 'soft',
            ];
        }

        if ($user && $user->marital_status === 'married' && $user->spouse === null) {
            $gaps[] = [
                'field' => 'spouse_link',
                'why' => 'Joint emergency-fund planning needs the spouse profile linked.',
                'severity' => 'soft',
            ];
        }

        if ($accounts->isEmpty()) {
            $gaps[] = [
                'field' => 'savings_accounts',
                'why' => 'No savings accounts are recorded — runway, ISA usage, and rate comparison are all empty.',
                'severity' => 'blocking',
            ];
        }

        return $gaps;
    }

    /**
     * Generate personalized recommendations.
     *
     * Delegates to SavingsActionDefinitionService when available (DB-driven),
     * falling back to inline logic for backward compatibility.
     */
    public function generateRecommendations(array $analysisData): array
    {
        $start = microtime(true);
        $result = (function () use ($analysisData): array {
            // Delegate to the DB-driven action definition service: agent rows and
            // goal rows are one catalogue (fyn-wiring Batch A). Goal-category
            // recommendations lead, as the plan page has always ordered them.
            if ($this->actionDefinitionService) {
                $userId = (int) ($analysisData['user_id'] ?? 0);

                $savingsAccounts = $userId > 0 && ($savingsUser = User::find($userId))
                    ? app(SavingsStore::class)->forUser($savingsUser)
                    : collect();
                $investmentAccounts = $userId > 0
                    ? InvestmentAccount::forUserOrJoint($userId)->get()
                    : collect();

                $result = $this->actionDefinitionService->evaluateAgentActions(
                    $analysisData,
                    $analysisData['investment_analysis'] ?? [],
                    $savingsAccounts,
                    $investmentAccounts,
                    $userId
                );

                $recs = $result['recommendations'] ?? [];
                usort($recs, fn (array $x, array $y): int => (($y['category'] ?? '') === 'Goal') <=> (($x['category'] ?? '') === 'Goal'));

                return $recs;
            }

            // Fallback: inline recommendation logic for backward compatibility
            return $this->generateInlineRecommendations($analysisData);
        })();

        event(new EngineCalled(
            engine: 'savings_recommendation',
            params: [],
            resultSummary: ['result_path' => 'happy', 'count' => count($result)],
            durationMs: (int) round((microtime(true) - $start) * 1000),
            atMicrotime: microtime(true),
        ));

        return $result;
    }

    /**
     * Inline recommendation logic (legacy fallback).
     */
    private function generateInlineRecommendations(array $analysisData): array
    {
        $recommendations = [];

        // Emergency Fund Recommendations
        //
        // A null score means the runway could not be worked out at all, because
        // no expenditure is recorded (W-0495). `?? 100` reads it as "nothing to
        // raise", which is right: telling a household to build a fund we have
        // not measured is the false recommendation this item exists to stop.
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

        // Goal recommendations live in the seeded catalogue (fyn-wiring Batch A);
        // this fallback only runs when the action definition service is absent.
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
     * Calculate employment-based emergency fund target.
     *
     * Self-employed/contractors: 9 months; unemployed/career break: 12 months; otherwise: 6 months.
     */
    private function calculateEmploymentBasedTarget(?User $user, float $monthlyExpenditure): array
    {
        // One month table for the whole module (fyn-wiring Batch A, F16).
        $targetMonths = $this->emergencyFundCalculator->getTargetMonths($user?->employment_status);

        return [
            'target_months' => $targetMonths,
            'target_amount' => $this->roundToPenny($monthlyExpenditure * $targetMonths),
            'employment_status' => $user?->employment_status,
            'rationale' => match ($targetMonths) {
                9 => 'Self-employed and contractor income can be irregular, so a larger buffer is recommended.',
                3 => 'A stable pension income needs a smaller buffer than earned income.',
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
            $isaAllowances = app(TaxConfigService::class)->getISAAllowances();
            $jisaAllowance = (float) ($isaAllowances['junior_isa']['annual_allowance'] ?? 9000);
        } catch (\Throwable $e) {
            // Use default
        }

        return $children->map(function ($child) use ($accounts, $jisaAllowance) {
            $dob = $child->date_of_birth;
            $age = $dob ? (int) Carbon::parse($dob)->age : null;
            $isUnder18 = $age !== null && $age < 18;

            // Find JISA accounts for this child
            $jisaAccounts = $accounts->filter(
                fn ($a) => $a->isJuniorIsa() && $a->beneficiary_id === $child->id
            );

            $totalJisaBalance = $jisaAccounts->sum('current_balance');
            $totalJisaSubscription = $jisaAccounts->sum('isa_subscription_amount');
            $jisaRemaining = max(0, $jisaAllowance - (float) $totalJisaSubscription);

            // Find non-JISA savings for this child
            $otherAccounts = $accounts->filter(
                fn ($a) => $a->beneficiary_id === $child->id && ! $a->isJuniorIsa()
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
    private function getEmergencyFundRecommendation(?float $runway, int $targetMonths): string
    {
        if ($runway === null) {
            return 'Add your monthly expenditure so your emergency fund can be measured in months of cover.';
        }

        $months = number_format($runway, 1);

        return match (true) {
            $runway >= $targetMonths => "Your emergency fund covers {$months} months, at or above your {$targetMonths}-month target.",
            $runway >= $targetMonths / 2 => "Your emergency fund covers {$months} of the {$targetMonths} months you need; keep building it.",
            $runway >= 1 => "Your emergency fund covers {$months} of the {$targetMonths} months you need; make it a priority.",
            default => "Your emergency fund covers less than a month of the {$targetMonths} you need; build it before anything else.",
        };
    }
}
