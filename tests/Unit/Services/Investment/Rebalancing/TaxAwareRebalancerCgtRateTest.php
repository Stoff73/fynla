<?php

declare(strict_types=1);

use App\Exceptions\FinancialCalculationException;
use App\Models\Investment\Holding;
use App\Models\Investment\InvestmentAccount;
use App\Models\TaxConfiguration;
use App\Models\User;
use App\Services\Investment\Rebalancing\TaxAwareRebalancer;
use App\Services\TaxConfigService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

/**
 * REVIEW §4 High #29 — backend defect surface.
 *
 * Before this fix:
 *  - `TaxAwareRebalancer::optimizeForCGT` and `::rebalanceWithinCGTAllowance`
 *    silently fell back to `?? 0.10` (pre-30-October-2024 CGT basic rate)
 *    when neither caller-supplied tax_rate nor cgtConfig was available.
 *  - `identifyTaxLossHarvesting` hardcoded `* 0.20` (pre-30-October-2024
 *    higher rate) for both per-holding `potential_tax_benefit` and the
 *    rollup `potential_tax_saving`.
 *  - `RebalancingCalculationController` defaulted `tax_rate => 0.20` at
 *    four sites, bypassing any service-side guard.
 *
 * After the fix:
 *  - The service resolves the rate from `options['tax_rate']` first, then
 *    `cgtConfig['basic_rate']`, then throws (Wave 2.5 BADR-sibling pattern).
 *  - The harvesting helper threads the resolved rate through.
 *  - The controller no longer hardcodes 0.20 — it passes null and lets the
 *    service look up the config default.
 */
function rebalanceTestHoldings(): array
{
    $user = User::factory()->create();
    $account = InvestmentAccount::factory()->create(['user_id' => $user->id]);

    // Gainer: 10 shares bought at 100, now worth 600 each → £5,000 unrealised gain.
    $gain = Holding::factory()->forAccount($account)->create([
        'purchase_price' => 100,
        'current_price' => 600,
        'quantity' => 10,
        'purchase_date' => now()->subYears(3),
    ]);

    // Loss-maker: 10 shares bought at 100, now worth 50 each → £500 unrealised loss.
    $loss = Holding::factory()->forAccount($account)->create([
        'purchase_price' => 100,
        'current_price' => 50,
        'quantity' => 10,
        'purchase_date' => now()->subYears(2),
    ]);

    return [$user, collect([$gain, $loss]), $gain, $loss];
}

function sellAllOfHolding(Holding $holding): array
{
    return [[
        'action_type' => 'sell',
        'holding_id' => $holding->id,
        'shares_to_trade' => (float) $holding->quantity,
        'trade_value' => (float) $holding->quantity * $holding->current_price,
    ]];
}

it('uses options[tax_rate] when the caller provides one', function () {
    [, $holdings, $gain] = rebalanceTestHoldings();

    $result = app(TaxAwareRebalancer::class)->optimizeForCGT(
        sellAllOfHolding($gain),
        $holdings,
        ['tax_rate' => 0.24]
    );

    // Sell £5,000 gain, £3,000 allowance → £2,000 taxable → 2,000 * 0.24 = £480.
    expect($result['cgt_analysis']['cgt_liability'])->toEqualWithDelta(480.0, 0.01);
});

it('falls back to config basic_rate when options[tax_rate] is null', function () {
    [, $holdings, $gain] = rebalanceTestHoldings();

    $result = app(TaxAwareRebalancer::class)->optimizeForCGT(
        sellAllOfHolding($gain),
        $holdings,
        ['tax_rate' => null]
    );

    // Seeded post-30-October-2024 basic_rate is 0.18 → 2,000 * 0.18 = £360.
    expect($result['cgt_analysis']['cgt_liability'])->toEqualWithDelta(360.0, 0.01);
});

it('falls back to config basic_rate when options[tax_rate] is absent entirely', function () {
    [, $holdings, $gain] = rebalanceTestHoldings();

    $result = app(TaxAwareRebalancer::class)->optimizeForCGT(
        sellAllOfHolding($gain),
        $holdings,
        []
    );

    expect($result['cgt_analysis']['cgt_liability'])->toEqualWithDelta(360.0, 0.01);
});

it('throws FinancialCalculationException when neither caller rate nor config basic_rate is available', function () {
    [, $holdings, $gain] = rebalanceTestHoldings();

    $activeConfig = TaxConfiguration::where('is_active', true)->first();
    $config = $activeConfig->config_data;
    unset($config['capital_gains_tax']['basic_rate']);
    $activeConfig->update(['config_data' => $config]);

    // Fresh stack so the TaxConfigService doesn't return a cached copy.
    $service = new TaxAwareRebalancer(new TaxConfigService);

    expect(fn () => $service->optimizeForCGT(sellAllOfHolding($gain), $holdings, []))
        ->toThrow(FinancialCalculationException::class);
});

it('threads the resolved tax rate into tax-loss harvesting potential_tax_benefit (not hardcoded 0.20)', function () {
    [, $holdings, $gain] = rebalanceTestHoldings();

    $result = app(TaxAwareRebalancer::class)->optimizeForCGT(
        sellAllOfHolding($gain),
        $holdings,
        ['tax_rate' => 0.24]
    );

    // Loss holding: 10 * (100 - 50) = £500 unrealised loss.
    // Pre-fix: £500 * 0.20 = £100. Post-fix at 0.24: £500 * 0.24 = £120.
    $opps = $result['tax_loss_opportunities']['opportunities'];
    expect($opps)->toHaveCount(1);
    expect($opps[0]['potential_tax_benefit'])->toEqualWithDelta(120.0, 0.01);
});

it('threads the resolved tax rate into tax-loss harvesting potential_tax_saving (not hardcoded 0.20)', function () {
    [, $holdings, $gain] = rebalanceTestHoldings();

    $result = app(TaxAwareRebalancer::class)->optimizeForCGT(
        sellAllOfHolding($gain),
        $holdings,
        ['tax_rate' => 0.18]
    );

    // taxable_gains £2,000; total potential losses £500 → min = £500. £500 * 0.18 = £90.
    expect($result['tax_loss_opportunities']['potential_tax_saving'])
        ->toEqualWithDelta(90.0, 0.01);
});

it('rebalanceWithinCGTAllowance also fails loud when basic_rate is missing', function () {
    [, $holdings, $gain] = rebalanceTestHoldings();

    $activeConfig = TaxConfiguration::where('is_active', true)->first();
    $config = $activeConfig->config_data;
    unset($config['capital_gains_tax']['basic_rate']);
    $activeConfig->update(['config_data' => $config]);

    $service = new TaxAwareRebalancer(new TaxConfigService);

    expect(fn () => $service->rebalanceWithinCGTAllowance(sellAllOfHolding($gain), $holdings, []))
        ->toThrow(FinancialCalculationException::class);
});
