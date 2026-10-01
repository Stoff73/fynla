<?php

declare(strict_types=1);

use App\Agents\InvestmentAgent;
use App\Models\Investment\InvestmentAccount;
use App\Models\Investment\RiskProfile;
use App\Models\ISAAllowanceTracking;
use App\Models\ISAContribution;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\AI\Pointers\FetchContext;
use App\Services\AI\Pointers\Handlers\TaxAllowanceHandler;
use App\Services\Coordination\HouseholdPlanningService;
use App\Services\Investment\Recommendation\UserContextBuilder;
use App\Services\Investment\Tax\TaxOptimizationAnalyzer;
use App\Services\Savings\ISATracker;
use App\Services\Stores\IngestSource;
use App\Services\Stores\InvestmentAccountStore;
use App\Services\Tax\TaxOptimisationService;
use App\Services\Tax\TaxStrategyMath;
use App\Services\Tax\TaxStrategyService;
use App\Services\TaxConfigService;
use Database\Seeders\TaxConfigurationSeeder;
use Laravel\Sanctum\Sanctum;

/*
 * One rule for "ISA allowance used this year" (CSJ 2026-10-01, item 7a; TODO 16).
 *
 * Before: five server engines answered this. TaxStrategyMath summed every
 * investment ISA's "paid in this year" column whatever its tax year and counted
 * the balance of any ISA created this tax year; InvestmentAgent,
 * TaxOptimizationAnalyzer, TaxOptimisationService, UserContextBuilder and
 * HouseholdPlanningService each had their own sum (no ledger, some with no tax
 * year at all). ISATracker (the Savings page on web, /m and iOS) was ledger-aware
 * and tax-year scoped. Now every one delegates to ISATracker.
 *
 * The fixture holds every shape that separated them, with amounts chosen so each
 * old engine lands on a different number from the one rule:
 *   - cash ISA, this year, £3,000 captured                  -> counts 3,000
 *   - cash ISA, 2019/20, £7,500 captured                    -> 0 (another year)
 *   - cash ISA created this tax year, £6,000 balance, none  -> 0 (no balance proxy)
 *   - Lifetime ISA (cash), this year, £1,000                -> counts 1,000
 *   - stocks and shares ISA, this year, column £4,000 but
 *     ledger subscriptions £2,500 + £1,750                  -> counts 4,250 (ledger wins)
 *   - stocks and shares ISA with tax_year 2019/20, £9,000   -> 0 (another year)
 * One rule: 8,250 used, allowance less 8,250 left.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->taxYear = app(TaxConfigService::class)->getTaxYear();
    $this->allowance = (float) app(TaxConfigService::class)->getISAAllowances()['annual_allowance'];

    $this->user = User::factory()->create([
        'annual_employment_income' => 60000,
        'marital_status' => 'single',
    ]);

    SavingsAccount::factory()->for($this->user)->create([
        'is_isa' => true, 'isa_type' => 'cash', 'account_type' => 'cash_isa',
        'current_balance' => 12000, 'isa_subscription_year' => $this->taxYear,
        'isa_subscription_amount' => 3000, 'created_at' => now()->subYears(3),
        'ownership_type' => 'individual', 'joint_owner_id' => null,
    ]);
    SavingsAccount::factory()->for($this->user)->create([
        'is_isa' => true, 'isa_type' => 'cash', 'account_type' => 'cash_isa',
        'current_balance' => 30000, 'isa_subscription_year' => '2019/20',
        'isa_subscription_amount' => 7500, 'created_at' => now()->subYears(6),
        'ownership_type' => 'individual', 'joint_owner_id' => null,
    ]);
    SavingsAccount::factory()->for($this->user)->create([
        'is_isa' => true, 'isa_type' => 'cash', 'account_type' => 'cash_isa',
        'current_balance' => 6000, 'isa_subscription_year' => null,
        'isa_subscription_amount' => null, 'created_at' => now(),
        'ownership_type' => 'individual', 'joint_owner_id' => null,
    ]);
    SavingsAccount::factory()->for($this->user)->create([
        'is_isa' => true, 'isa_type' => 'lisa', 'account_type' => 'cash_isa',
        'current_balance' => 2000, 'isa_subscription_year' => $this->taxYear,
        'isa_subscription_amount' => 1000, 'created_at' => now()->subYears(2),
        'ownership_type' => 'individual', 'joint_owner_id' => null,
    ]);

    $ledgerIsa = InvestmentAccount::factory()->for($this->user)->create([
        'account_type' => 'isa', 'isa_type' => 'stocks_and_shares',
        'current_value' => 40000, 'tax_year' => $this->taxYear,
        'isa_subscription_current_year' => 4000, 'contributions_ytd' => 4000,
        'ownership_type' => 'individual', 'joint_owner_id' => null,
    ]);
    foreach ([2500, 1750] as $i => $amount) {
        ISAContribution::create([
            'user_id' => $this->user->id,
            'account_type' => InvestmentAccount::class,
            'account_id' => $ledgerIsa->id,
            'tax_year' => $this->taxYear,
            'contribution_date' => now()->subDays(10 + $i)->toDateString(),
            'entry_type' => 'subscription',
            'amount' => $amount,
            'source' => 'form',
            'provenance' => 'recorded_ledger',
        ]);
    }

    InvestmentAccount::factory()->for($this->user)->create([
        'account_type' => 'isa', 'isa_type' => 'stocks_and_shares',
        'current_value' => 50000, 'tax_year' => '2019/20',
        'isa_subscription_current_year' => 9000, 'contributions_ytd' => 9000,
        'ownership_type' => 'individual', 'joint_owner_id' => null,
    ]);

    $this->expectedUsed = 8250.0;
    $this->expectedRemaining = $this->allowance - 8250.0;
});

it('ISATracker (the Savings page on web, /m and iOS) answers 8,250 used', function () {
    $status = app(ISATracker::class)->getISAAllowanceStatus($this->user->id, $this->taxYear);

    expect((float) $status['total_used'])->toBe($this->expectedUsed)
        ->and((float) $status['remaining'])->toBe($this->expectedRemaining)
        ->and(app(ISATracker::class)->usedThisTaxYear($this->user))->toBe($this->expectedUsed)
        ->and(app(ISATracker::class)->remainingThisTaxYear($this->user))->toBe($this->expectedRemaining);

    Sanctum::actingAs($this->user);
    $data = $this->getJson('/api/savings/isa-allowance')->assertOk()->json('data');
    expect((float) $data['total_used'])->toBe($this->expectedUsed)
        ->and((float) $data['remaining'])->toBe($this->expectedRemaining);
});

it('the Tax Strategy tile, plan items, how-tos and thresholds read the same figure', function () {
    expect(app(TaxStrategyMath::class)->estimateIsaSubscriptionsThisYear($this->user))->toBe($this->expectedUsed);

    $tile = collect(app(TaxStrategyService::class)->getDashboardPayload($this->user)['user_allowances'])
        ->firstWhere('key', 'isa_allowance');
    expect((float) $tile['used'])->toBe($this->expectedUsed)
        ->and((float) $tile['remaining'])->toBe($this->expectedRemaining);
});

it('web tax efficiency (TaxOptimizationAnalyzer) reads the same figure', function () {
    $position = app(TaxOptimizationAnalyzer::class)->analyzeCompleteTaxPosition($this->user->id)['current_position'];

    expect((float) $position['isa_used'])->toBe($this->expectedUsed)
        ->and((float) $position['isa_remaining'])->toBe($this->expectedRemaining);
});

it('the tax optimisation allowance analysis reads the same figure', function () {
    $isa = app(TaxOptimisationService::class)->analyzeAllowanceUsage($this->user)['isa'];

    expect((float) $isa['used'])->toBe($this->expectedUsed)
        ->and((float) $isa['remaining'])->toBe($this->expectedRemaining);
});

it('the investment recommendation context reads the same figure', function () {
    $allowances = app(UserContextBuilder::class)->build($this->user)['allowances'];

    expect((float) $allowances['isa_used'])->toBe($this->expectedUsed)
        ->and((float) $allowances['isa_remaining'])->toBe($this->expectedRemaining);
});

it('Fyn\'s investment tool (InvestmentAgent tax wrappers) reads the same figure', function () {
    RiskProfile::updateOrCreate(['user_id' => $this->user->id], ['risk_level' => 'medium']);

    $result = app(InvestmentAgent::class)->analyze($this->user->id);
    $wrappers = $result['data']['tax_wrappers'] ?? $result['tax_wrappers'] ?? null;
    fwrite(STDERR, "DBG ".json_encode(array_keys($result)).json_encode($result["readiness_checks"] ?? $result["data"]["readiness_checks"] ?? $result["message"] ?? null)."\n");

    expect($wrappers)->not->toBeNull('InvestmentAgent did not reach its analysis: '.json_encode($result['readiness_checks'] ?? $result['data']['readiness_checks'] ?? null));
    expect((float) $wrappers['isa_used_this_year'])->toBe($this->expectedUsed)
        ->and((float) $wrappers['isa_remaining'])->toBe($this->expectedRemaining);
});

it('the household planner reads the same figure', function () {
    $method = new ReflectionMethod(HouseholdPlanningService::class, 'calculateISAUsage');

    expect((float) $method->invoke(app(HouseholdPlanningService::class), $this->user))->toBe($this->expectedUsed);
});

it('Fyn\'s allowance pointer quotes the same totals and an account breakdown that adds up to them', function () {
    $value = app(TaxAllowanceHandler::class)->fetch(new FetchContext($this->user, 'how much ISA allowance have I used'))->value;

    expect($value)->toContain('£8,250 used')
        ->and($value)->toContain('£'.number_format($this->expectedRemaining).' remaining')
        ->and($value)->toContain('£4,250 subscribed')
        ->and($value)->not->toContain('£9,000 subscribed')
        ->and($value)->not->toContain('£7,500 subscribed');
});

it('the remaining figure uses the live allowance, never the one frozen on the tracking row', function () {
    ISAAllowanceTracking::create([
        'user_id' => $this->user->id, 'tax_year' => $this->taxYear,
        'cash_isa_used' => 0, 'stocks_shares_isa_used' => 0, 'lisa_used' => 0,
        'total_used' => 0, 'total_allowance' => 15000,
    ]);

    $status = app(ISATracker::class)->getISAAllowanceStatus($this->user->id, $this->taxYear);

    expect((float) $status['total_allowance'])->toBe($this->allowance)
        ->and((float) $status['remaining'])->toBe($this->expectedRemaining);
});

it('a changed "paid in this tax year" figure moves the account into this tax year', function () {
    $stale = InvestmentAccount::where('user_id', $this->user->id)->where('tax_year', '2019/20')->firstOrFail();

    app(InvestmentAccountStore::class)->update($stale->id, ['isa_subscription_current_year' => 6000], $this->user, IngestSource::FORM);

    expect($stale->fresh()->tax_year)->toBe($this->taxYear)
        ->and(app(ISATracker::class)->usedThisTaxYear($this->user))->toBe($this->expectedUsed + 6000);
});
