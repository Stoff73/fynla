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
        ->and(lineAmount($result, 'marriage_allowance'))->toBe(0);     // higher-rate recipient is NOT eligible
});

it('offers Marriage Allowance only to a basic-rate recipient with a £0 spouse', function () {
    $basic = $this->service->estimate(['income' => 'upto_50270', 'spouse' => 'yes', 'spouseIncome' => 'zero', 'assets' => []]);
    expect(lineAmount($basic, 'marriage_allowance'))->toBe(252); // £1,260 × 20%

    $higher = $this->service->estimate(['income' => '50271_100000', 'spouse' => 'yes', 'spouseIncome' => 'zero', 'assets' => []]);
    expect(lineAmount($higher, 'marriage_allowance'))->toBe(0);
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
    // Higher-rate primary → Marriage Allowance NOT eligible (excluded).
    // + spouse PA 12,570 + spouse ISA 20,000 + spouse AA 60,000 = 189,140
    expect($married['allowances']['total'])->toBe(189140);
});

it('reports the active tax year from config', function () {
    $result = $this->service->estimate(['income' => 'upto_50270', 'assets' => []]);

    expect($result['tax_year'])->toBe('2026/27');
});

function itemOn(array $result, string $key): ?bool
{
    foreach ($result['allowances']['items'] as $i) {
        if ($i['key'] === $key) {
            return $i['on'];
        }
    }

    return null;
}

function hasSaving(array $result, string $key): bool
{
    foreach ($result['savings'] as $s) {
        if ($s['key'] === $key) {
            return true;
        }
    }

    return false;
}

it('highlights the correct allowances and keeps the math consistent for every possible answer', function () {
    $bands = [
        'upto_50270' => 50270,
        '50271_100000' => 100000,
        '100001_125140' => 125140,
        'over_125140' => 150000,
    ];
    $spouseOptions = [null, 'zero', 'upto_50270', '50271_100000', '100001_125140', 'over_125140'];
    $assetKeys = ['bank', 'savings', 'pension', 'property', 'isa', 'investments'];

    $combos = 0;

    foreach ($bands as $incomeBand => $income) {
        $primaryBasic = $income <= 50270;
        $psaBand = $income > 125140 ? 0 : ($income > 50270 ? 500 : 1000);
        $expectedPa = (int) round(max(0.0, min(12570.0, 12570.0 - max(0, $income - 100000) * 0.5)));

        foreach ($spouseOptions as $spouseOpt) {
            $married = $spouseOpt !== null;

            for ($mask = 0; $mask < 64; $mask++) {
                $assets = [];
                foreach ($assetKeys as $bit => $key) {
                    if ($mask & (1 << $bit)) {
                        $assets[] = $key;
                    }
                }
                $combos++;

                $has = fn (string ...$k): bool => (bool) array_intersect($k, $assets);
                $hasFinancial = $has('isa', 'savings', 'investments', 'bank');

                $r = $this->service->estimate([
                    'income' => $incomeBand,
                    'spouse' => $married ? 'yes' : 'no',
                    'spouseIncome' => $spouseOpt,
                    'assets' => $assets,
                ]);

                $label = "income={$incomeBand} spouse=".($spouseOpt ?? 'none').' assets='.implode(',', $assets);

                // --- Structural / math invariants ---
                $sumSavings = array_sum(array_column($r['savings'], 'amount'));
                expect($r['savings_total'])->toBe($sumSavings, "savings_total != sum [$label]");

                $sumOnAllowances = 0;
                foreach ($r['allowances']['items'] as $i) {
                    expect($i['amount'])->toBeGreaterThanOrEqual(0, "negative allowance [$label:{$i['key']}]");
                    if ($i['on']) {
                        $sumOnAllowances += $i['amount'];
                    }
                }
                expect($r['allowances']['total'])->toBe($sumOnAllowances, "allowances total != sum of on [$label]");
                foreach ($r['savings'] as $s) {
                    expect($s['amount'])->toBeGreaterThanOrEqual(0, "negative saving [$label:{$s['key']}]");
                }

                // --- Allowance highlighting (on/off) correctness ---
                expect(itemOn($r, 'personal_allowance'))->toBeTrue("PA must always show [$label]");
                expect(itemOn($r, 'isa'))->toBeTrue("ISA must always show [$label]");
                expect(itemOn($r, 'pension_aa'))->toBeTrue("Pension AA must always show [$label]");
                expect(itemOn($r, 'psa'))->toBe(($has('savings', 'bank') && $psaBand > 0), "PSA on/off wrong [$label]");
                expect(itemOn($r, 'dividend'))->toBe($has('investments'), "Dividend on/off wrong [$label]");
                expect(itemOn($r, 'cgt'))->toBe($has('investments', 'property'), "CGT on/off wrong [$label]");

                // PA amount = correct taper
                $paAmount = null;
                foreach ($r['allowances']['items'] as $i) {
                    if ($i['key'] === 'personal_allowance') {
                        $paAmount = $i['amount'];
                    }
                }
                expect($paAmount)->toBe($expectedPa, "PA taper amount wrong [$label]");

                // Marriage Allowance: only present (and only "on") when married,
                // spouse earns £0, and the recipient is basic-rate.
                $marriageEligible = $married && $spouseOpt === 'zero' && $primaryBasic;
                if ($married) {
                    expect(itemOn($r, 'marriage_allowance'))->toBe($marriageEligible, "MA eligibility wrong [$label]");
                    expect(itemOn($r, 'spouse_pa'))->toBeTrue("spouse PA item missing [$label]");
                } else {
                    expect(itemOn($r, 'marriage_allowance'))->toBeNull("MA shown for single [$label]");
                    expect(itemOn($r, 'spouse_pa'))->toBeNull("spouse PA shown for single [$label]");
                }

                // --- Saving-line presence correctness ---
                expect(hasSaving($r, 'pension'))->toBe(! $has('pension'), "pension saving presence wrong [$label]");
                expect(hasSaving($r, 'isa'))->toBe($hasFinancial, "ISA saving presence wrong [$label]");
                expect(hasSaving($r, 'psa'))->toBe(($has('savings', 'bank') && $psaBand > 0), "PSA saving presence wrong [$label]");
                expect(hasSaving($r, 'dividend'))->toBe($has('investments'), "dividend saving presence wrong [$label]");
                expect(hasSaving($r, 'cgt'))->toBe($has('investments', 'property'), "CGT saving presence wrong [$label]");

                $spouseZero = $married && $spouseOpt === 'zero';
                expect(hasSaving($r, 'spouse_pa'))->toBe($spouseZero, "spouse_pa saving presence wrong [$label]");
                expect(hasSaving($r, 'spouse_psa'))->toBe($spouseZero, "spouse_psa saving presence wrong [$label]");
                expect(hasSaving($r, 'spouse_starting_rate'))->toBe($spouseZero, "spouse_starting_rate presence wrong [$label]");
                expect(hasSaving($r, 'marriage_allowance'))->toBe($marriageEligible, "MA saving presence wrong [$label]");
            }
        }
    }

    expect($combos)->toBe(4 * 6 * 64); // 1,536 combinations exercised
});
