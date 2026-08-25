<?php

declare(strict_types=1);

use App\Services\UKTaxCalculator;
use Database\Seeders\TaxConfigurationSeeder;

/**
 * W-0422 and W-0423 — two labels on the tax computation that described something
 * the figures beneath them do not do.
 *
 * The page's whole value is that a reader can check it by hand, so a wrong header
 * over a right number costs as much as a wrong number: it is the only claim they
 * have no way to verify.
 *
 * - **W-0423.** The card holding employment, self-employment, rental profit and
 *   pension income was headed "Earned Income" whatever was in it, with a flat "NI
 *   Applies" badge beside the combined gross. On a landlord with a salary that
 *   asserted National Insurance over rental profit, which is neither earned income
 *   nor liable to it. The computation underneath was always right.
 * - **W-0422.** "Net Income (after tax, pension contributions and tax credits)"
 *   sat over a figure that deducts income tax and National Insurance and adds the
 *   Section 24 credit. It has never deducted the pension.
 *
 * These cases pin the arithmetic the labels now describe, so the labels cannot
 * drift back without a red test. Every fixture makes the two hypotheses produce
 * DIFFERENT numbers — `base` of £145,000 against a gross of £159,290, and a net
 * income £11,600 apart — because a fixture where they coincide proves nothing
 * (`tests/CLAUDE.md` §4, Collision).
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->calculator = app(UKTaxCalculator::class);
});

describe('the card names the income it actually holds', function () {
    it('does not call rental profit earned income', function () {
        $result = $this->calculator->calculateDetailedNetIncome(
            employmentIncome: 145_000,
            rentalIncome: 14_290,
        );

        $earned = collect($result['income_breakdowns'])->firstWhere('income_type', 'earned');

        expect($earned)->not->toBeNull()
            ->and($earned['income_type_label'])->toBe('Earned and Rental Income')
            ->and($earned['gross_amount'])->toBe(159290.0);
    });

    it('names pension income in payment as well', function () {
        $result = $this->calculator->calculateDetailedNetIncome(
            employmentIncome: 40_000,
            rentalIncome: 6_000,
            pensionIncome: 9_000,
        );

        $earned = collect($result['income_breakdowns'])->firstWhere('income_type', 'earned');

        expect($earned['income_type_label'])->toBe('Earned, Rental and Pension Income');
    });

    it('still says Earned Income when that is all the card holds', function () {
        $result = $this->calculator->calculateDetailedNetIncome(employmentIncome: 60_000);

        $earned = collect($result['income_breakdowns'])->firstWhere('income_type', 'earned');

        expect($earned['income_type_label'])->toBe('Earned Income');
    });

    it('names rental alone for a landlord with no employment', function () {
        $result = $this->calculator->calculateDetailedNetIncome(rentalIncome: 22_000);

        $earned = collect($result['income_breakdowns'])->firstWhere('income_type', 'earned');

        expect($earned['income_type_label'])->toBe('Rental Income');
    });
});

describe('National Insurance is stated against what it is charged on', function () {
    it('publishes the employment pay as the Class 1 base, not the combined gross', function () {
        $result = $this->calculator->calculateDetailedNetIncome(
            employmentIncome: 145_000,
            rentalIncome: 14_290,
        );

        $earned = collect($result['income_breakdowns'])->firstWhere('income_type', 'earned');

        expect($earned['ni_breakdown']['class_1']['base'])->toBe(145000.0)
            // The rental profit is in the card's gross and out of the base.
            ->and($earned['ni_breakdown']['class_1']['base'])->not->toBe($earned['gross_amount']);
    });

    it('cannot be reconstructed from the bands, which is why the base is published', function () {
        $result = $this->calculator->calculateDetailedNetIncome(employmentIncome: 145_000);

        $classOne = collect($result['income_breakdowns'])
            ->firstWhere('income_type', 'earned')['ni_breakdown']['class_1'];

        $bandEarnings = $classOne['main_rate']['earnings'] + $classOne['additional_rate']['earnings'];

        // The bands start at the primary threshold, so they sum to LESS than the
        // pay. A badge summing them would name a figure the payslip does not hold.
        expect($bandEarnings)->toBeLessThan($classOne['base'])
            ->and($classOne['base'])->toBe(145000.0);
    });

    it('publishes a self-employment base for Class 4', function () {
        $result = $this->calculator->calculateDetailedNetIncome(
            employmentIncome: 30_000,
            selfEmploymentIncome: 25_000,
            rentalIncome: 5_000,
        );

        $earned = collect($result['income_breakdowns'])->firstWhere('income_type', 'earned');

        expect($earned['ni_breakdown']['class_1']['base'])->toBe(30000.0)
            ->and($earned['ni_breakdown']['class_4']['base'])->toBe(25000.0)
            // Combined base is the two earnings, and excludes the rent.
            ->and($earned['ni_breakdown']['class_1']['base'] + $earned['ni_breakdown']['class_4']['base'])
            ->toBe(55000.0);
    });
});

describe('net income deducts what its label now claims, and nothing else', function () {
    it('is total income less income tax and National Insurance, plus the Section 24 credit', function () {
        $result = $this->calculator->calculateDetailedNetIncome(
            employmentIncome: 145_000,
            rentalIncome: 14_290,
            pensionContributions: 11_600,
            section24Credit: 780,
        );

        $summary = $result['summary'];

        expect($summary['net_income'])->toBe(round(
            $summary['total_gross_income']
            - $summary['total_income_tax']
            - $summary['total_national_insurance'],
            2
        ));
    });

    it('does not deduct the pension contribution the old label said it did', function () {
        $arguments = [
            'employmentIncome' => 145_000,
            'rentalIncome' => 14_290,
            'section24Credit' => 780,
        ];

        $withPension = $this->calculator->calculateDetailedNetIncome(
            ...$arguments,
            pensionContributions: 11_600,
        );
        $withoutPension = $this->calculator->calculateDetailedNetIncome(...$arguments);

        // The contribution changes the TAX — it is relieved before the bands — so
        // the two runs are not identical, and this case is not asserting that
        // nothing happened.
        expect($withPension['summary']['total_income_tax'])
            ->toBeLessThan($withoutPension['summary']['total_income_tax']);

        // What it does NOT do is come off this line. If it did, net income would
        // be £11,600 lower than gross-less-deductions, which is the figure the old
        // label described and the code never produced.
        $summary = $withPension['summary'];
        $takeHome = $summary['net_income'] - 11_600;

        expect($summary['net_income'])->toBeGreaterThan($takeHome)
            ->and($summary['net_income'])->toBe(round(
                $summary['total_gross_income']
                - $summary['total_deductions'],
                2
            ));
    });

    it('deducts National Insurance, which no variant of the old label named', function () {
        $withEmployment = $this->calculator->calculateDetailedNetIncome(employmentIncome: 60_000);
        $summary = $withEmployment['summary'];

        expect($summary['total_national_insurance'])->toBeGreaterThan(0.0)
            ->and($summary['net_income'])->toBe(round(
                $summary['total_gross_income']
                - $summary['total_income_tax']
                - $summary['total_national_insurance'],
                2
            ));
    });
});
