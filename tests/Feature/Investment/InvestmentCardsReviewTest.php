<?php

declare(strict_types=1);

use App\Agents\InvestmentAgent;
use App\Models\Investment\Holding;
use App\Models\Investment\InvestmentAccount;
use App\Models\Investment\RiskProfile;
use App\Models\User;
use App\Services\Investment\Rebalancing\DriftAnalyzer;
use App\Services\Investment\Tax\CGTHarvestingCalculator;
use App\Services\Tax\ChargeableGains;
use App\Services\Tax\Strategies\BedAndIsaStrategy;
use App\Services\Tax\Strategies\TaxStrategyContext;
use Database\Seeders\InvestmentActionDefinitionSeeder;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * Item 8, the investment cards review (docs/superpowers/specs/2026-10-06-investment-cards-review-design.md):
 * chargeable gains have one home, the losses card states real losses, a fund
 * with no recorded mix is not counted as drift, and the ISA cards step aside
 * for Bed & ISA.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(InvestmentActionDefinitionSeeder::class);
});

function item8Holding(InvestmentAccount $account, float $cost, float $value, string $type = 'equity'): Holding
{
    return Holding::factory()->create([
        'holdable_id' => $account->id,
        'holdable_type' => InvestmentAccount::class,
        'asset_type' => $type,
        'cost_basis' => $cost,
        'current_value' => $value,
    ]);
}

it('counts gains and losses only in chargeable accounts, a joint one at the user\'s share', function () {
    $user = User::factory()->create();
    $partner = User::factory()->create();

    $gia = InvestmentAccount::factory()->create(['user_id' => $user->id, 'account_type' => 'gia', 'ownership_type' => 'individual', 'joint_owner_id' => null, 'ownership_percentage' => 100]);
    $joint = InvestmentAccount::factory()->create(['user_id' => $partner->id, 'joint_owner_id' => $user->id, 'account_type' => 'gia', 'ownership_type' => 'joint', 'ownership_percentage' => 50]);
    $vct = InvestmentAccount::factory()->create(['user_id' => $user->id, 'account_type' => 'vct', 'ownership_type' => 'individual', 'joint_owner_id' => null, 'ownership_percentage' => 100]);
    $isa = InvestmentAccount::factory()->create(['user_id' => $user->id, 'account_type' => 'isa', 'ownership_type' => 'individual', 'joint_owner_id' => null, 'ownership_percentage' => 100]);

    item8Holding($gia, 10000, 13000);   // +3,000
    item8Holding($joint, 20000, 18000); // -2,000 whole, -1,000 at 50%
    item8Holding($vct, 5000, 1000);     // a VCT loss is not allowable (TCGA 1992 s151A)
    item8Holding($isa, 1000, 9000);     // no CGT in an ISA
    // Tax review 2026-10-06: EIS gains are exempt (s150A), a trust's are the
    // trustees' (s69), RSUs are taxed as income on vesting (ITEPA Part 7).
    foreach (['eis', 'trust', 'rsu'] as $type) {
        $other = InvestmentAccount::factory()->create(['user_id' => $user->id, 'account_type' => $type, 'ownership_type' => 'individual', 'joint_owner_id' => null, 'ownership_percentage' => 100]);
        item8Holding($other, 1000, 50000);
    }

    $rows = app(ChargeableGains::class)->holdingsFor($user);

    expect($rows->sum('gain'))->toEqualWithDelta(2000.0, 0.01)
        ->and(app(ChargeableGains::class)->unrealisedGainsFor($user)['gain'])->toEqualWithDelta(3000.0, 0.01);

    $losses = app(CGTHarvestingCalculator::class)->calculateHarvestingOpportunities($user->id);
    expect($losses['total_harvestable_losses'])->toEqualWithDelta(1000.0, 0.01)
        ->and($losses['opportunities'])->toHaveCount(1)
        ->and($losses['opportunities'][0]['account_id'])->toBe($joint->id);
});

it('does not count a fund with no recorded mix as drift', function () {
    $holdings = collect([
        (object) ['asset_type' => 'etf', 'current_value' => 60.0],         // unclassified
        (object) ['asset_type' => 'bond', 'current_value' => 25.0],
        (object) ['asset_type' => 'alternative', 'current_value' => 15.0],
    ]);

    $drift = app(DriftAnalyzer::class)->analyzeDrift($holdings, ['equities' => 75, 'bonds' => 20, 'cash' => 0, 'alternatives' => 5]);
    $byAsset = $drift['drift_metrics']['drifts_by_asset'];

    // The 60% sits where it closes the gaps first: all of it in shares (short by 75).
    expect($drift['unrecorded_percent'])->toEqualWithDelta(60.0, 0.01)
        ->and($byAsset['equities']['current'])->toEqualWithDelta(60.0, 0.01)
        ->and($byAsset['alternatives']['drift'])->toEqualWithDelta(10.0, 0.01)
        ->and($byAsset)->not->toHaveKey('unclassified');
});

it('leaves moving holdings into an ISA to Bed & ISA when the account holds gains', function () {
    $user = User::factory()->create([
        'annual_employment_income' => 60000,
        'date_of_birth' => '1980-01-01',
        'monthly_expenditure' => 2500,
    ]);
    RiskProfile::factory()->create(['user_id' => $user->id, 'risk_level' => 'medium']);
    $gia = InvestmentAccount::factory()->create(['user_id' => $user->id, 'account_type' => 'gia', 'ownership_type' => 'individual', 'joint_owner_id' => null, 'ownership_percentage' => 100]);
    item8Holding($gia, 10000, 13000);

    $bedAndIsa = app(BedAndIsaStrategy::class)->generate(new TaxStrategyContext($user, null, null, 'individual'));
    expect($bedAndIsa)->toHaveCount(1);

    $agent = app(InvestmentAgent::class);
    $analysis = $agent->analyze($user->id);
    $data = $analysis['data'] ?? $analysis;
    // The gate must be open, or no card fires and the test proves nothing.
    expect($data['can_proceed'] ?? true)->toBeTrue()
        ->and($data['tax_wrappers']['chargeable_unrealised_gain'])->toEqualWithDelta(3000.0, 0.01);
    $keys = collect($agent->generateRecommendations($data)['recommendations'])->pluck('definition_key');

    expect($keys)->not->toContain('open_isa')
        ->and($keys)->not->toContain('use_isa_allowance');
});

it('lets the joint owner open the account\'s rebalancing panel', function () {
    $owner = User::factory()->create();
    $partner = User::factory()->create();
    $joint = InvestmentAccount::factory()->create(['user_id' => $owner->id, 'joint_owner_id' => $partner->id, 'account_type' => 'gia', 'ownership_type' => 'joint', 'ownership_percentage' => 50]);

    Sanctum::actingAs($partner);

    $this->getJson("/api/investment/accounts/{$joint->id}/rebalancing")
        ->assertOk()
        ->assertJsonPath('data.account_id', $joint->id);
});
