<?php

declare(strict_types=1);

use App\Models\Goal;
use App\Models\Investment\InvestmentAccount;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Plans\BasePlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Concrete test implementation to access protected BasePlanService methods.
 */
class TestablePlanService extends BasePlanService
{
    public function generatePlan(int $userId, array $options = []): array
    {
        return [];
    }

    public function getRecommendations(int $userId, ?array $preComputedData = null): array
    {
        return [];
    }

    public function checkDataCompleteness(int $userId): array
    {
        return ['percentage' => 100, 'missing' => [], 'complete' => true];
    }

    public function publicGetGoalsForPlan(int $userId, string $planType): array
    {
        return $this->getGoalsForPlan($userId, $planType);
    }

    public function publicBuildGoalRecommendations(array $linkedGoals): array
    {
        return $this->buildGoalRecommendations($linkedGoals);
    }

    public function publicStructureActions(array $recommendations, string $planType): array
    {
        return $this->structureActions($recommendations, $planType);
    }
}

describe('Goal Integration into Plans', function () {
    beforeEach(function () {
        $this->service = new TestablePlanService;
        $this->user = User::factory()->create();
    });

    it('includes savings-linked goal in investment plan linked_goals [3A.T1]', function () {
        $savingsAccount = SavingsAccount::factory()->create(['user_id' => $this->user->id]);

        Goal::factory()->create([
            'user_id' => $this->user->id,
            'goal_type' => 'emergency_fund',
            'assigned_module' => 'savings',
            'status' => 'active',
            'linked_savings_account_id' => $savingsAccount->id,
        ]);

        $goals = $this->service->publicGetGoalsForPlan($this->user->id, 'investment');

        expect($goals['linked'])->toHaveCount(1)
            ->and($goals['linked'][0]['type'])->toBe('emergency_fund');
    });

    it('includes investment-linked goal in investment plan linked_goals [3A.T2]', function () {
        $investmentAccount = InvestmentAccount::factory()->create(['user_id' => $this->user->id]);

        Goal::factory()->create([
            'user_id' => $this->user->id,
            'goal_type' => 'wealth_accumulation',
            'assigned_module' => 'investment',
            'status' => 'active',
            'linked_investment_account_id' => $investmentAccount->id,
        ]);

        $goals = $this->service->publicGetGoalsForPlan($this->user->id, 'investment');

        expect($goals['linked'])->toHaveCount(1)
            ->and($goals['linked'][0]['type'])->toBe('wealth_accumulation');
    });

    it('includes retirement goal in retirement plan linked_goals [3A.T3]', function () {
        $savingsAccount = SavingsAccount::factory()->create(['user_id' => $this->user->id]);

        Goal::factory()->create([
            'user_id' => $this->user->id,
            'goal_type' => 'retirement',
            'assigned_module' => 'retirement',
            'status' => 'active',
            'linked_savings_account_id' => $savingsAccount->id,
        ]);

        $goals = $this->service->publicGetGoalsForPlan($this->user->id, 'retirement');

        expect($goals['linked'])->toHaveCount(1)
            ->and($goals['linked'][0]['type'])->toBe('retirement');
    });

    it('returns empty goals for estate plan [3A.T4]', function () {
        $investmentAccount = InvestmentAccount::factory()->create(['user_id' => $this->user->id]);

        Goal::factory()->create([
            'user_id' => $this->user->id,
            'goal_type' => 'wealth_accumulation',
            'assigned_module' => 'investment',
            'status' => 'active',
            'linked_investment_account_id' => $investmentAccount->id,
        ]);

        $goals = $this->service->publicGetGoalsForPlan($this->user->id, 'estate');

        expect($goals['linked'])->toBeEmpty()
            ->and($goals['unlinked'])->toBeEmpty();
    });

    it('sorts goal-sourced actions before module-sourced actions [3A.T5]', function () {
        $goalRec = [
            'title' => 'Start contributing to Emergency Fund',
            'description' => 'Set up contributions.',
            'category' => 'Goal',
            'priority' => 'high',
            'source' => 'goal',
            'goal_id' => 1,
        ];
        $moduleRec = [
            'title' => 'Reduce platform fees',
            'description' => 'Switch to a lower-cost platform.',
            'category' => 'Fees',
            'priority' => 'critical',
        ];

        // Module rec has higher priority (critical) but goal rec should appear first
        $actions = $this->service->publicStructureActions([$moduleRec, $goalRec], 'investment');

        expect($actions[0]['source'])->toBe('goal')
            ->and($actions[1]['source'])->toBe('module');
    });

    it('places unlinked goal in unlinked_goals array [3A.T6]', function () {
        Goal::factory()->create([
            'user_id' => $this->user->id,
            'goal_type' => 'emergency_fund',
            'assigned_module' => 'savings',
            'status' => 'active',
            'linked_savings_account_id' => null,
            'linked_investment_account_id' => null,
        ]);

        $goals = $this->service->publicGetGoalsForPlan($this->user->id, 'investment');

        expect($goals['linked'])->toBeEmpty()
            ->and($goals['unlinked'])->toHaveCount(1)
            ->and($goals['unlinked'][0]['type'])->toBe('emergency_fund');
    });

    it('excludes retirement goal from investment plan even when linked to account [3A.T8]', function () {
        $savingsAccount = SavingsAccount::factory()->create(['user_id' => $this->user->id]);

        Goal::factory()->create([
            'user_id' => $this->user->id,
            'goal_type' => 'retirement',
            'assigned_module' => 'retirement',
            'status' => 'active',
            'linked_savings_account_id' => $savingsAccount->id,
        ]);

        $goals = $this->service->publicGetGoalsForPlan($this->user->id, 'investment');

        expect($goals['linked'])->toBeEmpty()
            ->and($goals['unlinked'])->toBeEmpty();
    });

    it('returns empty arrays when user has no goals [3A.T7]', function () {
        $goals = $this->service->publicGetGoalsForPlan($this->user->id, 'investment');

        expect($goals['linked'])->toBeEmpty()
            ->and($goals['unlinked'])->toBeEmpty();

        // Verify structureActions still works normally with no goal recs
        $actions = $this->service->publicStructureActions([
            ['title' => 'Test action', 'description' => 'Test', 'category' => 'General', 'priority' => 'medium'],
        ], 'investment');

        expect($actions)->toHaveCount(1)
            ->and($actions[0]['source'])->toBe('module')
            ->and($actions[0]['id'])->toBe('investment_action_1');
    });
});
