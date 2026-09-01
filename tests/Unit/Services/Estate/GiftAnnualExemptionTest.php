<?php

declare(strict_types=1);

use App\Services\Estate\GiftAnnualExemption;
use App\Services\TaxConfigService;

/**
 * W-0367 — a gift's chargeable value is net of the annual exemption.
 *
 * `FailedGiftTaxCalculator` took `gift_value` gross, so none of the lifetime
 * exemptions ever reduced a chargeable transfer. A donor giving £3,000 in a tax
 * year — exactly the exemption, so nothing chargeable at all — had the whole
 * £3,000 cumulated against their nil rate band.
 *
 * The exemption needs no new data. IHTA 1984 s19 applies it chronologically
 * within a tax year, and the UK tax year begins on 6 April, so `gift_date` and
 * `gift_value` are sufficient. One unused year may be carried forward, and only
 * one — and the current year's allowance is spent before the brought-forward one.
 */
function exemptionRules(float $annual = 3000, bool $carry = true, int $years = 1): TaxConfigService
{
    $service = Mockery::mock(TaxConfigService::class)->makePartial();
    $service->shouldReceive('get')
        ->with('gifting_exemptions', [])
        ->andReturn([
            'annual_exemption' => $annual,
            'annual_exemption_can_carry_forward' => $carry,
            'carry_forward_years' => $years,
        ]);

    return $service;
}

/** @param list<array{gift_date: string, value: float}> $gifts */
function chargeable(array $gifts, ?TaxConfigService $config = null): array
{
    return (new GiftAnnualExemption($config ?? exemptionRules()))->applyTo($gifts);
}

it('exempts a gift that fits entirely inside one year allowance', function () {
    $result = chargeable([['gift_date' => '2024-06-01', 'value' => 3000.0]]);

    // £3,000 given, £3,000 exempt, nothing chargeable.
    expect($result[0]['value'])->toBe(0.0)
        ->and($result[0]['exempt'])->toBe(3000.0);
});

it('charges only the excess over the allowance', function () {
    // Carry-forward off, so this isolates the single-year rule.
    $result = chargeable(
        [['gift_date' => '2024-06-01', 'value' => 10000.0]],
        exemptionRules(carry: false)
    );

    expect($result[0]['value'])->toBe(7000.0)
        ->and($result[0]['exempt'])->toBe(3000.0);
});

it('spends the allowance chronologically within a tax year', function () {
    // s19 applies it to the earliest gift first. The second gift gets what is
    // left, not a share.
    $result = chargeable([
        ['gift_date' => '2024-06-01', 'value' => 2000.0],
        ['gift_date' => '2024-09-01', 'value' => 5000.0],
    ], exemptionRules(carry: false));

    expect($result[0]['value'])->toBe(0.0)
        ->and($result[1]['value'])->toBe(4000.0);
});

it('starts a fresh allowance on 6 April, not 1 January', function () {
    // 2025-04-05 and 2025-04-06 are different tax years. Getting this wrong
    // gives a donor two allowances in one year or one across two.
    $result = chargeable([
        ['gift_date' => '2025-04-05', 'value' => 3000.0],
        ['gift_date' => '2025-04-06', 'value' => 3000.0],
    ]);

    expect($result[0]['value'])->toBe(0.0)
        ->and($result[1]['value'])->toBe(0.0);
});

it('carries one unused year forward and no more', function () {
    // Nothing given in 2023/24, so 2024/25 has £6,000 available - but a second
    // idle year does not accumulate a third allowance.
    $result = chargeable([['gift_date' => '2024-06-01', 'value' => 10000.0]]);

    expect($result[0]['exempt'])->toBe(6000.0)
        ->and($result[0]['value'])->toBe(4000.0);
});

it('spends the current year before the brought-forward year', function () {
    // The configured note says so, and the order matters: spending the
    // brought-forward allowance first would let it survive into a year it has
    // already expired in.
    $result = chargeable([
        ['gift_date' => '2024-06-01', 'value' => 4000.0],
        ['gift_date' => '2025-06-01', 'value' => 4000.0],
    ]);

    // 2024/25: 3,000 current + 3,000 carried = 6,000 available, 4,000 used.
    // 2025/26: 3,000 current only - 2024/25 was fully consumed, nothing carries.
    expect($result[0]['value'])->toBe(0.0)
        ->and($result[1]['value'])->toBe(1000.0);
});

it('takes the allowance from configuration, not a literal', function () {
    $moved = chargeable(
        [['gift_date' => '2024-06-01', 'value' => 10000.0]],
        exemptionRules(annual: 5000, carry: false)
    );

    expect($moved[0]['exempt'])->toBe(5000.0)
        ->and($moved[0]['value'])->toBe(5000.0);
});
