<?php

declare(strict_types=1);

use App\Constants\TaxDefaults;
use App\Models\Investment\Holding;
use App\Services\Investment\AssetLocation\TaxDragCalculator;
use App\Services\Investment\Recommendation\SpouseOptimisationService;
use App\Services\TaxConfigService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

/**
 * Audit fix — stale/hardcoded tax rates now read from config/constants (Rule #2).
 *
 * 1. TaxDragCalculator::calculateGIATaxDrag() previously matched hardcoded
 *    2025/26 dividend rates (0.0875 / 0.3375 / 0.3935); it now uses the
 *    TaxConfigService `dividend_tax` block it already fetches for the allowance,
 *    so the seeded 2026/27 rates (10.75% / 35.75%) drive the drag.
 * 2. SpouseOptimisationService::strategyNonEarningPension() previously inlined
 *    3600 / 2880; it now quotes TaxDefaults::NON_EARNER_PENSION_* constants.
 */
it('seeds the 2026/27 dividend rates', function () {
    $dividend = app(TaxConfigService::class)->getDividendTax();

    expect($dividend['basic_rate'])->toBe(0.1075)
        ->and($dividend['higher_rate'])->toBe(0.3575);
});

/**
 * Build a GIA drag scenario where the whole tax_amount is dividend tax:
 * expected_return == the estimated dividend yield => zero capital gain, and an
 * equity asset type => zero interest. dividend_yield is left unset so it is
 * sourced from TaxConfigService (avoids the Holding decimal cast returning a
 * string from a directly-assigned property — a pre-existing quirk, out of scope).
 *
 * @return array{tax_amount: float, taxable_dividend: float, breakdown: array}
 */
function gia_dividend_drag(float $incomeTaxRate): array
{
    $yields = app(TaxConfigService::class)->get('investment.asset_class_yields', []);
    $divYield = (float) ($yields['uk_equity']['income_yield'] ?? 0.035);
    $allowance = (float) (app(TaxConfigService::class)->getDividendTax()['allowance'] ?? 500);

    $holding = new Holding;
    $holding->asset_type = 'uk_equity';
    $holding->current_value = 100000;

    $profile = ['expected_return' => $divYield, 'income_tax_rate' => $incomeTaxRate, 'cgt_rate' => 0.24];

    $result = app(TaxDragCalculator::class)->calculateTaxDragByAccountType($holding, 'gia', $profile);

    return [
        'tax_amount' => $result['tax_amount'],
        'taxable_dividend' => max(0.0, 100000 * $divYield - $allowance),
        'breakdown' => $result['breakdown'],
    ];
}

it('applies the 2026/27 basic dividend rate (10.75%) to GIA dividend drag', function () {
    $r = gia_dividend_drag(0.20);

    // Whole drag is dividend tax; taxable dividend × 10.75% proves the current rate.
    expect($r['breakdown']['capital_gain'])->toBe(0.0)
        ->and($r['taxable_dividend'])->toBeGreaterThan(0.0)
        ->and($r['tax_amount'])->toEqualWithDelta($r['taxable_dividend'] * 0.1075, 0.01);
});

it('applies the 2026/27 higher dividend rate (35.75%) to GIA dividend drag', function () {
    $r = gia_dividend_drag(0.40);

    expect($r['breakdown']['capital_gain'])->toBe(0.0)
        ->and($r['tax_amount'])->toEqualWithDelta($r['taxable_dividend'] * 0.3575, 0.01);
});

it('quotes the non-earner pension figures from TaxDefaults constants', function () {
    // Sanity: gross (£3,600) is the sum of the net-contribution and uplift constants.
    expect(TaxDefaults::NON_EARNER_PENSION_NET_CONTRIBUTION)->toBe(2880)
        ->and(TaxDefaults::NON_EARNER_PENSION_NET_CONTRIBUTION + TaxDefaults::NON_EARNER_PENSION_GOVERNMENT_UPLIFT)->toBe(3600);

    $context = [
        'personal' => ['employment_status' => 'employed'],
        'financial' => ['gross_income' => 60000, 'tax_band' => 'higher'],
        'spouse' => [
            'name' => 'Jane',
            'gross_income' => 0.0,          // strict 0.0 triggers the non-earner branch
            'tax_band' => 'non_taxpayer',
            'employment_status' => 'not_working',
        ],
    ];

    $result = app(SpouseOptimisationService::class)->optimise($context);

    $rec = collect($result['recommendations'])
        ->firstWhere('strategy_type', 'non_earning_spouse_pension');

    expect($rec)->not->toBeNull()
        ->and($rec['amount'])->toBe(2880.0)
        ->and($rec['explanation'])->toContain('£3,600')
        ->and($rec['explanation'])->toContain('£2,880');
});
