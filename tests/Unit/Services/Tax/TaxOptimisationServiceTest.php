<?php

declare(strict_types=1);

use App\Models\Investment\Holding;
use App\Models\Investment\InvestmentAccount;
use App\Models\User;
use App\Services\Retirement\AnnualAllowanceChecker;
use App\Services\Tax\TaxOptimisationService;
use App\Services\TaxConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
    $this->taxConfig = app(TaxConfigService::class);

    $this->allowanceChecker = Mockery::mock(AnnualAllowanceChecker::class);
    $this->allowanceChecker->shouldReceive('checkAnnualAllowance')
        ->andReturn([
            'tax_year' => '2025/26',
            'standard_allowance' => 60000,
            'available_allowance' => 60000,
            'total_contributions' => 12000,
            'remaining_allowance' => 48000,
            'carry_forward_available' => 15000,
            'is_tapered' => false,
            'has_excess' => false,
        ]);

    $this->service = new TaxOptimisationService(
        $this->taxConfig,
        $this->allowanceChecker
    );
});

afterEach(function () {
    Mockery::close();
});

describe('analyzeAllowanceUsage', function () {
    it('calculates ISA allowance usage correctly', function () {
        $user = User::factory()->create([
            'annual_employment_income' => 55000,
        ]);

        // Create an ISA with some subscription
        InvestmentAccount::factory()->create([
            'user_id' => $user->id,
            'account_type' => 'isa',
            'isa_subscription_current_year' => 8000,
            'current_value' => 50000,
        ]);

        $result = $this->service->analyzeAllowanceUsage($user);

        expect($result)->toHaveKeys(['isa', 'pension_annual_allowance', 'capital_gains', 'personal_savings_allowance'])
            ->and($result['isa']['used'])->toBe(8000.0)
            ->and($result['isa']['remaining'])->toBe(12000.0)
            ->and($result['isa']['allowance'])->toBe(20000.0);
    });

    it('includes pension Annual Allowance data', function () {
        $user = User::factory()->create([
            'annual_employment_income' => 55000,
        ]);

        $result = $this->service->analyzeAllowanceUsage($user);

        expect($result['pension_annual_allowance']['remaining_allowance'])->toEqual(48000)
            ->and($result['pension_annual_allowance']['carry_forward_available'])->toEqual(15000)
            ->and($result['pension_annual_allowance']['is_tapered'])->toBeFalse();
    });

    it('calculates CGT position from GIA holdings', function () {
        $user = User::factory()->create([
            'annual_employment_income' => 55000,
        ]);

        $gia = InvestmentAccount::factory()->create([
            'user_id' => $user->id,
            'account_type' => 'gia',
            'current_value' => 100000,
        ]);

        Holding::factory()->create([
            'holdable_id' => $gia->id,
            'holdable_type' => InvestmentAccount::class,
            'cost_basis' => 40000,
            'current_value' => 50000,
        ]);

        Holding::factory()->create([
            'holdable_id' => $gia->id,
            'holdable_type' => InvestmentAccount::class,
            'cost_basis' => 30000,
            'current_value' => 25000,
        ]);

        $result = $this->service->analyzeAllowanceUsage($user);

        expect($result['capital_gains']['unrealised_gains'])->toBe(10000.0)
            ->and($result['capital_gains']['unrealised_losses'])->toBe(5000.0)
            ->and($result['capital_gains']['net_gains'])->toBe(5000.0);
    });
});

describe('generateStrategies', function () {
    it('generates ISA strategy when allowance is unused', function () {
        $user = User::factory()->create([
            'annual_employment_income' => 55000,
        ]);

        // No ISA subscriptions = full allowance remaining
        $result = $this->service->generateStrategies($user);

        $isaStrategy = collect($result['strategies'])->firstWhere('type', 'isa_allowance');

        expect($isaStrategy)->not->toBeNull()
            ->and($isaStrategy['priority'])->toBe('high')
            ->and($isaStrategy['estimated_annual_saving'])->toBeGreaterThan(0);
    });

    it('generates pension strategy for higher-rate taxpayer', function () {
        $user = User::factory()->create([
            'annual_employment_income' => 80000,
        ]);

        $result = $this->service->generateStrategies($user);

        $pensionStrategy = collect($result['strategies'])->firstWhere('type', 'pension_annual_allowance');

        expect($pensionStrategy)->not->toBeNull()
            ->and($pensionStrategy['priority'])->toBe('high')
            ->and($pensionStrategy['details']['tax_relief_rate'])->toBe(0.40);
    });

    it('generates CGT strategy when losses can be harvested', function () {
        $user = User::factory()->create([
            'annual_employment_income' => 55000,
        ]);

        $gia = InvestmentAccount::factory()->create([
            'user_id' => $user->id,
            'account_type' => 'gia',
            'current_value' => 200000,
        ]);

        // Gains exceeding annual exempt
        Holding::factory()->create([
            'holdable_id' => $gia->id,
            'holdable_type' => InvestmentAccount::class,
            'cost_basis' => 80000,
            'current_value' => 100000,
        ]);

        // Losses available
        Holding::factory()->create([
            'holdable_id' => $gia->id,
            'holdable_type' => InvestmentAccount::class,
            'cost_basis' => 50000,
            'current_value' => 40000,
        ]);

        $result = $this->service->generateStrategies($user);

        $cgtStrategy = collect($result['strategies'])->firstWhere('type', 'cgt_loss_harvesting');

        expect($cgtStrategy)->not->toBeNull()
            ->and($cgtStrategy['priority'])->toBe('medium');
    });

    it('generates spousal optimisation for married users with different tax bands', function () {
        $spouse = User::factory()->create([
            'annual_employment_income' => 20000,
            'marital_status' => 'married',
        ]);

        $user = User::factory()->create([
            'annual_employment_income' => 80000,
            'marital_status' => 'married',
            'spouse_id' => $spouse->id,
        ]);

        $spouse->update(['spouse_id' => $user->id]);

        $result = $this->service->generateStrategies($user);

        $spousalStrategy = collect($result['strategies'])->firstWhere('type', 'spousal_optimisation');

        expect($spousalStrategy)->not->toBeNull()
            ->and($spousalStrategy['details']['user_tax_band'])->toBe('higher')
            ->and($spousalStrategy['details']['spouse_tax_band'])->toBe('basic');
    });

    it('does not generate spousal strategy for single users', function () {
        $user = User::factory()->create([
            'annual_employment_income' => 80000,
            'marital_status' => 'single',
            'spouse_id' => null,
        ]);

        $result = $this->service->generateStrategies($user);

        $spousalStrategy = collect($result['strategies'])->firstWhere('type', 'spousal_optimisation');

        expect($spousalStrategy)->toBeNull();
    });

    it('sorts strategies by estimated saving descending', function () {
        $user = User::factory()->create([
            'annual_employment_income' => 80000,
        ]);

        $result = $this->service->generateStrategies($user);

        if (count($result['strategies']) > 1) {
            $savings = array_column($result['strategies'], 'estimated_annual_saving');
            $sortedSavings = $savings;
            rsort($sortedSavings);

            expect($savings)->toBe($sortedSavings);
        }

        expect($result['strategies'])->toBeArray();
    });

    it('calculates total estimated saving correctly', function () {
        $user = User::factory()->create([
            'annual_employment_income' => 55000,
        ]);

        $result = $this->service->generateStrategies($user);

        $sumOfIndividual = array_sum(array_column($result['strategies'], 'estimated_annual_saving'));

        expect($result['total_estimated_saving'])->toBe(round($sumOfIndividual, 2));
    });
});
