<?php

declare(strict_types=1);

use App\Models\TaxConfiguration;
use App\Services\Marketing\SaveTaxEstimateService;
use Database\Seeders\TaxConfigurationSeeder;

/**
 * Locks the SaveTax dynamic-math model to the figures agreed with CSJ
 * (see June/June8Updates/savetax-math-spec.md). All inputs use the UPPER bound
 * of each income band; all tax values come from TaxConfigService (auto-seeded).
 */
beforeEach(function () {
    // Seed the real canonical 2026/27 config (the auto-seed uses a random
    // factory config; our assertions depend on the real values).
    TaxConfiguration::query()->delete();
    $this->seed(TaxConfigurationSeeder::class);
    $this->service = app(SaveTaxEstimateService::class);
});

function lineAmount(array $result, string $key): int
{
    foreach ($result['savings'] as $line) {
        if ($line['key'] === $key) {
            return $line['amount'];
        }
    }

    return 0;
}

it('computes pension relief per band (no existing pension)', function () {
    $assets = ['savings']; // financial but no pension

    expect(lineAmount($this->service->estimate(['income' => 'upto_50270', 'assets' => $assets]), 'pension'))->toBe(1005)
        ->and(lineAmount($this->service->estimate(['income' => '50271_100000', 'assets' => $assets]), 'pension'))->toBe(4000)
        ->and(lineAmount($this->service->estimate(['income' => 'over_125140', 'assets' => $assets]), 'pension'))->toBe(6750);
});

it('computes the exact 60% trap relief for the £100k-£125,140 band', function () {
    // £125,140 income, contribute £25,140 to clear to £100k: 20% at source
    // (£5,028) + income-tax fall (£10,056) = £15,084.
    $result = $this->service->estimate(['income' => '100001_125140', 'assets' => ['savings']]);

    expect(lineAmount($result, 'pension'))->toBe(15084);
});

it('omits the pension line when the user already has a pension', function () {
    $result = $this->service->estimate(['income' => '50271_100000', 'assets' => ['pension', 'savings']]);

    expect(lineAmount($result, 'pension'))->toBe(0);
});

it('computes ISA, PSA, dividend and CGT savings for a higher-rate saver-investor', function () {
    $result = $this->service->estimate([
        'income' => '50271_100000',
        'assets' => ['savings', 'investments'],
    ]);

    expect(lineAmount($result, 'isa'))->toBe(4000)        // 10% of £100k × 40%
        ->and(lineAmount($result, 'psa'))->toBe(200)      // £500 × 40%
        ->and(lineAmount($result, 'dividend'))->toBe(179) // £500 × 35.75%
        ->and(lineAmount($result, 'cgt'))->toBe(720);     // £3,000 × 24%
});

it('drops the Personal Savings Allowance saving at additional rate', function () {
    $result = $this->service->estimate(['income' => 'over_125140', 'assets' => ['savings']]);

    expect(lineAmount($result, 'psa'))->toBe(0);
});

it('adds spouse-transfer levers when the spouse earns nothing', function () {
    $result = $this->service->estimate([
        'income' => '50271_100000', // primary higher-rate, 40%
        'spouse' => 'yes',
        'spouseIncome' => 'zero',
        'assets' => [],
    ]);

    expect(lineAmount($result, 'spouse_pa'))->toBe(5028)            // £12,570 × 40%
        ->and(lineAmount($result, 'spouse_psa'))->toBe(400)        // £1,000 × 40%
        ->and(lineAmount($result, 'spouse_starting_rate'))->toBe(2000) // £5,000 × 40%
        ->and(lineAmount($result, 'marriage_allowance'))->toBe(252);    // £1,260 × 20%
});

it('does not add spouse levers when there is no spouse', function () {
    $result = $this->service->estimate(['income' => '50271_100000', 'assets' => []]);

    expect(lineAmount($result, 'spouse_pa'))->toBe(0)
        ->and(lineAmount($result, 'marriage_allowance'))->toBe(0);
});

it('builds the allowances-available total and doubles per-person allowances when married', function () {
    $single = $this->service->estimate(['income' => '50271_100000', 'assets' => ['savings', 'investments']]);
    // PA 12,570 + ISA 20,000 + AA 60,000 + PSA 500 + dividend 500 + CGT 3,000 = 96,570
    expect($single['allowances']['total'])->toBe(96570);

    $married = $this->service->estimate([
        'income' => '50271_100000',
        'spouse' => 'yes',
        'spouseIncome' => 'zero',
        'assets' => ['savings', 'investments'],
    ]);
    // + Marriage 1,260 + spouse PA 12,570 + spouse ISA 20,000 + spouse AA 60,000 = 190,400
    expect($married['allowances']['total'])->toBe(190400);
});

it('reports the active tax year from config', function () {
    $result = $this->service->estimate(['income' => 'upto_50270', 'assets' => []]);

    expect($result['tax_year'])->toBe('2026/27');
});
