<?php

declare(strict_types=1);

namespace App\Services\Tax\Thresholds;

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
    public function __construct(private readonly UKTaxCalculator $calculator) {}

    public function delta(ThresholdContext $context, float $reduceBy, string $mechanism = 'pension'): ThresholdCost
    {
        $mix = $this->mix($context);
        $before = $this->run($mix, $context);

        $after = $mix;
        $extraPension = 0.0;
        if ($mechanism === 'isa') {
            // Move interest first, then dividends, out of the computation entirely.
            $fromInterest = min($after['interest'], $reduceBy);
            $after['interest'] -= $fromInterest;
            $after['dividend'] -= min($after['dividend'], $reduceBy - $fromInterest);
        } else {
            $extraPension = $reduceBy;
        }
        $afterRun = $this->run($after, $context, $extraPension);

        return new ThresholdCost(
            incomeTax: round($before['non_savings_tax'] - $afterRun['non_savings_tax'], 2),
            niClass1: round($before['class_1'] - $afterRun['class_1'], 2),
            niClass4: round($before['class_4'] - $afterRun['class_4'], 2),
            dividendTax: round($before['dividend_tax'] - $afterRun['dividend_tax'], 2),
            interestTax: round($before['interest_tax'] - $afterRun['interest_tax'], 2),
        );
    }

    /** @return array<string, float> */
    public function mix(ThresholdContext $context): array
    {
        $c = $context->components();

        return [
            'employment' => ($c['employment'] ?? 0) + ($c['vesting'] ?? 0),
            'self_employment' => $c['self_employment'] ?? 0,
            'rental' => $c['rental'] ?? 0,
            'dividend' => $c['dividend'] ?? 0,
            'interest' => $c['interest'] ?? 0,
            // Pension income in payment, trust and other income share the non-savings
            // bands and carry no NI, which is exactly the calculator's `otherIncome`.
            'other' => ($c['pension_income'] ?? 0) + ($c['trust'] ?? 0) + ($c['other'] ?? 0),
        ];
    }

    /** @return array{non_savings_tax: float, dividend_tax: float, interest_tax: float, class_1: float, class_4: float} */
    private function run(array $mix, ThresholdContext $context, float $extraPension = 0.0): array
    {
        $deductions = $context->definitions['deductions'] ?? [];
        $pension = (float) ($deductions['employee_pension_contributions'] ?? 0) + $extraPension;
        $giftAid = (float) ($deductions['gift_aid_gross'] ?? 0);
        $bpa = (float) ($deductions['blind_persons_allowance'] ?? 0);

        $full = $this->calculator->calculateNetIncome($mix['employment'], $mix['self_employment'], $mix['rental'], $mix['dividend'], $mix['interest'], $mix['other'], $pension, $giftAid, $bpa);
        $noDividends = $this->calculator->calculateNetIncome($mix['employment'], $mix['self_employment'], $mix['rental'], 0.0, $mix['interest'], $mix['other'], $pension, $giftAid, $bpa);
        $noSavings = $this->calculator->calculateNetIncome($mix['employment'], $mix['self_employment'], $mix['rental'], 0.0, 0.0, $mix['other'], $pension, $giftAid, $bpa);

        return [
            'non_savings_tax' => (float) $noSavings['income_tax'],
            'interest_tax' => (float) $noDividends['income_tax'] - (float) $noSavings['income_tax'],
            'dividend_tax' => (float) $full['income_tax'] - (float) $noDividends['income_tax'],
            'class_1' => (float) ($full['breakdown']['class_1_ni'] ?? 0),
            'class_4' => (float) ($full['breakdown']['class_4_ni'] ?? 0),
        ];
    }
}
