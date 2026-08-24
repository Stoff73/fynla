<?php

declare(strict_types=1);

use App\Models\TaxConfiguration;
use App\Services\TaxConfigService;
use App\Services\UKTaxCalculator;
use Database\Seeders\TaxConfigurationSeeder;

/**
 * W-0174 — the Personal Allowance tapered to £0 but the basic-rate band stayed
 * £50,270 wide, so the whole withdrawn allowance was taxed at 20% instead of
 * 40%: a flat £2,514 under-charge for every user above £125,140, and a partial
 * one for anyone inside the taper zone.
 *
 * Runs against the real seeded configuration, both the detailed breakdown the
 * income page renders and the simple total, and finishes by MOVING the
 * configured band width in the database and requiring the answer to follow — a
 * suite that only ever sees £37,700 cannot tell a config read from a literal.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->calculator = app(UKTaxCalculator::class);
});

/**
 * The earned-income slice of the detailed breakdown, which is what the
 * "Estimated Tax and National Insurance" card renders band by band.
 */
function earnedBands(array $detailed): array
{
    return $detailed['income_breakdowns'][0]['tax_breakdown'];
}

describe('peak_earners — the figures the persona run measured', function () {
    it('charges David £52,663.50, not the £50,149 the page showed', function () {
        // £145,000 employment + £14,290 rental profit − £11,600 pension = £147,690
        // taxable, Personal Allowance fully tapered away.
        $detailed = $this->calculator->calculateDetailedNetIncome(
            employmentIncome: 145_000,
            rentalIncome: 14_290,
            pensionContributions: 11_600,
        );

        $bands = earnedBands($detailed);

        expect($detailed['income_breakdowns'][0]['taxable_income'])->toBe(147_690.0)
            ->and($bands['basic_rate']['taxable'])->toBe(37_700.0)
            ->and($bands['basic_rate']['tax'])->toBe(7_540.0)
            ->and($bands['higher_rate']['taxable'])->toBe(87_440.0)
            ->and($bands['higher_rate']['tax'])->toBe(34_976.0)
            ->and($bands['additional_rate']['taxable'])->toBe(22_550.0)
            ->and($bands['additional_rate']['tax'])->toBe(10_147.5)
            ->and($detailed['summary']['total_income_tax_before_credits'])->toBe(52_663.5);
    });

    it('charges Sarah £44,199, not the £41,685 the page showed', function () {
        $detailed = $this->calculator->calculateDetailedNetIncome(
            employmentIncome: 120_000,
            rentalIncome: 8_880,
        );

        $bands = earnedBands($detailed);

        expect($bands['basic_rate']['taxable'])->toBe(37_700.0)
            ->and($bands['higher_rate']['taxable'])->toBe(87_440.0)
            ->and($bands['additional_rate']['taxable'])->toBe(3_740.0)
            ->and($detailed['summary']['total_income_tax_before_credits'])->toBe(44_199.0);
    });

    it('leaves National Insurance and the Section 24 credit alone', function () {
        $detailed = $this->calculator->calculateDetailedNetIncome(
            employmentIncome: 145_000,
            rentalIncome: 14_290,
            pensionContributions: 11_600,
            section24Credit: 780,
        );

        expect($detailed['summary']['total_national_insurance'])->toBe(4_910.6)
            ->and($detailed['summary']['section_24_credit'])->toBe(780.0)
            ->and($detailed['summary']['total_income_tax'])->toBe(51_883.5);
    });
});

describe('three points across the taper', function () {
    it('keeps the full allowance and the £50,270 threshold below £100,000', function () {
        $bands = earnedBands($this->calculator->calculateDetailedNetIncome(employmentIncome: 99_000));

        // £99,000 − £12,570 = £86,430 taxable: £37,700 at 20%, £48,730 at 40%.
        expect($bands['basic_rate']['taxable'])->toBe(37_700.0)
            ->and($bands['higher_rate']['taxable'])->toBe(48_730.0)
            ->and((float) $bands['additional_rate']['taxable'])->toBe(0.0)
            ->and($this->calculator->calculateNetIncome(employmentIncome: 99_000)['income_tax'])->toBe(27_032.0);
    });

    it('narrows the threshold in step with a partly tapered allowance', function () {
        // £110,000: allowance £7,570, so the higher rate starts at £45,270 of
        // income — not £50,270. This is the case a fully-tapered-only fix misses.
        $bands = earnedBands($this->calculator->calculateDetailedNetIncome(employmentIncome: 110_000));

        expect($bands['basic_rate']['taxable'])->toBe(37_700.0)
            ->and($bands['higher_rate']['taxable'])->toBe(64_730.0)
            ->and($this->calculator->calculateNetIncome(employmentIncome: 110_000)['income_tax'])->toBe(33_432.0);
    });

    it('gives a £37,700 band and no allowance at £125,140', function () {
        $bands = earnedBands($this->calculator->calculateDetailedNetIncome(employmentIncome: 125_140));

        expect((float) $bands['personal_allowance_used'])->toBe(0.0)
            ->and($bands['basic_rate']['taxable'])->toBe(37_700.0)
            ->and($bands['higher_rate']['taxable'])->toBe(87_440.0)
            ->and((float) $bands['additional_rate']['taxable'])->toBe(0.0)
            ->and($this->calculator->calculateNetIncome(employmentIncome: 125_140)['income_tax'])->toBe(42_516.0);
    });

    it('agrees between the detailed breakdown and the simple total at every point', function () {
        foreach ([45_000, 99_000, 110_000, 125_140, 159_290] as $income) {
            $detailed = $this->calculator->calculateDetailedNetIncome(employmentIncome: $income);
            $simple = $this->calculator->calculateNetIncome(employmentIncome: $income);

            expect($detailed['summary']['total_income_tax'])->toBe($simple['income_tax']);
        }
    });
});

describe('the answer follows the configuration, not a literal', function () {
    /**
     * Rewrites the active configuration and clears the request-scoped cache, so
     * the calculator is re-resolved against the changed values.
     */
    function withIncomeTaxConfig(callable $mutate): UKTaxCalculator
    {
        $active = TaxConfiguration::where('is_active', true)->firstOrFail();
        $config = $active->config_data;
        $config['income_tax'] = $mutate($config['income_tax']);
        $active->update(['config_data' => $config]);

        app(TaxConfigService::class)->clearCache();

        return app(UKTaxCalculator::class);
    }

    it('moves with a changed basic-rate band width', function () {
        $calculator = withIncomeTaxConfig(function (array $incomeTax) {
            $incomeTax['bands'][0]['max'] = 40_000;

            return $incomeTax;
        });

        // Allowance fully tapered, so the whole 20% slice is the configured width.
        $bands = earnedBands($calculator->calculateDetailedNetIncome(employmentIncome: 130_000));

        expect($bands['basic_rate']['taxable'])->toBe(40_000.0)
            ->and($bands['higher_rate']['taxable'])->toBe(85_140.0);
    });

    it('moves with a changed taper threshold', function () {
        $calculator = withIncomeTaxConfig(function (array $incomeTax) {
            $incomeTax['personal_allowance_taper_threshold'] = 120_000;

            return $incomeTax;
        });

        // £110,000 is now below the threshold, so the allowance survives whole
        // and the higher rate starts at £50,270 again.
        $bands = earnedBands($calculator->calculateDetailedNetIncome(employmentIncome: 110_000));

        expect($bands['personal_allowance_used'])->toBe(12_570.0)
            ->and($bands['basic_rate']['taxable'])->toBe(37_700.0)
            ->and($bands['higher_rate']['taxable'])->toBe(59_730.0);
    });

    it('moves with a changed taper rate', function () {
        $calculator = withIncomeTaxConfig(function (array $incomeTax) {
            $incomeTax['personal_allowance_taper_rate'] = 0.25;

            return $incomeTax;
        });

        // £110,000 is £10,000 over: £1 in £4 withdraws £2,500, leaving £10,070.
        $bands = earnedBands($calculator->calculateDetailedNetIncome(employmentIncome: 110_000));

        expect($bands['personal_allowance_used'])->toBe(10_070.0)
            ->and($bands['basic_rate']['taxable'])->toBe(37_700.0);
    });

    it('moves with a changed additional-rate threshold', function () {
        $calculator = withIncomeTaxConfig(function (array $incomeTax) {
            $incomeTax['additional_rate_threshold'] = 150_000;

            return $incomeTax;
        });

        // The allowance still goes at £125,140 (threshold + full allowance × 2),
        // so the 40% band runs from £37,700 to £150,000 — £112,300 wide.
        $bands = earnedBands($calculator->calculateDetailedNetIncome(employmentIncome: 159_290));

        expect($bands['basic_rate']['taxable'])->toBe(37_700.0)
            ->and($bands['higher_rate']['taxable'])->toBe(112_300.0)
            ->and($bands['additional_rate']['taxable'])->toBe(9_290.0);
    });

    it('moves with a changed basic rate', function () {
        $calculator = withIncomeTaxConfig(function (array $incomeTax) {
            $incomeTax['bands'][0]['rate'] = 0.10;

            return $incomeTax;
        });

        $bands = earnedBands($calculator->calculateDetailedNetIncome(employmentIncome: 130_000));

        expect($bands['basic_rate']['tax'])->toBe(3_770.0);
    });
});
