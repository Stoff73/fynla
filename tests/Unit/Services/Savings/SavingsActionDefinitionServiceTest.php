<?php

declare(strict_types=1);

use App\Models\SavingsAccount;
use App\Models\SavingsActionDefinition;
use App\Models\User;
use App\Services\Savings\SavingsActionDefinitionService;
use Database\Seeders\SavingsActionDefinitionSeeder;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(SavingsActionDefinitionSeeder::class);
    $this->service = app(SavingsActionDefinitionService::class);
    $this->user = User::factory()->create([
        'employment_status' => 'employed',
        'annual_employment_income' => 45000,
        'monthly_expenditure' => 2000,
        'date_of_birth' => '1985-01-01',
        'marital_status' => 'single',
    ]);
});

function savingsAnalysis(?float $runway, float $expenditure = 2000, array $extra = []): array
{
    return array_merge([
        'summary' => ['monthly_expenditure' => $expenditure, 'total_savings' => ($runway ?? 0) * $expenditure],
        'emergency_fund' => ['runway_months' => $runway, 'current_balance' => ($runway ?? 0) * $expenditure],
        'rate_comparisons' => [],
        'isa_allowance' => ['remaining' => 0],
    ], $extra);
}

function keysOf(array $result): array
{
    return array_values(array_unique(array_column($result['recommendations'], 'definition_key')));
}

describe('emergency fund family', function () {
    it('fires emergency_fund_critical below 1 month', function () {
        $result = $this->service->evaluateAgentActions(savingsAnalysis(0.5), [], collect(), collect(), $this->user->id);
        expect(keysOf($result))->toContain('emergency_fund_critical')->not->toContain('emergency_fund_low');
    });

    it('fires emergency_fund_low between 1 and 3 months', function () {
        $result = $this->service->evaluateAgentActions(savingsAnalysis(2.0), [], collect(), collect(), $this->user->id);
        expect(keysOf($result))->toContain('emergency_fund_low')->not->toContain('emergency_fund_building');
    });

    it('fires emergency_fund_building between 3 and 6 months', function () {
        $result = $this->service->evaluateAgentActions(savingsAnalysis(4.0), [], collect(), collect(), $this->user->id);
        expect(keysOf($result))->toContain('emergency_fund_building');
    });

    it('fires emergency_fund_excess above the seeded 6-month threshold, not the old 12', function () {
        $result = $this->service->evaluateAgentActions(savingsAnalysis(8.0), [], collect(), collect(), $this->user->id);
        expect(keysOf($result))->toContain('emergency_fund_excess');
    });

    it('does not state a runway it cannot measure', function () {
        $result = $this->service->evaluateAgentActions(savingsAnalysis(null, 0.0), [], collect(), collect(), $this->user->id);
        expect(keysOf($result))->not->toContain('emergency_fund_critical');
    });
});

describe('fixed-rate maturity family reads days_threshold and de-duplicates', function () {
    it('fires only the urgent row for an account maturing in 10 days', function () {
        $account = SavingsAccount::factory()->create([
            'user_id' => $this->user->id, 'access_type' => 'fixed', 'maturity_date' => now()->addDays(10),
            'current_balance' => 10000, 'interest_rate' => 4.5,
        ]);
        $result = $this->service->evaluateAgentActions(savingsAnalysis(6.0), [], collect([$account]), collect(), $this->user->id);
        expect(keysOf($result))->toContain('fixed_maturity_urgent')->not->toContain('fixed_maturity_warning');
    });

    it('fires only the warning row for an account maturing in 60 days', function () {
        $account = SavingsAccount::factory()->create([
            'user_id' => $this->user->id, 'access_type' => 'fixed', 'maturity_date' => now()->addDays(60),
            'current_balance' => 10000, 'interest_rate' => 4.5,
        ]);
        $result = $this->service->evaluateAgentActions(savingsAnalysis(6.0), [], collect([$account]), collect(), $this->user->id);
        expect(keysOf($result))->toContain('fixed_maturity_warning')->not->toContain('fixed_maturity_urgent');
    });
});

describe('rate family uses the comparator gap in percentage points', function () {
    it('fires rate_poor, not rate_below_market, when the gap is 1.5 points or more', function () {
        $account = SavingsAccount::factory()->create([
            'user_id' => $this->user->id, 'access_type' => 'immediate', 'is_isa' => false,
            'current_balance' => 20000, 'interest_rate' => 2.5,
        ]);
        $comparison = ['account_id' => $account->id, 'comparison' => [
            'account_rate' => 0.025, 'market_rate' => 0.045, 'account_rate_percent' => 2.5, 'market_rate_percent' => 4.5,
            'difference' => -0.02, 'difference_percent' => -2.0, 'is_competitive' => false, 'category' => 'Poor',
        ], 'potential_gain' => 400.0];
        $result = $this->service->evaluateAgentActions(
            savingsAnalysis(6.0, 2000, ['rate_comparisons' => [$comparison]]), [], collect([$account]), collect(), $this->user->id
        );
        expect(keysOf($result))->toContain('rate_poor')->not->toContain('rate_below_market');
    });

    it('fires rate_below_market alone when the gap is between 0.5 and 1.5 points', function () {
        $account = SavingsAccount::factory()->create([
            'user_id' => $this->user->id, 'access_type' => 'immediate', 'is_isa' => false,
            'current_balance' => 20000, 'interest_rate' => 3.6,
        ]);
        $comparison = ['account_id' => $account->id, 'comparison' => [
            'account_rate' => 0.036, 'market_rate' => 0.045, 'account_rate_percent' => 3.6, 'market_rate_percent' => 4.5,
            'difference' => -0.009, 'difference_percent' => -0.9, 'is_competitive' => false, 'category' => 'Fair',
        ], 'potential_gain' => 180.0];
        $result = $this->service->evaluateAgentActions(
            savingsAnalysis(6.0, 2000, ['rate_comparisons' => [$comparison]]), [], collect([$account]), collect(), $this->user->id
        );
        expect(keysOf($result))->toContain('rate_below_market')->not->toContain('rate_poor');
    });
});

describe('seeded thresholds are the ones the evaluators read', function () {
    it('pins psa_approaching at 80 per cent usage', function () {
        $definition = SavingsActionDefinition::where('key', 'psa_approaching')->firstOrFail();
        expect($definition->trigger_config['threshold'])->toBe(80);
    });

    it('pins the maturity rows on days_threshold 90 and 30', function () {
        expect(SavingsActionDefinition::where('key', 'fixed_maturity_warning')->firstOrFail()->trigger_config['days_threshold'])->toBe(90)
            ->and(SavingsActionDefinition::where('key', 'fixed_maturity_urgent')->firstOrFail()->trigger_config['days_threshold'])->toBe(30);
    });
});

it('every enabled agent row is reachable by the dispatcher', function () {
    $source = file_get_contents(app_path('Services/Savings/SavingsActionDefinitionService.php'));
    preg_match_all("/^\\s*'([a-z0-9_]+)'\\s*=>\\s*\\\$this->evaluate/m", $source, $m);
    $arms = $m[1];
    $conditions = SavingsActionDefinition::where('is_enabled', true)->where('source', 'agent')->get()
        ->map(fn ($d) => $d->trigger_config['condition'] ?? null)->filter()->unique()->values()->all();
    expect(array_values(array_diff($conditions, $arms)))->toBe([]);
});

describe('emergency fund decision trace', function () {
    it('explains the month table exactly as EmergencyFundCalculator defines it, retired at 3', function () {
        $result = $this->service->evaluateAgentActions(savingsAnalysis(2.0), [], collect(), collect(), $this->user->id);
        $rec = collect($result['recommendations'])->firstWhere('definition_key', 'emergency_fund_low');
        $step = collect($rec['decision_trace'])->firstWhere('data_field', 'employment_status');

        expect($step['threshold'])->toContain('retired = 3 months')->not->toContain('retired = 6')
            ->and($step['explanation'])->not->toContain('retired need 6');
    });
});
