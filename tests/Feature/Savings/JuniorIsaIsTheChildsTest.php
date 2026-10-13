<?php

declare(strict_types=1);

use App\Agents\SavingsAgent;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\AI\AdvicePromptBuilder;
use App\Services\Coordination\HouseholdFinancialContext;
use App\Services\Estate\EstateAssetAggregatorService;
use App\Services\NetWorth\NetWorthService;
use App\Services\Shared\CrossModuleAssetAggregator;
use App\Services\Tax\IncomeDefinitionsService;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * T65 (release ac prod walk, 2026-10-13). A child's Junior ISA, saved on the
 * parent's account, was counted as the parent's money: Bea's Inheritance Tax
 * action read £2,800, which is (£320,000 + £8,900 + £3,100 − £325,000) × 40%
 * with Arjun's £3,100 Junior ISA in it; without it the figure is £1,560. It also
 * sat in the parent's cash total and under "Cash ISAs". GOV.UK, Junior ISAs:
 * "the money belongs to the child". CSJ 2026-10-13: out of every parent total,
 * listed under its own heading naming the child.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
    $this->user = User::factory()->create(['is_preview_user' => false, 'date_of_birth' => now()->subYears(40)]);
    SavingsAccount::factory()->create([
        'user_id' => $this->user->id,
        'institution' => 'Chase',
        'account_name' => 'Easy access',
        'account_type' => 'savings_account',
        'is_isa' => false,
        'isa_type' => null,
        'current_balance' => 8000,
        'interest_rate' => 4.5,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'joint_owner_id' => null,
    ]);
    SavingsAccount::factory()->create([
        'user_id' => $this->user->id,
        'institution' => 'Vanguard',
        'account_name' => 'Junior ISA',
        'account_type' => 'junior_isa',
        'is_isa' => true,
        'isa_type' => 'junior',
        'current_balance' => 3100,
        'interest_rate' => 4.0,
        'beneficiary_name' => 'Arjun',
        'beneficiary_dob' => '2017-07-09',
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'joint_owner_id' => null,
    ]);
});

it('leaves the Junior ISA out of the parent\'s cash, net worth and estate', function () {
    $cash = app(CrossModuleAssetAggregator::class)->calculateCashTotal($this->user->id);
    $netWorth = app(NetWorthService::class)->calculateNetWorth($this->user);
    $estateCash = app(EstateAssetAggregatorService::class)->gatherUserAssets($this->user)
        ->where('asset_type', 'cash')->sum('current_value');
    $detailed = app(NetWorthService::class)->getAssetsSummaryWithDetails($this->user);

    expect($cash)->toBe(8000.0)
        ->and((float) $netWorth['total_assets'])->toBe(8000.0)
        ->and((float) $estateCash)->toBe(8000.0)
        ->and((float) $detailed['cash']['total_value'])->toBe(8000.0)
        ->and($detailed['cash']['count'])->toBe(1);
});

it('gives the Junior ISA its own group on the savings page, outside the holder\'s ISAs', function () {
    $position = app(SavingsAgent::class)->positionFor($this->user);

    expect((float) $position['total_cash'])->toBe(8000.0)
        ->and((float) $position['group_totals']['isas'])->toBe(0.0)
        ->and((float) $position['group_totals']['junior_isas'])->toBe(3100.0);
});

it('never counts the Junior ISA as the parent\'s ISA or interest', function () {
    expect(app(HouseholdFinancialContext::class)->hasIsaAccount($this->user))->toBeFalse()
        ->and(app(IncomeDefinitionsService::class)->estimatedAnnualInterest($this->user))->toBe(360.0);
});

it('tells Fyn the Junior ISA is the child\'s money', function () {
    $profile = app(AdvicePromptBuilder::class)->buildExistingRecordsSummary($this->user->fresh());

    expect($profile)->toContain('Junior ISA for <user_provided>Arjun</user_provided>')
        ->and($profile)->toContain('the child\'s money, not counted in your totals');
});
