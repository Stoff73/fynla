<?php

declare(strict_types=1);

namespace App\Services\Tax;

use App\Models\User;
use App\Services\Property\PropertyService;
use App\Services\TaxConfigService;
use App\Traits\ResolvesIncome;

class IncomeDefinitionsService
{
    use ResolvesIncome;

    public function __construct(
        private readonly TaxConfigService $taxConfig,
        private readonly PropertyService $propertyService,
    ) {}

    public function calculate(int $userId): array
    {
        $user = User::with(['dcPensions', 'dbPensions', 'statePension'])->findOrFail($userId);
        $pensionContributions = $this->getPensionContributions($user);

        // 1. Total Income — from all sources including computed rental and pension income
        $components = $this->getIncomeComponents($user);
        $totalIncome = array_sum($components);

        // 2. Net Income (ITA 2007 s23 Step 2) — total income less the reliefs
        // listed in s24.
        //
        // W-0205: this used to deduct the Gift Aid gross-up as well, so for a
        // donor the figure labelled "Net Income" was net income less the
        // grossed-up donation — part of the way to adjusted net income, and not a
        // figure with a name. **Gift Aid is not a s24 relief.** A Gift Aid
        // donation extends the basic rate band; it does not reduce net income.
        // It comes off one definition further down, at s58. The end figures were
        // already right; only this intermediate carried a statute's name for
        // another statute's number.
        //
        // **The Blind Person's Allowance is NOT a s58 deduction** — corrected by
        // tax-compliance-reviewer, 2026-08-25. s58 has four steps and none of them
        // is the BPA: it is an s38 allowance deducted at s23 **Step 3**, downstream
        // of net income, so it cannot reduce adjusted net income by construction.
        // This service nonetheless subtracts it below, which is a live money defect
        // (see W-0485) — `UKTaxCalculator:720` computes ANI without it and gets the
        // right answer, so the application currently holds two contradictory
        // answers. It is deliberately NOT corrected here: W-0205's acceptance 3
        // requires adjusted net income to be unchanged by that item, so the
        // arithmetic fix belongs to its own item with its own figures.
        $pensionRelief = $pensionContributions['employee'];
        $giftAidGross = $this->calculateGiftAidGrossUp($user);
        $netIncome = $totalIncome - $pensionRelief;

        // 3. Adjusted Net Income (ITA 2007 s58) — drives the Personal Allowance
        // taper. Net income less the grossed-up Gift Aid donation and the Blind
        // Person's Allowance. Its value is unchanged by W-0205: the donation was
        // always deducted exactly once on the way here, and still is — what moved
        // is which step it is attributed to.
        $bpa = $user->is_registered_blind ? $this->taxConfig->getBlindPersonsAllowance() : 0.0;
        $adjustedNetIncome = $netIncome - $giftAidGross - $bpa;

        // 4. Threshold Income (FA 2004 s228ZA) — total income less net-pay
        // employee contributions only, deducted once. Gift Aid and the Blind
        // Person's Allowance do NOT reduce it (those belong to Adjusted Net Income).
        //
        // W-0189: this branches from TOTAL income, not from the adjusted net income
        // computed above it, and the employee contribution it deducts is the same
        // one already taken out at step 2 — deducted once across the two, never
        // twice. The panel presented these five figures as a single running column,
        // so the deduction appeared to be applied a second time and produce an
        // unchanged total. The figures were right; the chain was the lie.
        //
        // W-0205 consequence, stated so it is not read as a bug: net income and
        // threshold income are now the SAME number for a net-pay contributor,
        // because the Gift Aid gross-up was the only thing separating them and it
        // has moved to adjusted net income where it belongs. That coincidence is
        // correct — for someone with no salary sacrifice and no relief-at-source
        // contributions, the two definitions genuinely land on the same figure.
        // What still distinguishes a Gift Aid donor's definitions is adjusted net
        // income against threshold income, not net income against it.
        $thresholdIncome = $totalIncome - $pensionContributions['employee'];

        // 5. Adjusted Income (FA 2004 s228ZA) — total income plus employer
        // contributions (equivalently threshold income plus both the employee
        // and employer pension inputs). Also branches from total income.
        $adjustedIncome = $totalIncome + $pensionContributions['employer'];

        // Ensure no negative values
        $totalIncome = max(0.0, $totalIncome);
        $netIncome = max(0.0, $netIncome);
        $adjustedNetIncome = max(0.0, $adjustedNetIncome);
        $thresholdIncome = max(0.0, $thresholdIncome);
        $adjustedIncome = max(0.0, $adjustedIncome);

        $adjustedAllowances = $this->calculateAdjustedAllowances($adjustedNetIncome, $thresholdIncome, $adjustedIncome);

        return [
            'total_income' => round($totalIncome, 2),
            'net_income' => round($netIncome, 2),
            'adjusted_net_income' => round($adjustedNetIncome, 2),
            'threshold_income' => round($thresholdIncome, 2),
            'adjusted_income' => round($adjustedIncome, 2),
            'components' => $components,
            // W-0189 acceptance 2 — the arrangement the deduction was made under,
            // so the panel can name it instead of the reader having to guess why
            // £11,600 is deducted once rather than at both steps that mention it.
            'pension_arrangement' => $pensionContributions['arrangement'],
            'deductions' => [
                'pension_relief' => round($pensionRelief, 2),
                'gift_aid_gross' => round($giftAidGross, 2),
                'blind_persons_allowance' => round($bpa, 2),
                'employee_pension_contributions' => round($pensionContributions['employee'], 2),
                'employer_pension_contributions' => round($pensionContributions['employer'], 2),
            ],
            'adjusted_allowances' => $adjustedAllowances,
        ];
    }

    private function getIncomeComponents(User $user): array
    {
        return [
            'employment' => round((float) ($user->annual_employment_income ?? 0), 2),
            'self_employment' => round((float) ($user->annual_self_employment_income ?? 0), 2),
            'rental' => round($this->calculateRentalIncome($user), 2),
            'dividend' => round((float) ($user->annual_dividend_income ?? 0), 2),
            'interest' => round((float) ($user->annual_interest_income ?? 0), 2),
            'other' => round((float) ($user->annual_other_income ?? 0), 2),
            'trust' => round((float) ($user->annual_trust_income ?? 0), 2),
            'pension_income' => round($this->calculatePensionIncome($user), 2),
        ];
    }

    /**
     * The user's annual rental profit, from the one home for that figure.
     *
     * This used to re-derive a GROSS rent figure with its own copy of the
     * ownership-share arithmetic, so the income page's allowance panel tested
     * the £100,000 taper against a different rental figure from the one the tax
     * computation on the same screen was taxing — £10,800 against £8,880 for the
     * peak_earners persona (W-0175). Property income enters total income as the
     * profits of the property business (ITA 2007 s23 Step 1 over ITTOIA 2005
     * Part 3), which is what adjusted net income and threshold income build on,
     * so the profit is the correct base here as well as there.
     *
     * @see PropertyService::annualRentalTaxPosition()
     */
    private function calculateRentalIncome(User $user): float
    {
        return (float) $this->propertyService->annualRentalTaxPosition($user)['total'];
    }

    /**
     * Calculate annual pension income from DB pensions in payment and state pension.
     *
     * Shares one implementation with UserProfileService and PersonalAccountsService
     * (W-0036). This is the tax-facing consumer, so the defect it carried was not a
     * retirement display bug: a not-yet-payable Defined Benefit pension counted as
     * income here moved the user's taxable income, Personal Allowance taper and
     * Child Benefit position.
     */
    private function calculatePensionIncome(User $user): float
    {
        return $this->resolvePensionIncomeInPayment($user);
    }

    /**
     * Employee and employer pension inputs, and the arrangement they are made under.
     *
     * `arrangement` describes what this method DID, not a regime it verified:
     *
     *   * `none`    — no employee contributions to deduct.
     *   * `net_pay` — contributions deducted from total income once. No workplace
     *                 pension is flagged as salary sacrifice, and the application
     *                 has no relief-at-source flag, so net pay is the treatment.
     *   * `salary_sacrifice` — at least one workplace pension is flagged as salary
     *                 sacrifice. The deduction is UNCHANGED: the sacrificed pay is
     *                 not added back to threshold income under FA 2004 s228ZA(3),
     *                 because nothing records whether `annual_employment_income` is
     *                 the pre- or post-sacrifice figure, and assuming one would move
     *                 a user's taper position on a guess. The panel names the
     *                 arrangement rather than claiming a treatment that was applied.
     *
     * @return array{employee: float, employer: float, arrangement: string}
     */
    private function getPensionContributions(User $user): array
    {
        $employee = 0.0;
        $employer = 0.0;
        $sacrifices = false;

        foreach ($user->dcPensions as $pension) {
            $salary = (float) ($pension->annual_salary ?? 0);
            $contribution = $salary * ((float) ($pension->employee_contribution_percent ?? 0) / 100);
            $employee += $contribution;
            $employer += $salary * ((float) ($pension->employer_contribution_percent ?? 0) / 100);

            if ($contribution > 0 && $pension->salary_sacrifice) {
                $sacrifices = true;
            }
        }

        $employee = round($employee, 2);

        return [
            'employee' => $employee,
            'employer' => round($employer, 2),
            'arrangement' => match (true) {
                $employee <= 0 => 'none',
                $sacrifices => 'salary_sacrifice',
                default => 'net_pay',
            },
        ];
    }

    private function calculateGiftAidGrossUp(User $user): float
    {
        if (! $user->is_gift_aid || ! $user->annual_charitable_donations) {
            return 0.0;
        }

        return round((float) $user->annual_charitable_donations * 1.25, 2);
    }

    private function calculateAdjustedAllowances(float $adjustedNetIncome, float $thresholdIncome, float $adjustedIncome): array
    {
        $incomeTax = $this->taxConfig->getIncomeTax();
        $pensionConfig = $this->taxConfig->getPensionAllowances();

        $fullPA = (float) ($incomeTax['personal_allowance'] ?? 12570);
        $paTaperThreshold = (float) ($incomeTax['personal_allowance_taper_threshold'] ?? 100000);

        $fullAA = (float) ($pensionConfig['annual_allowance'] ?? 60000);
        $taper = $pensionConfig['tapered_annual_allowance'] ?? [];
        $aaThresholdIncome = (float) ($taper['threshold_income'] ?? 200000);
        $aaAdjustedIncome = (float) ($taper['adjusted_income_threshold'] ?? $taper['adjusted_income'] ?? 260000);
        $aaMinimum = (float) ($taper['minimum_allowance'] ?? 10000);
        $aaTaperRate = (float) ($taper['taper_rate'] ?? 0.5);

        // Personal Allowance taper
        $adjustedPA = $fullPA;
        $paTapered = false;
        if ($adjustedNetIncome > $paTaperThreshold) {
            $excess = $adjustedNetIncome - $paTaperThreshold;
            $reduction = floor($excess / 2);
            $adjustedPA = max(0.0, $fullPA - $reduction);
            $paTapered = $adjustedPA < $fullPA;
        }

        // Pension AA taper — both conditions must be met
        $adjustedAA = $fullAA;
        $aaTapered = false;
        if ($thresholdIncome > $aaThresholdIncome && $adjustedIncome > $aaAdjustedIncome) {
            $excess = $adjustedIncome - $aaAdjustedIncome;
            $reduction = floor($excess * $aaTaperRate);
            $adjustedAA = max($aaMinimum, $fullAA - $reduction);
            $aaTapered = $adjustedAA < $fullAA;
        }

        return [
            'personal_allowance' => round($adjustedPA, 2),
            'personal_allowance_full' => round($fullPA, 2),
            'personal_allowance_tapered' => $paTapered,
            'pension_annual_allowance' => round($adjustedAA, 2),
            'pension_annual_allowance_full' => round($fullAA, 2),
            'pension_aa_tapered' => $aaTapered,
        ];
    }
}
