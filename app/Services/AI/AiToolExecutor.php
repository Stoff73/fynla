<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Agents\CoordinatingAgent;
use App\Agents\EstateAgent;
use App\Agents\GoalsAgent;
use App\Agents\InvestmentAgent;
use App\Agents\ProtectionAgent;
use App\Agents\RetirementAgent;
use App\Agents\SavingsAgent;
use App\Models\Goal;
use App\Models\LifeEvent;
use App\Models\User;
use App\Services\TaxConfigService;
use Illuminate\Support\Facades\Log;

class AiToolExecutor
{
    public function __construct(
        private readonly CoordinatingAgent $coordinatingAgent,
        private readonly ProtectionAgent $protectionAgent,
        private readonly SavingsAgent $savingsAgent,
        private readonly InvestmentAgent $investmentAgent,
        private readonly RetirementAgent $retirementAgent,
        private readonly EstateAgent $estateAgent,
        private readonly GoalsAgent $goalsAgent,
        private readonly TaxConfigService $taxConfig,
    ) {}

    /**
     * Execute a tool call and return the result.
     */
    public function execute(string $toolName, array $input, User $user): array
    {
        $isPreviewUser = $user->is_preview_user;

        try {
            return match ($toolName) {
                'navigate_to_page' => $this->navigateToPage($input),
                'get_module_analysis' => $this->getModuleAnalysis($input, $user),
                'run_what_if_scenario' => $this->runWhatIfScenario($input, $user),
                'get_recommendations' => $this->getRecommendations($user),
                'get_tax_information' => $this->getTaxInformation($input),
                'create_goal' => $this->createGoal($input, $user, $isPreviewUser),
                'create_life_event' => $this->createLifeEvent($input, $user, $isPreviewUser),
                default => ['error' => "Unknown tool: {$toolName}"],
            };
        } catch (\Exception $e) {
            Log::error('[AiToolExecutor] Tool execution failed', [
                'tool' => $toolName,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return ['error' => 'Tool execution failed. Please try again.'];
        }
    }

    private function navigateToPage(array $input): array
    {
        return [
            'action' => 'navigate',
            'route_path' => $input['route_path'],
            'description' => $input['description'] ?? '',
        ];
    }

    private function getModuleAnalysis(array $input, User $user): array
    {
        $module = $input['module'];

        $analysis = match ($module) {
            'protection' => $this->protectionAgent->analyze($user->id),
            'savings' => $this->savingsAgent->analyze($user->id),
            'investment' => $this->investmentAgent->analyze($user->id),
            'retirement' => $this->retirementAgent->analyze($user->id),
            'estate' => $this->estateAgent->analyze($user->id),
            'goals' => $this->goalsAgent->analyze($user->id),
            'holistic' => $this->coordinatingAgent->orchestrateAnalysis($user->id),
            default => ['error' => "Unknown module: {$module}"],
        };

        return $this->summariseAnalysis($module, $analysis);
    }

    private function runWhatIfScenario(array $input, User $user): array
    {
        $module = $input['module'];
        $parameters = $input['parameters'] ?? [];

        $agent = match ($module) {
            'protection' => $this->protectionAgent,
            'savings' => $this->savingsAgent,
            'investment' => $this->investmentAgent,
            'retirement' => $this->retirementAgent,
            default => null,
        };

        if (! $agent) {
            return ['error' => "Scenarios not available for module: {$module}"];
        }

        return $agent->buildScenarios($user->id, $parameters);
    }

    private function getRecommendations(User $user): array
    {
        $analysis = $this->coordinatingAgent->orchestrateAnalysis($user->id);

        return [
            'recommendations' => $analysis['ranked_recommendations'] ?? [],
            'total' => count($analysis['ranked_recommendations'] ?? []),
            'surplus' => $analysis['available_surplus'] ?? 0,
        ];
    }

    private function getTaxInformation(array $input): array
    {
        $topic = $input['topic'];

        return match ($topic) {
            'income_tax' => $this->taxConfig->getIncomeTax(),
            'capital_gains' => $this->taxConfig->getCapitalGainsTax(),
            'inheritance_tax' => $this->taxConfig->getInheritanceTax(),
            'isa_allowances' => $this->taxConfig->getISAAllowances(),
            'pension_allowances' => $this->taxConfig->getPensionAllowances(),
            default => ['error' => "Unknown tax topic: {$topic}"],
        };
    }

    private function createGoal(array $input, User $user, bool $isPreview): array
    {
        if ($isPreview) {
            return [
                'blocked' => true,
                'reason' => 'You are in preview mode. Goal creation is not available — please create a real account to save goals.',
            ];
        }

        $goal = Goal::create([
            'user_id' => $user->id,
            'goal_name' => $input['name'],
            'goal_type' => $input['goal_type'],
            'target_amount' => $input['target_amount'],
            'target_date' => $input['target_date'],
            'priority' => $input['priority'],
            'status' => 'active',
            'current_amount' => 0,
            'start_date' => now()->toDateString(),
        ]);

        return [
            'created' => true,
            'entity_type' => 'goal',
            'entity_id' => $goal->id,
            'name' => $goal->goal_name,
            'message' => "Goal \"{$goal->goal_name}\" created successfully.",
        ];
    }

    private function createLifeEvent(array $input, User $user, bool $isPreview): array
    {
        if ($isPreview) {
            return [
                'blocked' => true,
                'reason' => 'You are in preview mode. Life event creation is not available — please create a real account to save life events.',
            ];
        }

        $impactType = $this->resolveImpactType($input['event_type']);

        $lifeEvent = LifeEvent::create([
            'user_id' => $user->id,
            'event_name' => $input['description'],
            'event_type' => $input['event_type'],
            'description' => $input['description'],
            'amount' => $input['estimated_cost'] ?? 0,
            'impact_type' => $impactType,
            'expected_date' => $input['event_date'],
            'certainty' => 'likely',
            'status' => 'planned',
        ]);

        return [
            'created' => true,
            'entity_type' => 'life_event',
            'entity_id' => $lifeEvent->id,
            'name' => $lifeEvent->event_name,
            'message' => "Life event \"{$lifeEvent->event_name}\" created successfully.",
        ];
    }

    /**
     * Resolve impact type based on event type.
     */
    private function resolveImpactType(string $eventType): string
    {
        if (in_array($eventType, LifeEvent::INCOME_EVENT_TYPES)) {
            return 'income';
        }

        if (in_array($eventType, LifeEvent::EXPENSE_EVENT_TYPES)) {
            return 'expense';
        }

        return 'expense';
    }

    /**
     * Summarise analysis data to fit within token budget.
     */
    private function summariseAnalysis(string $module, array $analysis): array
    {
        if (isset($analysis['error'])) {
            return $analysis;
        }

        // Extract key metrics based on module to keep response concise
        $summary = ['module' => $module];

        if (isset($analysis['data'])) {
            $data = $analysis['data'];
            $summary['metrics'] = $this->extractKeyMetrics($data);
            $summary['recommendations'] = array_slice($data['recommendations'] ?? [], 0, 5);
        } elseif (isset($analysis['summary'])) {
            $summary['metrics'] = $analysis['summary'];
            $summary['recommendations'] = array_slice($analysis['ranked_recommendations'] ?? [], 0, 5);
        } else {
            $summary['metrics'] = $analysis;
        }

        return $summary;
    }

    /**
     * Extract the most important metrics from analysis data.
     */
    private function extractKeyMetrics(array $data): array
    {
        $metrics = [];

        // Common fields across modules
        $keyFields = [
            'total_value', 'total_cover', 'coverage_gaps', 'net_worth',
            'monthly_surplus', 'emergency_fund_months', 'pension_projection',
            'iht_liability', 'total_savings', 'total_investments',
            'retirement_income', 'target_income', 'shortfall',
            'risk_score', 'asset_allocation', 'progress_percentage',
        ];

        foreach ($keyFields as $field) {
            if (isset($data[$field])) {
                $metrics[$field] = $data[$field];
            }
        }

        return $metrics;
    }
}
