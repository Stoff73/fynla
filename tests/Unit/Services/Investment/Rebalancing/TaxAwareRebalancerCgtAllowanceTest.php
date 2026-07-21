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
 * Sibling of TaxAwareRebalancerCgtRateTest — same Wave 2.5 BADR fail-loud
 * pattern, this time for the CGT annual exempt amount rather than the rate.
 *
 * Before this fix:
 *  - `optimizeForCGT` and `rebalanceWithinCGTAllowance` silently fell back to
 *    `?? 3000` when neither caller-supplied cgt_allowance nor cgtConfig was
 *    available.
 *  - `RebalancingCalculationController` defaulted `cgt_allowance` to the
 *    config value with a hardcoded `?? 3000` at four sites, plus capped the
 *    response field `allowance_used` at a hardcoded `min(..., 3000)`.
 *
 * After the fix:
 *  - The service resolves the allowance from `options['cgt_allowance']` first,
 *    then `cgtConfig['annual_exempt_amount']`, then throws.
 *  - The controller passes `cgt_allowance => null` to let the service look up
 *    the config default.
 *  - The controller's `allowance_used` response field uses the service's
 *    already-computed `cgt_analysis['allowance_used']`.
 */
function rebalanceAllowanceTestHoldings(): array
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

    return [$user, collect([$gain]), $gain];
}

function sellAllAllowance(Holding $holding): array
{
    return [[
        'action_type' => 'sell',
        'holding_id' => $holding->id,
        'shares_to_trade' => (float) $holding->quantity,
        'trade_value' => (float) $holding->quantity * $holding->current_price,
    ]];
}

it('uses options[cgt_allowance] when the caller provides one', function () {
    [, $holdings, $gain] = rebalanceAllowanceTestHoldings();

    $result = app(TaxAwareRebalancer::class)->optimizeForCGT(
        sellAllAllowance($gain),
        $holdings,
        ['cgt_allowance' => 6000, 'tax_rate' => 0.24]
    );

    // £5,000 gain, £6,000 allowance → £0 taxable, but allowance_used capped
    // at the gain figure (min(allowance, netGains)).
    expect($result['cgt_analysis']['allowance_used'])->toEqualWithDelta(5000.0, 0.01);
    expect($result['cgt_analysis']['cgt_liability'])->toEqualWithDelta(0.0, 0.01);
});

it('falls back to config annual_exempt_amount when options[cgt_allowance] is null', function () {
    [, $holdings, $gain] = rebalanceAllowanceTestHoldings();

    $result = app(TaxAwareRebalancer::class)->optimizeForCGT(
        sellAllAllowance($gain),
        $holdings,
        ['cgt_allowance' => null, 'tax_rate' => 0.18]
    );

    // Seeded post-30-October-2024 annual_exempt_amount is £3,000.
    // £5,000 gain - £3,000 allowance = £2,000 taxable × 0.18 = £360.
    expect($result['cgt_analysis']['allowance_used'])->toEqualWithDelta(3000.0, 0.01);
    expect($result['cgt_analysis']['cgt_liability'])->toEqualWithDelta(360.0, 0.01);
});

it('falls back to config annual_exempt_amount when options[cgt_allowance] is absent entirely', function () {
    [, $holdings, $gain] = rebalanceAllowanceTestHoldings();

    $result = app(TaxAwareRebalancer::class)->optimizeForCGT(
        sellAllAllowance($gain),
        $holdings,
        ['tax_rate' => 0.18]
    );

    expect($result['cgt_analysis']['allowance_used'])->toEqualWithDelta(3000.0, 0.01);
    expect($result['cgt_analysis']['cgt_liability'])->toEqualWithDelta(360.0, 0.01);
});

it('throws FinancialCalculationException when neither caller allowance nor config annual_exempt_amount is available', function () {
    [, $holdings, $gain] = rebalanceAllowanceTestHoldings();

    $activeConfig = TaxConfiguration::where('is_active', true)->first();
    $config = $activeConfig->config_data;
    unset($config['capital_gains_tax']['annual_exempt_amount']);
    $activeConfig->update(['config_data' => $config]);

    // Fresh stack so the TaxConfigService doesn't return a cached copy.
    $service = new TaxAwareRebalancer(new TaxConfigService);

    expect(fn () => $service->optimizeForCGT(sellAllAllowance($gain), $holdings, ['tax_rate' => 0.18]))
        ->toThrow(FinancialCalculationException::class);
});

it('rebalanceWithinCGTAllowance also fails loud when annual_exempt_amount is missing', function () {
    [, $holdings, $gain] = rebalanceAllowanceTestHoldings();

    $activeConfig = TaxConfiguration::where('is_active', true)->first();
    $config = $activeConfig->config_data;
    unset($config['capital_gains_tax']['annual_exempt_amount']);
    $activeConfig->update(['config_data' => $config]);

    $service = new TaxAwareRebalancer(new TaxConfigService);

    expect(fn () => $service->rebalanceWithinCGTAllowance(sellAllAllowance($gain), $holdings, ['tax_rate' => 0.18]))
        ->toThrow(FinancialCalculationException::class);
});

it('caller-supplied allowance overrides config value (not min/max — direct override)', function () {
    [, $holdings, $gain] = rebalanceAllowanceTestHoldings();

    // Pass a lower-than-config allowance; service must honour it, not silently
    // bump up to the config value.
    $result = app(TaxAwareRebalancer::class)->optimizeForCGT(
        sellAllAllowance($gain),
        $holdings,
        ['cgt_allowance' => 1000, 'tax_rate' => 0.18]
    );

    // £5,000 gain - £1,000 allowance = £4,000 taxable × 0.18 = £720.
    expect($result['cgt_analysis']['allowance_used'])->toEqualWithDelta(1000.0, 0.01);
    expect($result['cgt_analysis']['cgt_liability'])->toEqualWithDelta(720.0, 0.01);
});
