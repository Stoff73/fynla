<?php

declare(strict_types=1);

namespace App\Services\Tax\Thresholds;

use App\Services\TaxConfigService;
use App\Services\UKTaxCalculator;

/**
 * What being `$reduceBy` over a line costs, as the difference between two full
 * computations over the user's actual income mix: one as they stand, one with
 * the excess taken out by the lever's mechanism. `UKTaxCalculator` already
 * applies Class 1 on employment, Class 4 on self-employment, no NI on pension
 * income, the savings allowance on interest, dividend rates on dividends, the
 * tapered allowance and the Gift Aid extension, so nothing is re-derived here.
 *
 * Dividend and interest tax are top-sliced the way HMRC stacks them: tax with
 * dividends less tax without is the dividend tax; the same for interest on the
 * non-dividend figure.
 */
final class ThresholdCostCalculator
{
    public function __construct(
        private readonly UKTaxCalculator $calculator,
        private readonly TaxConfigService $taxConfig,
    ) {}

    public function delta(ThresholdContext $context, float $reduceBy, string $mechanism = 'pension'): ThresholdCost
    {
        $mix = $this->mix($context);
        $before = $this->run($mix, $context);

        $after = $mix;
        $extraPension = 0.0;
        if ($mechanism === 'isa') {
            // Move interest first, then dividends, out of the computation entirely.
            // Only what is actually held can move, so a request beyond the balance
            // prices what the move would really achieve.
            $fromInterest = min($after['interest'], $reduceBy);
            $after['interest'] -= $fromInterest;
            $fromDividend = min($after['dividend'], $reduceBy - $fromInterest);
            $after['dividend'] -= $fromDividend;
            $applied = $fromInterest + $fromDividend;
        } else {
            $applied = $extraPension = min($reduceBy, $this->pensionCeiling($mix));
        }
        $afterRun = $this->run($after, $context, $extraPension);

        return new ThresholdCost(
            incomeTax: round($before['non_savings_tax'] - $afterRun['non_savings_tax'], 2),
            niClass1: round($before['class_1'] - $afterRun['class_1'], 2),
            niClass4: round($before['class_4'] - $afterRun['class_4'], 2),
            dividendTax: round($before['dividend_tax'] - $afterRun['dividend_tax'], 2),
            interestTax: round($before['interest_tax'] - $afterRun['interest_tax'], 2),
            requested: round($reduceBy, 2),
            applied: round($applied, 2),
        );
    }

    /**
     * The most a pension contribution can attract relief on (FA 2004 s190): relevant
     * UK earnings, or the basic amount when those are lower.
     *
     * Earnings from work only. Rental, pension income in payment and other income are
     * not relevant earnings, so a director on £12,570 of salary and £120,000 of
     * dividends cannot pension their way under £100,000 however much they hold.
     * `UKTaxCalculator` deducts a contribution from non-savings income alone, which
     * silently floors the deduction rather than reporting it, so the limit is applied
     * here where the shortfall can be published as `requested` against `applied`.
     *
     * @param  array<string, float>  $mix
     */
    private function pensionCeiling(array $mix): float
    {
        // Vests are earnings from work too, so relief reaches them.
        $relevantEarnings = (float) $mix['employment'] + (float) $mix['vesting'] + (float) $mix['self_employment'];

        return max(
            $relevantEarnings,
            (float) $this->taxConfig->get('pension.relevant_earnings_minimum', 0),
        );
    }

    /** @return array<string, float> */
    public function mix(ThresholdContext $context): array
    {
        $c = $context->components();

        return [
            // Salary and share vests are published SEPARATELY, though both are
            // employment income under ITEPA 2003 and both carry Class 1. Folding the
            // vest into the salary gave the strip no way to name either: a £112,400
            // earner with £12,000 of RSUs was told they had "£124,400 employment
            // income", a figure that appears on no payslip they have ever seen.
            // `run()` adds them back together for the calculator, which wants one
            // employment argument.
            'employment' => $this->employment($context, $c),
            'vesting' => $c['vesting'] ?? 0,
            'self_employment' => $c['self_employment'] ?? 0,
            'rental' => $c['rental'] ?? 0,
            'dividend' => $c['dividend'] ?? 0,
            'interest' => $c['interest'] ?? 0,
            // Pension income in payment, trust and other income share the non-savings
            // bands and carry no NI, which is exactly the calculator's `otherIncome`.
            'other' => ($c['pension_income'] ?? 0) + ($c['trust'] ?? 0) + ($c['other'] ?? 0),
        ];
    }

    /**
     * Salary as the definitions actually count it. Share vests are published beside
     * this figure rather than inside it, so the strip can name each.
     *
     * `components.employment` is the recorded figure, untouched. Where the user records
     * pay BEFORE salary sacrifice, `IncomeDefinitionsService` takes the sacrificed
     * amount off total income on the way to every definition, but leaves the component
     * as recorded — so running the calculator over the raw component puts its internal
     * adjusted net income above `$context->adjustedNetIncome()` by the sacrificed
     * amount, and prices the excess against a personal allowance the user does not have.
     * Sacrificed pay is never the employee's income (W-0204), so it comes off here too.
     *
     * `post_sacrifice` means the recorded figure already excludes it, and `null` means
     * the question does not arise because nothing is sacrificed. Only `gross` and the
     * stated-assumption `assumed_gross` need the correction.
     *
     * @param  array<string, float>  $components
     */
    private function employment(ThresholdContext $context, array $components): float
    {
        $employment = (float) ($components['employment'] ?? 0);

        $basis = $context->definitions['employment_income_basis'] ?? null;
        if ($basis === 'gross' || $basis === 'assumed_gross') {
            $employment -= (float) ($context->definitions['deductions']['salary_sacrificed'] ?? 0);
        }

        return max(0.0, $employment);
    }

    /** @return array{non_savings_tax: float, dividend_tax: float, interest_tax: float, class_1: float, class_4: float} */
    private function run(array $mix, ThresholdContext $context, float $extraPension = 0.0): array
    {
        $deductions = $context->definitions['deductions'] ?? [];
        $pension = (float) ($deductions['employee_pension_contributions'] ?? 0) + $extraPension;
        $giftAid = (float) ($deductions['gift_aid_gross'] ?? 0);
        $bpa = (float) ($deductions['blind_persons_allowance'] ?? 0);

        // One employment figure for the calculator: Class 1 applies to the vest as it
        // does to the salary, and the bands see a single stream.
        $employment = (float) $mix['employment'] + (float) ($mix['vesting'] ?? 0);

        $full = $this->calculator->calculateNetIncome($employment, $mix['self_employment'], $mix['rental'], $mix['dividend'], $mix['interest'], $mix['other'], $pension, $giftAid, $bpa);
        $noDividends = $this->calculator->calculateNetIncome($employment, $mix['self_employment'], $mix['rental'], 0.0, $mix['interest'], $mix['other'], $pension, $giftAid, $bpa);
        $noSavings = $this->calculator->calculateNetIncome($employment, $mix['self_employment'], $mix['rental'], 0.0, 0.0, $mix['other'], $pension, $giftAid, $bpa);

        return [
            'non_savings_tax' => (float) $noSavings['income_tax'],
            'interest_tax' => (float) $noDividends['income_tax'] - (float) $noSavings['income_tax'],
            'dividend_tax' => (float) $full['income_tax'] - (float) $noDividends['income_tax'],
            'class_1' => (float) ($full['breakdown']['class_1_ni'] ?? 0),
            'class_4' => (float) ($full['breakdown']['class_4_ni'] ?? 0),
        ];
    }
}
