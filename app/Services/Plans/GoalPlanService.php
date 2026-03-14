<?php

declare(strict_types=1);

namespace App\Services\Plans;

use App\Agents\GoalsAgent;
use App\Models\Goal;
use App\Models\User;
use App\Services\Goals\GoalAffordabilityService;
use App\Services\Goals\GoalProgressService;
use App\Services\Goals\GoalStrategyService;

class GoalPlanService extends BasePlanService
{
    public function __construct(
        private readonly GoalsAgent $goalsAgent,
        private readonly GoalProgressService $progressService,
        private readonly GoalAffordabilityService $affordabilityService,
        private readonly GoalStrategyService $strategyService,
        private readonly PlanConfigService $planConfig,
        private readonly DisposableIncomeAccessor $incomeAccessor
    ) {}

    public function generatePlan(int $userId, array $options = []): array
    {
        $goalId = $options['goal_id'] ?? null;

        if (! $goalId) {
            throw new \InvalidArgumentException('goal_id is required for goal plans.');
        }

        $user = User::findOrFail($userId);
        $goal = Goal::where('id', $goalId)
            ->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)->orWhere('joint_owner_id', $userId);
            })
            ->firstOrFail();

        $completeness = $this->checkDataCompleteness($userId, $goalId);

        $progress = $this->progressService->calculateProgress($goal);
        $affordability = $this->affordabilityService->analyzeAffordability($goal, $user);
        $strategy = $this->strategyService->getStrategyForGoal($goalId);

        $currentSituation = $this->buildCurrentSituation($goal, $progress, $affordability);
        $recommendations = $this->getRecommendations($userId, ['goal_id' => $goalId]);
        ['actions' => $actions, 'enabledActions' => $enabledActions] = $this->prepareActions($recommendations, 'goal', $options);

        $whatIf = $this->buildWhatIfData($user, $goal, $progress, $affordability, $enabledActions);
        $conclusion = $this->generateDynamicConclusion($currentSituation, $enabledActions, 'goal');

        return [
            'metadata' => $this->buildPlanMetadata($user, 'goal', $completeness),
            'goal' => [
                'id' => $goal->id,
                'name' => $goal->goal_name,
                'type' => $goal->goal_type,
                'status' => $goal->status,
                'priority' => $goal->priority,
                'assigned_module' => $goal->assigned_module,
            ],
            'completeness_warning' => $this->buildCompletenessWarning($completeness),
            'executive_summary' => $this->buildExecutiveSummary($user, $goal, $progress, $affordability),
            'current_situation' => $currentSituation,
            'actions' => $actions,
            'what_if' => $whatIf,
            'conclusion' => $conclusion,
        ];
    }

    public function getRecommendations(int $userId, ?array $preComputedData = null): array
    {
        $goalId = $preComputedData['goal_id'] ?? null;

        $analysis = $this->goalsAgent->analyze($userId);
        $recs = $this->goalsAgent->generateRecommendations($analysis);
        $allRecommendations = $recs['recommendations'] ?? [];

        if (! $goalId) {
            return $allRecommendations;
        }

        // Also get goal-specific scenarios
        $scenarios = $this->goalsAgent->buildScenarios($userId, ['goal_id' => $goalId]);
        $scenarioRecs = [];

        foreach ($scenarios['data']['scenarios'] ?? $scenarios['scenarios'] ?? [] as $scenario) {
            if (isset($scenario['title']) || isset($scenario['description'])) {
                $scenarioRecs[] = [
                    'category' => 'Scenario',
                    'priority' => 'medium',
                    'title' => $scenario['title'] ?? 'Consider This Scenario',
                    'description' => $scenario['description'] ?? '',
                    'action' => $scenario['action'] ?? 'Review and consider implementing this approach.',
                    'estimated_impact' => $scenario['impact'] ?? null,
                ];
            }
        }

        return array_merge($allRecommendations, $scenarioRecs);
    }

    public function checkDataCompleteness(int $userId, ?int $goalId = null): array
    {
        $missing = [];

        if ($goalId) {
            $goal = Goal::where('id', $goalId)
                ->where(function ($q) use ($userId) {
                    $q->where('user_id', $userId)->orWhere('joint_owner_id', $userId);
                })
                ->first();
            if (! $goal) {
                $missing[] = [
                    'field' => 'goal',
                    'label' => 'Goal',
                    'description' => 'The specified goal could not be found.',
                    'link' => '/goals',
                ];
            } else {
                if (! $goal->target_amount || $goal->target_amount <= 0) {
                    $missing[] = [
                        'field' => 'target_amount',
                        'label' => 'Target amount',
                        'description' => 'Set a target amount for this goal.',
                        'link' => '/goals',
                    ];
                }
                if (! $goal->target_date) {
                    $missing[] = [
                        'field' => 'target_date',
                        'label' => 'Target date',
                        'description' => 'Set a target date to track progress against.',
                        'link' => '/goals',
                    ];
                }
                $hasLinkedAccount = $goal->linked_savings_account_id || $goal->linked_investment_account_id;
                if (! $hasLinkedAccount) {
                    $missing[] = [
                        'field' => 'linked_accounts',
                        'label' => 'Linked accounts',
                        'description' => 'Link a savings or investment account to automatically track contributions.',
                        'link' => '/goals',
                    ];
                }
            }
        }

        $total = 3;
        $present = max(0, $total - count($missing));

        return [
            'percentage' => (int) round(($present / $total) * 100),
            'missing' => $missing,
            'complete' => empty($missing),
        ];
    }

    private function buildExecutiveSummary(User $user, Goal $goal, array $progress, array $affordability): array
    {
        $firstName = $this->getUserFirstName($user);
        $targetAmount = (float) $goal->target_amount;
        $currentAmount = (float) $goal->current_amount;
        $remaining = max(0, $targetAmount - $currentAmount);
        $progressPercent = $progress['progress_percentage'] ?? 0;
        $isOnTrack = $progress['is_on_track'] ?? false;
        $monthsRemaining = $progress['months_remaining'] ?? null;
        $monthlyContribution = (float) ($goal->monthly_contribution ?? 0);

        $lines = [];
        $lines[] = "Dear {$firstName},";
        $lines[] = '';
        $lines[] = sprintf(
            'Thank you for using Fynla. Here is your personalised plan for your goal: "%s".',
            $goal->goal_name
        );
        $lines[] = '';

        // Goal overview
        $lines[] = sprintf(
            'You are working towards saving %s, and so far you have saved %s — that is %s%% of your target.',
            $this->formatCurrency($targetAmount),
            $this->formatCurrency($currentAmount),
            round($progressPercent)
        );

        // Target date
        if ($goal->target_date) {
            $targetDateFormatted = $goal->target_date->format('j F Y');
            $lines[] = sprintf('Your target date to reach this goal is %s.', $targetDateFormatted);
        }

        // Monthly contribution
        if ($monthlyContribution > 0) {
            $lines[] = sprintf('You are currently contributing %s per month towards this goal.', $this->formatCurrency($monthlyContribution));
        }

        // Linked accounts
        $linkedParts = [];
        if ($goal->linked_savings_account_id) {
            $savingsAccount = \App\Models\SavingsAccount::find($goal->linked_savings_account_id);
            if ($savingsAccount) {
                $linkedParts[] = sprintf('your savings account at %s', $savingsAccount->institution ?: 'your bank');
            }
        }
        if ($goal->linked_investment_account_id) {
            $investmentAccount = \App\Models\Investment\InvestmentAccount::find($goal->linked_investment_account_id);
            if ($investmentAccount) {
                $linkedParts[] = sprintf('your %s investment account', $investmentAccount->provider ?: 'linked');
            }
        }
        if (! empty($linkedParts)) {
            $lines[] = 'This goal is linked to '.implode(' and ', $linkedParts).'.';
        }

        // Progress status
        $lines[] = '';
        if ($isOnTrack) {
            $lines[] = 'Based on your current contribution rate and timeline, you are on track to reach this goal by your target date.';
        } elseif ($monthsRemaining !== null && $monthsRemaining > 0) {
            $lines[] = sprintf(
                'At your current pace, it would take approximately %d months to reach your target. The recommendations below suggest ways to accelerate your progress.',
                $monthsRemaining
            );
        } else {
            $lines[] = 'Based on the current figures, you may need to adjust your contributions or timeline. The recommendations below offer strategies to help.';
        }

        // Affordability
        $affordabilityCategory = $affordability['category'] ?? 'unknown';
        if ($affordabilityCategory === 'comfortable' || $affordabilityCategory === 'moderate') {
            $lines[] = 'This goal fits comfortably within your current budget.';
        } elseif ($affordabilityCategory === 'challenging') {
            $lines[] = 'Reaching this goal will require careful budgeting, but it is achievable with the right adjustments.';
        } elseif ($affordabilityCategory === 'stretch') {
            $lines[] = 'This is an ambitious goal that will require significant budget adjustments. Consider whether you can increase your income or reduce other spending.';
        }

        $lines[] = '';
        $lines[] = 'The sections below break down your current progress and provide specific steps you can take to stay on track.';

        return [
            'narrative' => implode("\n", $lines),
            'key_metrics' => [],
            'on_track' => $isOnTrack,
        ];
    }

    private function buildCurrentSituation(Goal $goal, array $progress, array $affordability): array
    {
        return [
            'goal_details' => [
                'name' => $goal->goal_name,
                'type' => $goal->goal_type,
                'priority' => $goal->priority,
                'target_amount' => $this->roundToPenny((float) $goal->target_amount),
                'current_amount' => $this->roundToPenny((float) $goal->current_amount),
                'target_date' => $goal->target_date?->format('Y-m-d'),
                'monthly_contribution' => $this->roundToPenny((float) ($goal->monthly_contribution ?? 0)),
            ],
            'progress' => $progress,
            'affordability' => $affordability,
            'linked_accounts' => [
                'savings' => $goal->linked_savings_account_id,
                'investment' => $goal->linked_investment_account_id,
            ],
        ];
    }

    private function buildWhatIfData(User $user, Goal $goal, array $progress, array $affordability, array $enabledActions): array
    {
        $targetAmount = (float) $goal->target_amount;
        $currentAmount = (float) $goal->current_amount;
        $remaining = max(0, $targetAmount - $currentAmount);
        $monthlyContribution = (float) ($goal->monthly_contribution ?? 0);
        $monthsRemaining = $progress['months_remaining'] ?? 12;

        // Use disposable income via DistributionAccount instead of hardcoded amounts
        $monthlyDisposable = $this->incomeAccessor->getMonthlyForUser($user);
        $budget = new DistributionAccount($monthlyDisposable);

        $additionalMonthly = 0;
        $lumpSum = 0;

        foreach ($enabledActions as $action) {
            $category = strtolower($action['category'] ?? '');
            if (str_contains($category, 'contribution') || str_contains($category, 'increase')) {
                $additionalMonthly += $budget->allocate($action['id'] ?? 'contribution', $monthlyDisposable * 0.2);
            } elseif (str_contains($category, 'lump') || str_contains($category, 'transfer')) {
                $lumpSum += $budget->allocate($action['id'] ?? 'lump_sum', $monthlyDisposable * 0.5);
            } else {
                $additionalMonthly += $budget->allocate($action['id'] ?? 'other', $monthlyDisposable * 0.1);
            }
        }

        $currentMonthsToGoal = $monthlyContribution > 0
            ? ceil($remaining / $monthlyContribution)
            : null;

        $newMonthly = $monthlyContribution + $additionalMonthly;
        $adjustedRemaining = max(0, $remaining - $lumpSum);
        $projectedMonthsToGoal = $newMonthly > 0
            ? ceil($adjustedRemaining / $newMonthly)
            : null;

        $currentCompletionDate = $currentMonthsToGoal
            ? now()->addMonths((int) $currentMonthsToGoal)->format('Y-m-d')
            : null;

        $projectedCompletionDate = $projectedMonthsToGoal
            ? now()->addMonths((int) $projectedMonthsToGoal)->format('Y-m-d')
            : null;

        return [
            'current_scenario' => [
                'months_to_goal' => $currentMonthsToGoal,
                'completion_date' => $currentCompletionDate,
                'monthly_contribution' => $this->roundToPenny($monthlyContribution),
                'total_contributions' => $this->roundToPenny($currentMonthsToGoal ? $monthlyContribution * $currentMonthsToGoal : 0),
            ],
            'projected_scenario' => [
                'months_to_goal' => $projectedMonthsToGoal,
                'completion_date' => $projectedCompletionDate,
                'monthly_contribution' => $this->roundToPenny($newMonthly),
                'lump_sum' => $this->roundToPenny($lumpSum),
                'total_contributions' => $this->roundToPenny($projectedMonthsToGoal ? $newMonthly * $projectedMonthsToGoal + $lumpSum : $lumpSum),
            ],
            'is_approximate' => true,
        ];
    }
}
