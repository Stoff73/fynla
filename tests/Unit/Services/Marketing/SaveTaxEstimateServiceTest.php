<?php

declare(strict_types=1);

use App\Models\TaxConfiguration;
use App\Services\Marketing\SaveTaxEstimateService;
use App\Services\TaxConfigService;
use Database\Seeders\SavingsMarketRatesSeeder;
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

    // Basic: a tenth of £50,270, rounded down to £5,000 (the engine never
    // rounds up), at 20% = £1,000. Higher: £10,000 at 40% = £4,000.
    // Additional (£150,000 assumed): the engine's additional-rate sizing fills
    // the £60,000 Annual Allowance. Tax on £150,000 (no allowance) is
    // £7,540 + £34,976 + £11,187 = £53,703; on £90,000 (allowance back) it is
    // £7,540 + £15,892 = £23,432; the contribution saves £30,271.
    expect(lineAmount($this->service->estimate(['income' => 'upto_50270', 'assets' => $assets]), 'pension'))->toBe(1000)
        ->and(lineAmount($this->service->estimate(['income' => '50271_100000', 'assets' => $assets]), 'pension'))->toBe(4000)
        ->and(lineAmount($this->service->estimate(['income' => 'over_125140', 'assets' => $assets]), 'pension'))->toBe(30271);
});

it('computes the exact 60% trap relief for the £100k-£125,140 band', function () {
    // £125,140 income; the engine rounds the contribution down to £25,100.
    // Tax on £125,140 (no allowance): £7,540 + £34,976 = £42,516. On £100,040
    // (allowance £12,550, taxable £87,490): £7,540 + £19,916 = £27,456.
    // Saving £15,060.
    $result = $this->service->estimate(['income' => '100001_125140', 'assets' => ['savings']]);

    // Surfaced as a distinct "60% Tax Trap" line (not the generic pension line).
    expect(lineAmount($result, 'tax_trap_60'))->toBe(15060)
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
        ->and($pa['state'])->toBe('available')
        ->and($pa['label'])->toContain('(tapered)')
        ->and($pa['amount'])->toBe(0) // £125,140 → Personal Allowance fully tapered
        ->and(hasSaving($trap, 'tax_trap_60'))->toBeTrue();
});

it('keeps a fully tapered Personal Allowance actionable at the exact taper boundary', function () {
    $result = $this->service->estimate([
        'income' => '100001_125140',
        'assets' => ['savings'],
    ]);
    $allowance = collect($result['allowances']['items'])
        ->firstWhere('key', 'personal_allowance');

    expect($allowance['amount'])->toBe(0)
        ->and($allowance['state'])->toBe('available')
        ->and($allowance['on'])->toBeTrue()
        ->and($allowance['explanation'])->toContain('pension contribution may restore');
});

it('marks the Personal Allowance as automatic below the taper and actionable at the taper boundary', function () {
    // A working earner's Personal Allowance is used automatically by their
    // salary — greyed.
    expect(itemOn($this->service->estimate(['income' => 'upto_50270', 'assets' => []]), 'personal_allowance'))->toBeFalse()
        ->and(itemOn($this->service->estimate(['income' => '50271_100000', 'assets' => []]), 'personal_allowance'))->toBeFalse()
        ->and(itemOn($this->service->estimate(['income' => 'over_125140', 'assets' => []]), 'personal_allowance'))->toBeFalse()
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

it('gives every allowance a truthful semantic state and explanation', function () {
    $ordinaryWorker = $this->service->estimate([
        'income' => '50271_100000',
        'assets' => [],
    ]);

    foreach ($ordinaryWorker['allowances']['items'] as $item) {
        expect($item)->toHaveKeys(['state', 'label', 'amount', 'explanation'])
            ->and($item['state'])->toBeIn(['available', 'used_automatically', 'not_applicable'])
            ->and(trim($item['explanation']))->not->toBe('');
    }

    expect(itemState($ordinaryWorker, 'personal_allowance'))->toBe('used_automatically')
        ->and(itemState($ordinaryWorker, 'pension_aa'))->toBe('available')
        ->and(itemState($ordinaryWorker, 'psa'))->toBe('not_applicable')
        ->and(itemState($ordinaryWorker, 'dividend'))->toBe('not_applicable')
        ->and(itemState($ordinaryWorker, 'cgt'))->toBe('not_applicable');

    $pension = collect($ordinaryWorker['allowances']['items'])->firstWhere('key', 'pension_aa');
    expect($pension['explanation'])
        ->toContain('open or contribute to a pension')
        ->toContain('earnings')
        ->toContain('allowance rules')
        ->toContain('personal circumstances');
});

it('shows a non-earning spouse the Pension Annual Allowance and explains the contribution limit', function () {
    $r = $this->service->estimate(['income' => '50271_100000', 'spouse' => 'yes', 'spouseIncome' => 'zero', 'assets' => []]);

    $spouseAa = collect($r['allowances']['items'])->firstWhere('key', 'spouse_pension_aa');
    expect($spouseAa['on'])->toBeTrue()
        ->and($spouseAa['amount'])->toBe(60000)
        ->and($spouseAa['note'])->toContain('£3,600')
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

it('keeps the pension line when the user already has a pension (CSJ 2026-09-26)', function () {
    // Holding a pension says nothing about unused Annual Allowance; a pension
    // holder at £50,271–£100,000 was shown "up to £0".
    $with = $this->service->estimate(['income' => '50271_100000', 'assets' => ['pension']]);
    $without = $this->service->estimate(['income' => '50271_100000', 'assets' => ['savings']]);

    expect(lineAmount($with, 'pension'))->toBe(lineAmount($without, 'pension'))
        ->and(lineAmount($with, 'pension'))->toBeGreaterThan(0)
        ->and($with['savings_total'])->toBeGreaterThan(0);
});

it('prices the ISA line on interest and never counts automatic allowances as savings (CSJ 2026-09-27)', function () {
    $result = $this->service->estimate([
        'income' => '50271_100000',
        'assets' => ['savings', 'investments'],
    ]);

    // £10,000 at the easy-access benchmark earns less than the £500 higher-rate
    // Personal Savings Allowance, so an ISA saves no tax and shows no line.
    expect(lineAmount($result, 'isa'))->toBe(0)
        ->and(lineAmount($result, 'psa'))->toBe(0)
        ->and(lineAmount($result, 'dividend'))->toBe(0)
        ->and(lineAmount($result, 'cgt'))->toBe(0);
});

it('drops the Personal Savings Allowance saving at additional rate', function () {
    $result = $this->service->estimate(['income' => 'over_125140', 'assets' => ['savings']]);

    expect(lineAmount($result, 'psa'))->toBe(0);
});

it('never prices a spouse allowance as if salary could be moved into it (F1, L3-1)', function () {
    // Salary is taxed on the employee (ITEPA 2003 s62) and the plan engine
    // never produces these figures, so the funnel must not promise them.
    foreach (['upto_50270', '50271_100000', '100001_125140'] as $band) {
        $result = $this->service->estimate([
            'income' => $band,
            'spouse' => 'yes',
            'spouseIncome' => 'zero',
            'assets' => ['savings', 'bank'],
        ]);

        expect(lineAmount($result, 'spouse_pa'))->toBe(0, $band)
            ->and(lineAmount($result, 'spouse_psa'))->toBe(0, $band)
            ->and(lineAmount($result, 'spouse_starting_rate'))->toBe(0, $band);
    }

    // The £110k-couple case from the 29 Sep run: £125,140 at the band top,
    // non-earning spouse. Only the trap line and the ISA line remain.
    $trapCouple = $this->service->estimate([
        'income' => '100001_125140', 'spouse' => 'yes', 'spouseIncome' => 'zero', 'assets' => [],
    ]);
    expect($trapCouple['savings_total'])->toBe(15060)
        ->and($trapCouple['partner_savings_total'])->toBe(0);
});

it('caps a retired or not-employed user at the non-earner pension limit (F2)', function () {
    // FA 2004 s190: relief on the greater of relevant earnings and £3,600.
    // A pension or rent is not relevant earnings. £2,880 net, £720 relief.
    foreach (['retired', 'not-employed'] as $employment) {
        $result = $this->service->estimate(['employment' => $employment, 'income' => 'upto_50270', 'assets' => []]);
        expect(lineAmount($result, 'pension'))->toBe(720, $employment);
    }

    // No income at all: basic-rate relief is still added at source (s192).
    expect(lineAmount($this->service->estimate(['income' => 'zero', 'assets' => []]), 'pension'))->toBe(720);

    // A higher-rate retiree gets higher-rate relief on the £3,600 too:
    // £3,600 at 40% = £1,440.
    expect(lineAmount($this->service->estimate(['employment' => 'retired', 'income' => '50271_100000', 'assets' => []]), 'pension'))->toBe(1440);

    // Part-time and self-employed income is earnings: no cap.
    foreach (['part-time', 'self-employed', 'full-time'] as $employment) {
        $result = $this->service->estimate(['employment' => $employment, 'income' => 'upto_50270', 'assets' => []]);
        expect(lineAmount($result, 'pension'))->toBe(1000, $employment);
    }
});

it("prices a partner's 60% tax trap on their own card (F3)", function () {
    $result = $this->service->estimate([
        'employment' => 'not-employed',
        'income' => 'zero',
        'spouse' => 'yes',
        'spouseIncome' => '100001_125140',
        'assets' => [],
    ]);

    // Same arithmetic as the user's own trap line: £25,100 saves £15,060.
    expect(lineAmount($result, 'spouse_tax_trap_60'))->toBe(15060)
        ->and($result['partner_savings_total'])->toBe(15060)
        ->and(lineAmount($result, 'pension'))->toBe(720)
        ->and($result['savings_total'])->toBe(15780);
});

// Tax review of #975, F10: relief is limited to the greater of relevant UK
// earnings and the basic amount (FA 2004 s190), so a partner whose £125,140 is
// a pension or rent is priced at the basic amount, as the user is.
it('caps a partner without earnings at the non-earner pension limit (F10)', function () {
    $partner = fn (?string $spouseEmployment): array => $this->service->estimate([
        'employment' => 'not-employed',
        'income' => 'zero',
        'spouse' => 'yes',
        'spouseIncome' => '100001_125140',
        'spouseEmployment' => $spouseEmployment,
        'assets' => [],
    ]);
    $reason = fn (array $result): string => collect($result['savings'])->firstWhere('key', 'spouse_tax_trap_60')['reason'];

    // £3,600 gross wins back £1,800 of allowance and moves £3,600 out of the
    // higher band: 60% of £3,600 = £2,160.
    foreach (['retired', 'not-employed'] as $employment) {
        $result = $partner($employment);
        expect(lineAmount($result, 'spouse_tax_trap_60'))->toBe(2160, $employment)
            ->and($result['partner_savings_total'])->toBe(2160)
            ->and($reason($result))->toContain('Without earnings from work')
            ->and($reason($result))->toContain('£3,600')
            ->and($reason($result))->toContain('£2,880')
            // £720 is added at source; the other £1,440 is claimed (s192(4)).
            ->and($reason($result))->toContain('The other £1,440 they claim back through Self Assessment.');
    }

    // A working partner is priced as their own plan prices them, and the
    // reason no longer hedges on where the income comes from.
    foreach (['full-time', 'part-time', 'self-employed'] as $employment) {
        $result = $partner($employment);
        expect(lineAmount($result, 'spouse_tax_trap_60'))->toBe(15060, $employment)
            ->and($reason($result))->not->toContain('if that income is from work');
    }

    // Unasked (a client from before the question): the stated assumption stays.
    expect(lineAmount($partner(null), 'spouse_tax_trap_60'))->toBe(15060)
        ->and($reason($partner(null)))->toContain('if that income is from work');
});

it('says which income the headline assumes', function () {
    $banded = $this->service->estimate(['income' => '100001_125140', 'assets' => []]);
    $open = $this->service->estimate(['income' => 'over_125140', 'assets' => []]);
    $none = $this->service->estimate(['income' => 'zero', 'assets' => []]);

    expect($banded['assumed_income'])->toBe(125140)
        ->and($banded['assumed_income_basis'])->toBe('band_top')
        // The top band has no top: its figure is the configured example.
        ->and($open['assumed_income'])->toBe((int) config('onboarding.savetax_over_band_assumed_income'))
        ->and($open['assumed_income_basis'])->toBe('example')
        ->and($none['assumed_income'])->toBe(0)
        ->and($none['assumed_income_basis'])->toBe('none');
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

    // The other way round: the user has no income, the partner pays basic rate.
    $reverse = $this->service->estimate(['income' => 'zero', 'spouse' => 'yes', 'spouseIncome' => 'upto_50270', 'assets' => []]);
    expect(lineAmount($reverse, 'marriage_allowance'))->toBe(252)
        ->and(itemOn($reverse, 'marriage_allowance'))->toBeTrue();

    // Neither pays tax: nothing to transfer to.
    $neither = $this->service->estimate(['income' => 'zero', 'spouse' => 'yes', 'spouseIncome' => 'zero', 'assets' => []]);
    expect(lineAmount($neither, 'marriage_allowance'))->toBe(0)
        ->and(itemOn($neither, 'marriage_allowance'))->toBeFalse();
});

it('warns a Marriage Allowance recipient above the Scottish limit, as the engine how-to does', function () {
    // The funnel prices rest-of-UK rates, as the plan engine does. A Scottish
    // recipient may pay no more than the Scottish intermediate rate (ITA 2007
    // s55B(2)(b)); income_tax.marriage_allowance.scottish_recipient_upper_limit
    // is £43,662 (gov.uk/marriage-allowance/eligibility).
    $reasonOf = fn (array $result): string => collect($result['savings'])->firstWhere('key', 'marriage_allowance')['reason'] ?? '';

    // £50,270 recipient (the user), above £43,662.
    $user = $this->service->estimate(['income' => 'upto_50270', 'spouse' => 'yes', 'spouseIncome' => 'zero', 'assets' => []]);
    expect($reasonOf($user))->toContain('If you live in Scotland, this does not apply to you')
        ->and($reasonOf($user))->toContain('Scottish intermediate rate, which usually means income up to £43,662.')
        ->and(lineAmount($user, 'marriage_allowance'))->toBe(252);

    // £50,270 recipient (the partner).
    $spouse = $this->service->estimate(['income' => 'zero', 'spouse' => 'yes', 'spouseIncome' => 'upto_50270', 'assets' => []]);
    expect($reasonOf($spouse))->toContain('If your partner lives in Scotland, this does not apply')
        ->and($reasonOf($spouse))->toContain('£43,662');
});

it('reads the Scottish Marriage Allowance limit from tax config, and says nothing at or below it', function () {
    $configuration = TaxConfiguration::where('is_active', true)->firstOrFail();
    $data = $configuration->config_data;
    $data['income_tax']['marriage_allowance']['scottish_recipient_upper_limit'] = 50270;
    $configuration->update(['config_data' => $data]);
    app()->forgetInstance(TaxConfigService::class);
    app()->forgetInstance(SaveTaxEstimateService::class);

    $atLimit = app(SaveTaxEstimateService::class)->estimate(['income' => 'upto_50270', 'spouse' => 'yes', 'spouseIncome' => 'zero', 'assets' => []]);
    $reason = collect($atLimit['savings'])->firstWhere('key', 'marriage_allowance')['reason'];
    expect($reason)->not->toContain('Scotland');

    $data['income_tax']['marriage_allowance']['scottish_recipient_upper_limit'] = 40000;
    $configuration->update(['config_data' => $data]);
    app()->forgetInstance(TaxConfigService::class);
    app()->forgetInstance(SaveTaxEstimateService::class);

    $moved = app(SaveTaxEstimateService::class)->estimate(['income' => 'upto_50270', 'spouse' => 'yes', 'spouseIncome' => 'zero', 'assets' => []]);
    expect(collect($moved['savings'])->firstWhere('key', 'marriage_allowance')['reason'])->toContain('income up to £40,000.');
});

it('derives Marriage Allowance tax saving from the configured basic rate', function () {
    $configuration = TaxConfiguration::where('is_active', true)->firstOrFail();
    $data = $configuration->config_data;
    foreach ($data['income_tax']['bands'] as &$band) {
        if (str_contains($band['name'], 'Basic')) {
            $band['rate'] = 0.19;
        }
    }
    unset($band);
    $configuration->update(['config_data' => $data]);
    app()->forgetInstance(TaxConfigService::class);
    app()->forgetInstance(SaveTaxEstimateService::class);

    $result = app(SaveTaxEstimateService::class)->estimate([
        'income' => 'upto_50270',
        'spouse' => 'yes',
        'spouseIncome' => 'zero',
        'assets' => [],
    ]);

    expect(lineAmount($result, 'marriage_allowance'))->toBe(239);
});

it('does not add spouse levers when there is no spouse', function () {
    $result = $this->service->estimate(['income' => '50271_100000', 'assets' => []]);

    expect(lineAmount($result, 'spouse_pa'))->toBe(0)
        ->and(lineAmount($result, 'marriage_allowance'))->toBe(0);
});

it('does not assume a non-earning spouse when the spouse income answer is missing', function () {
    $result = $this->service->estimate([
        'income' => '50271_100000',
        'spouse' => 'yes',
        'assets' => ['savings'],
    ]);

    expect(collect($result['savings'])->pluck('key')->filter(
        fn (string $key): bool => str_starts_with($key, 'spouse_') || $key === 'marriage_allowance'
    )->all())->toBe([])
        ->and(collect($result['allowances']['items'])->pluck('key')->filter(
            fn (string $key): bool => str_starts_with($key, 'spouse_') || $key === 'marriage_allowance'
        )->all())->toBe([]);
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
    // 5,000 + spouse PSA 1,000 + spouse ISA 20,000 + spouse AA 60,000
    // + spouse dividend 500 + spouse CGT 3,000 = 186,070.
    // Marriage Allowance NOT eligible (higher-rate primary).
    expect($married['allowances']['total'])->toBe(186070);
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

it('derives funnel band assumptions from the active tax configuration', function () {
    $configuration = TaxConfiguration::where('is_active', true)->firstOrFail();
    $data = $configuration->config_data;
    $data['tax_year'] = '2030/31';
    $data['income_tax']['higher_rate_threshold'] = 60000;
    $data['income_tax']['personal_allowance_taper_threshold'] = 110000;
    $data['income_tax']['additional_rate_threshold'] = 140000;
    $configuration->update(['config_data' => $data]);
    config()->set('onboarding.savetax_over_band_assumed_income', 165000);
    app()->forgetInstance(TaxConfigService::class);
    app()->forgetInstance(SaveTaxEstimateService::class);
    $service = app(SaveTaxEstimateService::class);

    expect($service->estimate(['income' => 'upto_50270', 'assets' => []])['assumed_income'])->toBe(60000)
        ->and($service->estimate(['income' => '50271_100000', 'assets' => []])['assumed_income'])->toBe(110000)
        ->and($service->estimate(['income' => '100001_125140', 'assets' => []])['assumed_income'])->toBe(135140)
        ->and($service->estimate(['income' => 'over_125140', 'assets' => []])['assumed_income'])->toBe(165000)
        ->and($service->estimate(['income' => 'upto_50270', 'assets' => []])['tax_year'])->toBe('2030/31');
});

it('fails closed when a required tax value is missing', function () {
    $configuration = TaxConfiguration::where('is_active', true)->firstOrFail();
    $data = $configuration->config_data;
    unset($data['dividend_tax']['allowance']);
    $configuration->update(['config_data' => $data]);
    app()->forgetInstance(TaxConfigService::class);
    app()->forgetInstance(SaveTaxEstimateService::class);

    expect(fn () => app(SaveTaxEstimateService::class)->estimate([
        'income' => '50271_100000',
        'assets' => ['investments'],
    ]))->toThrow(LogicException::class, 'dividend_tax.allowance');
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

function itemState(array $result, string $key): ?string
{
    foreach ($result['allowances']['items'] as $item) {
        if ($item['key'] === $key) {
            return $item['state'] ?? null;
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
        'zero' => 0,
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
                // Personal Allowance: greyed for a working earner (auto-used).
                // The funnel's taper band maps to the exact upper boundary,
                // where pension action can restore the currently-zero amount.
                expect(itemOn($r, 'personal_allowance'))->toBe($isTrap || $income === 0, "PA gating wrong [$label]");
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
                $pays = fn (int $amount): bool => $amount > 12570 && $amount <= 50270;
                $spouseIncome = ['zero' => 0, 'upto_50270' => 50270, '50271_100000' => 100000, '100001_125140' => 125140, 'over_125140' => 150000][$spouseOpt ?? 'zero'];
                $marriageEligible = $married && (($spouseOpt === 'zero' && $pays($income)) || ($income === 0 && $pays($spouseIncome)));
                if ($married) {
                    expect(itemOn($r, 'marriage_allowance'))->toBe($marriageEligible, "MA eligibility wrong [$label]");
                    // Spouse Personal Allowance: shown for a non-earner and at
                    // the exact upper taper boundary, where pension action can
                    // restore allowance even though the current amount is zero.
                    $spousePaClaimable = in_array($spouseOpt, ['zero', '100001_125140'], true);
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
                // Whether or not a pension is held (CSJ 2026-09-26).
                expect(hasSaving($r, 'pension'))->toBe(! $isTrap, "pension saving presence wrong [$label]");
                expect(hasSaving($r, 'tax_trap_60'))->toBe($isTrap, "trap saving presence wrong [$label]");
                // No answer ever promises "up to £0".
                expect($r['savings_total'])->toBeGreaterThan(0, "zero headline saving [$label]");
                if (hasSaving($r, 'isa')) {
                    expect($hasFinancial)->toBeTrue("ISA saving without savings [$label]");
                }
                expect(hasSaving($r, 'psa'))->toBeFalse("PSA is automatic, not a saving [$label]");
                expect(hasSaving($r, 'dividend'))->toBeFalse("dividend allowance is automatic, not a saving [$label]");
                expect(hasSaving($r, 'cgt'))->toBeFalse("CGT allowance is automatic, not a saving [$label]");

                // Salary cannot be moved to a spouse: never priced (F1).
                expect(hasSaving($r, 'spouse_pa'))->toBeFalse("spouse_pa saving must not exist [$label]");
                expect(hasSaving($r, 'spouse_psa'))->toBeFalse("spouse_psa saving must not exist [$label]");
                expect(hasSaving($r, 'spouse_starting_rate'))->toBeFalse("spouse_starting_rate saving must not exist [$label]");
                expect(hasSaving($r, 'spouse_tax_trap_60'))->toBe($spouseOpt === '100001_125140', "spouse trap presence wrong [$label]");
                expect(hasSaving($r, 'marriage_allowance'))->toBe($marriageEligible, "MA saving presence wrong [$label]");
            }
        }
    }

    expect($combos)->toBe(5 * 6 * 64); // 1,920 combinations exercised
});

// Azlan, 2026-09-18: the landing page shows how many allowances are available,
// not a sum of amounts with different tax meanings.
it('counts the allowances available beside the total', function () {
    $result = app(SaveTaxEstimateService::class)->estimate(['income' => '50271_100000', 'spouse' => 'yes', 'spouseIncome' => 'basic', 'assets' => ['savings', 'pension', 'isa']]);

    expect($result['allowances']['count'])->toBe(count($result['allowances']['items']))
        ->and($result['allowances']['available_count'])->toBe(collect($result['allowances']['items'])->where('state', 'available')->count())
        ->and($result['allowances']['available_count'])->toBeGreaterThan(0);
});

it('sizes the ISA line from the named savings-share assumption, not a literal', function () {
    $this->seed(SavingsMarketRatesSeeder::class);
    $answers = ['income' => 'over_125140', 'assets' => ['savings']];
    $income = (int) config('onboarding.savetax_over_band_assumed_income');
    $isaReason = function (array $result): ?string {
        foreach ($result['savings'] as $line) {
            if ($line['key'] === 'isa') {
                return $line['reason'];
            }
        }

        return null;
    };

    // The shipped assumption: savings worth 10% of income (figure unchanged).
    expect(config('onboarding.savetax_isa_assumed_savings_share'))->toBe(0.10)
        ->and($isaReason($this->service->estimate($answers)))
        ->toContain('£'.number_format((int) round($income * 0.10)).' of savings');

    config()->set('onboarding.savetax_isa_assumed_savings_share', 0.20);

    expect($isaReason($this->service->estimate($answers)))
        ->toContain('£'.number_format((int) round($income * 0.20)).' of savings');
});

it('fails closed when the ISA savings-share assumption is not a share', function () {
    config()->set('onboarding.savetax_isa_assumed_savings_share', null);

    expect(fn () => $this->service->estimate(['income' => '50271_100000', 'assets' => ['savings']]))
        ->toThrow(LogicException::class);
});
