<?php

declare(strict_types=1);

namespace App\Services\Marketing;

use App\Services\TaxConfigService;

/**
 * PensionEstimateService — drives the projected-pot estimate on the
 * /pensioncheck funnel result page (pensioncheck-plan).
 *
 * Given the funnel answers (employment status, income band, age band, existing
 * pensions, pot size, spouse) it returns a conservative, banded, deterministic
 * projection that is marketing-grade — not a regulated financial forecast.
 *
 * Every tax threshold used in the tax-relief note comes from TaxConfigService
 * (Rule #2 — never hard-coded). Band midpoints for income, age, and pot are
 * conservative marketing midpoints, not tax values (see private consts below).
 *
 * Structure mirrors SaveTaxEstimateService: answers-array in, display-ready
 * array out; single class, no interfaces, no options objects.
 */
class PensionEstimateService
{
    /**
     * Age band key => midpoint age used in the projection.
     * These are marketing midpoints, not tax values.
     */
    private const AGE_MIDPOINTS = [
        'under_30' => 25,
        '30s' => 35,
        '40s' => 45,
        '50s' => 55,
        '60_plus' => 63,
    ];

    /**
     * Pot size band key => midpoint pot value (£).
     * Marketing midpoints only — not drawn from TaxConfigService.
     */
    private const POT_MIDPOINTS = [
        'none' => 0,
        'under_25k' => 12500,
        '25k_100k' => 62500,
        '100k_250k' => 175000,
        'over_250k' => 300000,
    ];

    /**
     * Income band key => midpoint income (£).
     * Marketing midpoints only. Tax-band thresholds (higher-rate, additional-rate)
     * are sourced from TaxConfigService where needed (Rule #2).
     */
    private const INCOME_MIDPOINTS = [
        'upto_50270' => 30000,
        '50271_100000' => 75000,
        '100001_125140' => 112500,
        'over_125140' => 150000,
    ];

    /**
     * Statutory auto-enrolment minimum total contribution (employer + employee).
     * Source: Workplace Pension Reform / The Pensions Act 2008; current rate 8%.
     */
    private const AUTO_ENROLMENT_TOTAL_PCT = 0.08;

    /**
     * Real-terms annual growth rate applied to the projected pot.
     * Conservative marketing assumption — not a guaranteed return.
     */
    private const REAL_GROWTH_RATE = 0.025;

    /**
     * Fallback State Pension age when TaxConfigService does not expose one.
     * The canonical value is sourced from pension.state_pension.future_spa
     * (seeded as 67 — rising from 66 between April 2026 and April 2028).
     * This const is used only if that key is absent from the database config.
     */
    private const DEFAULT_STATE_PENSION_AGE = 67;

    /** Default income band when the provided key is absent or unrecognised. */
    private const DEFAULT_INCOME_BAND = 'upto_50270';

    /** Default age band when the provided key is absent or unrecognised. */
    private const DEFAULT_AGE_BAND = 'under_30';

    /** Default pot band when the provided key is absent or unrecognised. */
    private const DEFAULT_POT_BAND = 'none';

    public function __construct(private readonly TaxConfigService $taxConfig) {}

    /**
     * Produce a banded projected-pot estimate from funnel answers.
     *
     * @param  array{
     *     employment?: string,
     *     income?: string,
     *     age?: string,
     *     pensions?: array<int,string>,
     *     pot?: string,
     *     spouse?: string,
     * }  $answers
     * @return array{
     *     projected_pot: float,
     *     retirement_age: int,
     *     years_to_retirement: int,
     *     monthly_contribution_assumed: float,
     *     tax_relief_note: string,
     *     already_retired: bool,
     * }
     */
    public function estimate(array $answers): array
    {
        $employment = $answers['employment'] ?? 'full-time';
        $incomeBand = $this->normaliseKey($answers['income'] ?? null, self::INCOME_MIDPOINTS, self::DEFAULT_INCOME_BAND);
        $ageBand = $this->normaliseKey($answers['age'] ?? null, self::AGE_MIDPOINTS, self::DEFAULT_AGE_BAND);
        $potBand = $this->normaliseKey($answers['pot'] ?? null, self::POT_MIDPOINTS, self::DEFAULT_POT_BAND);

        $ageMidpoint = self::AGE_MIDPOINTS[$ageBand];
        $incomeMidpoint = (float) self::INCOME_MIDPOINTS[$incomeBand];
        $potMidpoint = (float) self::POT_MIDPOINTS[$potBand];
        $retirementAge = $this->retirementAge();
        $alreadyRetired = $employment === 'retired';

        // Non-contributors (retired or not-employed) make no further contributions.
        $isNonContributor = $alreadyRetired || $employment === 'not-employed';
        $monthlyContribution = $isNonContributor
            ? 0.0
            : round($incomeMidpoint * self::AUTO_ENROLMENT_TOTAL_PCT / 12, 2);

        // Retired users have no years to project; everyone else runs to State Pension age.
        $yearsToRetirement = $alreadyRetired ? 0 : max(0, $retirementAge - $ageMidpoint);

        $projectedPot = $this->projectPot($potMidpoint, $monthlyContribution, $yearsToRetirement);

        return [
            'projected_pot' => $projectedPot,
            'retirement_age' => $retirementAge,
            'years_to_retirement' => $yearsToRetirement,
            'monthly_contribution_assumed' => $monthlyContribution,
            'tax_relief_note' => $this->taxReliefNote($incomeBand, $incomeMidpoint),
            'already_retired' => $alreadyRetired,
        ];
    }

    /**
     * Compound the current pot and monthly contributions forward at REAL_GROWTH_RATE
     * for the given number of years. Returns whole pounds (rounded).
     *
     * Uses the standard future-value formula:
     *   FV = PV × (1 + r)^n  +  PMT × ((1 + r)^n − 1) / r
     * where r is the monthly equivalent of REAL_GROWTH_RATE and n is months.
     */
    private function projectPot(float $potMidpoint, float $monthlyContribution, int $yearsToRetirement): float
    {
        if ($yearsToRetirement <= 0) {
            return round($potMidpoint);
        }

        $months = $yearsToRetirement * 12;
        $monthlyRate = (1.0 + self::REAL_GROWTH_RATE) ** (1.0 / 12) - 1.0;
        $growthFactor = (1.0 + $monthlyRate) ** $months;

        $potFv = $potMidpoint * $growthFactor;
        $contribFv = $monthlyContribution > 0.0
            ? $monthlyContribution * ($growthFactor - 1.0) / $monthlyRate
            : 0.0;

        return round($potFv + $contribFv);
    }

    /**
     * One British-English sentence describing the user's marginal pension tax-relief
     * opportunity. Tax-band thresholds are sourced from TaxConfigService (Rule #2).
     * No acronyms except ISA; no icons or emoji (Rule #9, Rule #15).
     */
    private function taxReliefNote(string $incomeBand, float $incomeMidpoint): string
    {
        $higherThreshold = (int) $this->taxConfig->get('income_tax.higher_rate_threshold', 50270);
        // The Personal Allowance taper starts at £100,000 — a separate threshold from the
        // higher-rate boundary (£50,270). Source: income_tax.personal_allowance_taper_threshold.
        $paTaperStart = (int) $this->taxConfig->get('income_tax.personal_allowance_taper_threshold', 100000);
        $additionalThreshold = (int) $this->taxConfig->get('income_tax.additional_rate_threshold', 125140);

        if ($incomeMidpoint > $additionalThreshold) {
            return 'As an additional-rate taxpayer you are entitled to 45% relief on pension '
                .'contributions, which you can reclaim through your Self Assessment tax return.';
        }

        if ($incomeBand === '100001_125140') {
            // Personal Allowance taper zone — £1 lost per £2 over £100,000 creates an
            // effective 60% rate; a pension contribution restores the allowance.
            return 'Income between '.$this->money($paTaperStart).' and '.$this->money($additionalThreshold)
                .' is subject to an effective 60% tax rate due to the Personal Allowance taper; '
                .'pension contributions in this range attract higher-rate relief and can restore your '
                .'Personal Allowance.';
        }

        if ($incomeMidpoint > $higherThreshold) {
            return 'As a higher-rate taxpayer you can claim extra tax relief on pension contributions '
                .'through your Self Assessment tax return.';
        }

        return 'As a basic-rate taxpayer you receive 20% tax relief on pension contributions '
            .'automatically at source.';
    }

    /**
     * The target retirement age for the projection, sourced from TaxConfigService
     * where available. The seeder exposes this as pension.state_pension.future_spa
     * (currently 67 — scheduled to rise from 66 between April 2026 and April 2028).
     * Falls back to DEFAULT_STATE_PENSION_AGE when the key is absent.
     */
    private function retirementAge(): int
    {
        $pension = $this->taxConfig->getPensionAllowances();

        return (int) ($pension['state_pension']['future_spa'] ?? self::DEFAULT_STATE_PENSION_AGE);
    }

    /**
     * Returns $key if it is a valid key in $map, otherwise returns $default.
     * Mirrors the band-normalisation pattern used in SaveTaxEstimateService.
     *
     * @param  array<string, mixed>  $map
     */
    private function normaliseKey(?string $key, array $map, ?string $default): ?string
    {
        return isset($map[$key]) ? $key : $default;
    }

    private function money(int $n): string
    {
        return '£'.number_format($n);
    }
}
