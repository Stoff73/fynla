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

    // Surfaced as a distinct "60% Tax Trap" line (not the generic pension line).
    expect(lineAmount($result, 'tax_trap_60'))->toBe(15084)
        ->and(lineAmount($result, 'pension'))->toBe(0);
});

it('merges the 60% trap into the tapered Personal Allowance card (no standalone trap row)', function () {
    $trap = $this->service->estimate(['income' => '100001_125140', 'assets' => ['savings']]);

    // The standalone 60% Tax Trap allowance card is removed everywhere.
    expect(itemOn($trap, 'tax_trap_60'))->toBeNull();

    // The Personal Allowance card carries the taper in the trap band; its label
    // gains "(tapered)" and the trap saving still drives the headline figure.
    $pa = collect($trap['allowances']['items'])->firstWhere('key', 'personal_allowance');
    expect($pa['on'])->toBeTrue()
        ->and($pa['label'])->toContain('(tapered)')
        ->and($pa['amount'])->toBe(0) // £125,140 → Personal Allowance fully tapered
        ->and(hasSaving($trap, 'tax_trap_60'))->toBeTrue();
});

it('greys the Personal Allowance for a working earner; shows it only for £0 or the £100k–£125,140 band', function () {
    // A working earner's Personal Allowance is used automatically by their
    // salary — greyed.
    expect(itemOn($this->service->estimate(['income' => 'upto_50270', 'assets' => []]), 'personal_allowance'))->toBeFalse()
        ->and(itemOn($this->service->estimate(['income' => '50271_100000', 'assets' => []]), 'personal_allowance'))->toBeFalse()
        ->and(itemOn($this->service->estimate(['income' => 'over_125140', 'assets' => []]), 'personal_allowance'))->toBeFalse()
        // Shown only in the £100k–£125,140 taper (reclaimable via a pension).
        ->and(itemOn($this->service->estimate(['income' => '100001_125140', 'assets' => []]), 'personal_allowance'))->toBeTrue();
});

it('always shows the Pension Annual Allowance — £60k for a worker (it is for pensions, not income)', function () {
    // The pension annual allowance is shown in every working band.
    expect(itemOn($this->service->estimate(['income' => 'upto_50270', 'assets' => []]), 'pension_aa'))->toBeTrue()
        ->and(itemOn($this->service->estimate(['income' => '50271_100000', 'assets' => []]), 'pension_aa'))->toBeTrue()
        ->and(itemOn($this->service->estimate(['income' => 'over_125140', 'assets' => []]), 'pension_aa'))->toBeTrue();

    foreach (['upto_50270', '50271_100000', '100001_125140', 'over_125140'] as $band) {
        $aa = collect($this->service->estimate(['income' => $band, 'assets' => []])['allowances']['items'])->firstWhere('key', 'pension_aa');
        expect($aa['amount'])->toBe(60000); // a worker gets the full £60,000
    }
});

it('shows a non-earning spouse a £3,600 pension allowance and the £5,000 starting-rate card', function () {
    $r = $this->service->estimate(['income' => '50271_100000', 'spouse' => 'yes', 'spouseIncome' => 'zero', 'assets' => []]);

    $spouseAa = collect($r['allowances']['items'])->firstWhere('key', 'spouse_pension_aa');
    expect($spouseAa['on'])->toBeTrue()
        ->and($spouseAa['amount'])->toBe(3600) // capped at the relevant-earnings minimum, not £60k
        ->and($spouseAa['note'])->toContain('£2,880'); // net contribution after basic-rate relief

    $spouseStart = collect($r['allowances']['items'])->firstWhere('key', 'spouse_starting_rate');
    expect($spouseStart['on'])->toBeTrue()
        ->and($spouseStart['amount'])->toBe(5000);

    // A spouse who DOES earn gets the full £60k allowance gating + greyed starting rate.
    $earning = $this->service->estimate(['income' => '50271_100000', 'spouse' => 'yes', 'spouseIncome' => '100001_125140', 'assets' => []]);
    $earningAa = collect($earning['allowances']['items'])->firstWhere('key', 'spouse_pension_aa');
    expect($earningAa['on'])->toBeTrue()->and($earningAa['amount'])->toBe(60000)
        ->and(itemOn($earning, 'spouse_starting_rate'))->toBeFalse();
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

it('omits the "automatically used" note from the spouse Personal Allowance card', function () {
    // In the generalised (unregistered) estimate we don't know the spouse's real
    // position, so the shared PA builder must NOT claim their allowance is
    // "automatically used against your income" — only the user's own card may (CSJ).
    $result = $this->service->estimate([
        'income' => '50271_100000',
        'spouse' => 'yes',
        'spouseIncome' => 'upto_50270', // working spouse: income > 0, not tapered
        'assets' => [],
    ]);

    $spousePa = collect($result['allowances']['items'])->firstWhere('key', 'spouse_pa');
    $userPa = collect($result['allowances']['items'])->firstWhere('key', 'personal_allowance');

    expect($spousePa)->not->toBeNull()
        ->and($spousePa['note'] ?? '')->not->toContain('Automatically used') // dropped for the spouse card
        ->and($userPa['note'] ?? '')->toContain('Automatically used');       // kept on the user's own card
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
    // Working earner → Personal Allowance greyed (auto-used); Pension AA shown.
    // ISA 20,000 + Pension AA 60,000 + PSA 500 + dividend 500 + CGT 3,000 = 84,000
    expect($single['allowances']['total'])->toBe(84000);

    $married = $this->service->estimate([
        'income' => '50271_100000',
        'spouse' => 'yes',
        'spouseIncome' => 'zero',
        'assets' => ['savings', 'investments'],
    ]);
    // Single part 84,000 + spouse PA 12,570 (non-earner, shown) + starting rate
    // 5,000 + spouse PSA 1,000 + spouse ISA 20,000 + spouse AA 3,600 (non-earner)
    // + spouse dividend 500 + spouse CGT 3,000 = 129,670.
    // Marriage Allowance NOT eligible (higher-rate primary).
    expect($married['allowances']['total'])->toBe(129670);
});

it('gives the spouse every per-person allowance the primary gets (PSA, dividend, CGT)', function () {
    // Non-earning spouse: PSA at the basic rate (£1,000), plus dividend and CGT.
    $nonEarner = $this->service->estimate([
        'income' => '50271_100000', 'spouse' => 'yes', 'spouseIncome' => 'zero',
        'assets' => ['savings', 'investments'],
    ]);
    $psa = collect($nonEarner['allowances']['items'])->firstWhere('key', 'spouse_psa');
    expect($psa['on'])->toBeTrue()->and($psa['amount'])->toBe(1000); // basic-rate PSA
    expect(itemOn($nonEarner, 'spouse_dividend'))->toBeTrue()
        ->and(itemOn($nonEarner, 'spouse_cgt'))->toBeTrue();

    // Earning (higher-rate) spouse: PSA at the band amount (£500), same as the primary.
    $earner = $this->service->estimate([
        'income' => 'upto_50270', 'spouse' => 'yes', 'spouseIncome' => '50271_100000',
        'assets' => ['savings', 'investments'],
    ]);
    expect(collect($earner['allowances']['items'])->firstWhere('key', 'spouse_psa')['amount'])->toBe(500);

    // No household savings → spouse PSA greyed (same gate as the primary's PSA).
    $noSavings = $this->service->estimate([
        'income' => '50271_100000', 'spouse' => 'yes', 'spouseIncome' => 'zero', 'assets' => [],
    ]);
    expect(itemOn($noSavings, 'spouse_psa'))->toBeFalse();
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
        $isTrap = $incomeBand === '100001_125140';
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
                // Personal Allowance: greyed for a working earner (auto-used);
                // shown only in the £100k–£125,140 taper (primary has no £0 band).
                expect(itemOn($r, 'personal_allowance'))->toBe($isTrap, "PA gating wrong [$label]");
                expect(itemOn($r, 'isa'))->toBeTrue("ISA must always show [$label]");
                // Pension Annual Allowance is always shown — a worker gets £60k.
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
                    // Spouse Personal Allowance: greyed for a working spouse; shown
                    // only for a non-earner (£0) or the £100k–£125,140 taper.
                    $spousePaClaimable = $spouseOpt === 'zero' || $spouseOpt === '100001_125140';
                    expect(itemOn($r, 'spouse_pa'))->toBe($spousePaClaimable, "spouse PA gating wrong [$label]");
                    // Spouse Pension AA always shown (£3,600 non-earner / £60k worker);
                    // spouse Starting Rate for Savings only for a non-earning spouse.
                    expect(itemOn($r, 'spouse_pension_aa'))->toBeTrue("spouse AA must always show [$label]");
                    expect(itemOn($r, 'spouse_starting_rate'))->toBe($spouseOpt === 'zero', "spouse starting-rate gating wrong [$label]");
                    // Spouse per-person PSA / dividend / CGT mirror the primary's gating.
                    $spouseInc = ['zero' => 0, 'upto_50270' => 50270, '50271_100000' => 100000, '100001_125140' => 125140, 'over_125140' => 150000][$spouseOpt];
                    $spousePsaBand = $spouseInc > 125140 ? 0 : ($spouseInc > 50270 ? 500 : 1000);
                    expect(itemOn($r, 'spouse_psa'))->toBe($has('savings', 'bank') && $spousePsaBand > 0, "spouse PSA gating wrong [$label]");
                    expect(itemOn($r, 'spouse_dividend'))->toBe($has('investments'), "spouse dividend gating wrong [$label]");
                    expect(itemOn($r, 'spouse_cgt'))->toBe($has('investments', 'property'), "spouse CGT gating wrong [$label]");
                } else {
                    expect(itemOn($r, 'marriage_allowance'))->toBeNull("MA shown for single [$label]");
                    expect(itemOn($r, 'spouse_pa'))->toBeNull("spouse PA shown for single [$label]");
                    expect(itemOn($r, 'spouse_pension_aa'))->toBeNull("spouse AA shown for single [$label]");
                    expect(itemOn($r, 'spouse_starting_rate'))->toBeNull("spouse starting-rate shown for single [$label]");
                    expect(itemOn($r, 'spouse_psa'))->toBeNull("spouse PSA shown for single [$label]");
                    expect(itemOn($r, 'spouse_dividend'))->toBeNull("spouse dividend shown for single [$label]");
                    expect(itemOn($r, 'spouse_cgt'))->toBeNull("spouse CGT shown for single [$label]");
                }

                // The standalone 60% Tax Trap allowance card is removed in every
                // band; the trap is folded into the (tapered) Personal Allowance.
                expect(itemOn($r, 'tax_trap_60'))->toBeNull("trap allowance row must not exist [$label]");
                if ($income > 100000) {
                    $paItem = collect($r['allowances']['items'])->firstWhere('key', 'personal_allowance');
                    expect(str_contains($paItem['label'], '(tapered)'))->toBeTrue("PA label must show tapered over £100k [$label]");
                }

                // --- Saving-line presence correctness ---
                // In the trap band the pension lever is surfaced as tax_trap_60.
                expect(hasSaving($r, 'pension'))->toBe(! $isTrap && ! $has('pension'), "pension saving presence wrong [$label]");
                expect(hasSaving($r, 'tax_trap_60'))->toBe($isTrap && ! $has('pension'), "trap saving presence wrong [$label]");
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
