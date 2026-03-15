<?php

declare(strict_types=1);

namespace App\Services\Plans;

use App\Agents\InvestmentAgent;
use App\Agents\SavingsAgent;
use App\Models\Investment\InvestmentAccount;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Savings\SavingsActionDefinitionService;
use App\Services\TaxConfigService;

class SavingsPlanService extends BasePlanService
{
    public function __construct(
        private readonly SavingsAgent $savingsAgent,
        private readonly InvestmentAgent $investmentAgent,
        private readonly SavingsActionDefinitionService $actionDefinitionService,
        private readonly TaxConfigService $taxConfig
    ) {}

    public function generatePlan(int $userId, array $options = []): array
    {
        $user = User::findOrFail($userId);
        $completeness = $this->checkDataCompleteness($userId);

        // 1. Get analysis from both agents
        $savingsAnalysis = $this->savingsAgent->analyze($userId);
        $investmentAnalysis = $this->investmentAgent->analyze($userId);

        // 2. Check readiness gate
        if (($savingsAnalysis['can_proceed'] ?? true) === false) {
            return [
                'success' => false,
                'can_proceed' => false,
                'readiness_checks' => $savingsAnalysis['readiness_checks'] ?? null,
                'recommendations' => [],
            ];
        }

        $savingsAccounts = SavingsAccount::forUserOrJoint($userId)->get();
        $investmentAccounts = InvestmentAccount::forUserOrJoint($userId)->get();

        // 3. Get recommendations from DB-driven service
        $recommendations = $this->getRecommendations($userId, [
            'savings_analysis' => $savingsAnalysis,
            'investment_analysis' => $investmentAnalysis,
            'savings_accounts' => $savingsAccounts,
            'investment_accounts' => $investmentAccounts,
        ]);

        // 4. Get goal recommendations
        $goals = $this->getGoalsForPlan($userId, 'savings');
        $goalRecommendations = $this->actionDefinitionService->evaluateGoalActions(
            collect($goals['linked'] ?? [])
        );

        // 5. Merge: goals first, then agent recs
        $allRecs = array_merge($goalRecommendations, $recommendations);

        // 6. Structure into actions
        ['actions' => $actions, 'enabledActions' => $enabledActions] = $this->prepareActions($allRecs, 'savings', $options);

        return [
            'success' => true,
            'can_proceed' => true,
            'metadata' => $this->buildPlanMetadata($user, 'savings', $completeness),
            'completeness_warning' => $this->buildCompletenessWarning($completeness),
            'plan_type' => 'savings',
            'actions' => $actions,
            'enabledActions' => $enabledActions,
            'goals' => $goals,
            'conclusion' => $this->generateDynamicConclusion([], $enabledActions, 'savings'),
            'summary' => [
                'total_recommendations' => count($allRecs),
                'high_priority' => collect($allRecs)->whereIn('impact', ['High', 'Critical'])->count()
                    + collect($allRecs)->whereIn('priority', ['high', 'critical'])->count(),
            ],
        ];
    }

    /**
     * Get recommendations by delegating to the DB-driven SavingsActionDefinitionService.
     *
     * @param  int  $userId  User ID
     * @param  array|null  $preComputedData  Optional pre-computed data with keys:
     *                                       savings_analysis, investment_analysis, savings_accounts, investment_accounts
     */
    public function getRecommendations(int $userId, ?array $preComputedData = null): array
    {
        $savingsAnalysis = $preComputedData['savings_analysis'] ?? $this->savingsAgent->analyze($userId);
        $investmentAnalysis = $preComputedData['investment_analysis'] ?? $this->investmentAgent->analyze($userId);

        $savingsAccounts = $preComputedData['savings_accounts'] ?? SavingsAccount::forUserOrJoint($userId)->get();
        $investmentAccounts = $preComputedData['investment_accounts'] ?? InvestmentAccount::forUserOrJoint($userId)->get();

        $result = $this->actionDefinitionService->evaluateAgentActions(
            $savingsAnalysis,
            $investmentAnalysis,
            $savingsAccounts,
            $investmentAccounts,
            $userId
        );

        return $result['recommendations'] ?? [];
    }

    public function checkDataCompleteness(int $userId): array
    {
        $missing = [];
        $user = User::find($userId);

        $checks = [
            'savings_accounts' => SavingsAccount::where('user_id', $userId)->exists(),
            'income' => $user && ($user->annual_employment_income || $user->annual_self_employment_income),
            'expenditure' => $user && ($user->monthly_expenditure > 0 || $user->annual_expenditure > 0),
        ];

        if (! $checks['savings_accounts']) {
            $missing[] = [
                'field' => 'savings_accounts',
                'label' => 'Savings accounts',
                'description' => 'Add your savings accounts to receive personalised savings analysis.',
                'link' => '/savings',
            ];
        }

        if (! $checks['income']) {
            $missing[] = [
                'field' => 'income',
                'label' => 'Income details',
                'description' => 'Add your income for accurate emergency fund and tax calculations.',
                'link' => '/profile',
            ];
        }

        if (! $checks['expenditure']) {
            $missing[] = [
                'field' => 'expenditure',
                'label' => 'Monthly expenditure',
                'description' => 'Add your monthly expenditure to calculate emergency fund targets.',
                'link' => '/profile',
            ];
        }

        // Check date of birth
        $hasDob = $user && $user->date_of_birth;
        if (! $hasDob) {
            $missing[] = [
                'field' => 'date_of_birth',
                'label' => 'Date of birth',
                'description' => 'Your date of birth is required for personalised savings guidance.',
                'link' => '/profile',
            ];
        }

        $total = count($checks) + 1; // +1 for date of birth
        $present = $total - count($missing);

        return [
            'percentage' => $total > 0 ? round(($present / $total) * 100) : 0,
            'missing' => $missing,
            'complete' => empty($missing),
        ];
    }
}
