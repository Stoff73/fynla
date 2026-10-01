<?php

declare(strict_types=1);

namespace App\Services\Tax\Strategies;

use App\DataTransferObjects\StrategyRecommendation;
use App\Enums\StrategyCategory;
use App\Enums\StrategyPriority;
use App\Models\User;
use App\Services\Tax\Strategies\Contract\TaxStrategy;
use App\Services\Tax\TaxStrategyMath;
use App\Services\TaxConfigService;

/**
 * Pension tax relief below the Personal Allowance taper (CSJ ruling
 * 2026-09-25: suggested for every band). Above the taper threshold,
 * IncomeBandStrategy owns the pension items.
 *
 *   - Higher rate: the slice taxed at the higher rate, relieved at that rate.
 *   - Basic rate:  a tenth of relevant earnings less what already goes in,
 *                  relieved at the basic rate and capped at income above the
 *                  Personal Allowance so relief never exceeds tax paid.
 *   - No earnings: the basic amount (FA 2004 s190) less what already goes
 *                  in, with relief at source whether or not tax is paid.
 */
final class PensionTaxReliefStrategy implements TaxStrategy
{
    // The /savetax funnel (SaveTaxEstimateService) reads this constant, so the
    // plan keeps the funnel's promise; a user-set target replaces it if one is
    // ever captured.
    public const BASIC_RATE_SHARE_OF_EARNINGS = 0.10;

    public function __construct(
        private readonly TaxStrategyMath $math,
        private readonly TaxConfigService $taxConfig,
    ) {}

    public function generate(TaxStrategyContext $context): array
    {
        $user = $context->user;

        // Net income (after net-pay contributions) against bands extended by
        // relief-at-source payments, both from IncomeDefinitionsService.
        // The plan prices the pension first (CSJ 2026-09-29, #993), so no
        // other item's sheltered interest is taken off before it.
        $taxable = $this->math->taxableIncomeFor($user);

        $taperThreshold = (float) ($this->taxConfig->getIncomeTax()['personal_allowance_taper_threshold'] ?? 0);
        // Above the threshold the tax-trap item owns the pension relief.
        if ($this->math->adjustedNetIncomeFor($user) > $taperThreshold) {
            return [];
        }

        $earnings = $this->math->relevantEarningsFor($user);
        $age = $this->math->ageOf($user->date_of_birth);
        $availableAA = $this->math->availableAnnualAllowance($user, $context->overrides);
        // Never more than the money the user has to pay it with (CSJ
        // 2026-09-30; PensionAffordability). Unknown money leaves it as it was.
        $fundable = $context->pensionFundableGross((float) $this->taxConfig->getPensionAllowances()['tax_relief']['basic_rate']);
        if ($fundable !== null) {
            $availableAA = min($availableAA, $fundable);
        }
        $aboveAllowance = $taxable - $this->math->personalAllowanceFor($user);
        // No relief on contributions paid after the member reaches the age in
        // pension.relief_max_age: FA 2004 s188(3)(a),
        // https://www.legislation.gov.uk/ukpga/2004/12/section/188
        $maxAge = (int) $this->taxConfig->getPensionAllowances()['relief_max_age'];
        if (($age !== null && $age >= $maxAge) || $availableAA <= 0) {
            return [];
        }
        if ($earnings <= 0) {
            return $this->math->isDeclaredNonEarner($user)
                ? $this->nonEarnerItem($context, $availableAA, $maxAge)
                : [];
        }
        if ($aboveAllowance <= 0) {
            return [];
        }

        $band = $this->math->bandFromIncomeFor($user, $taxable);
        $contribution = $band === 'higher'
            ? min(
                $this->math->higherRateSlice($user, $taxable, $this->math->bandThresholdsFor($user)['higher']),
                $availableAA,
                $earnings - $this->math->grossEmployeePensionContributions($user),
            )
            : min(
                $earnings * self::BASIC_RATE_SHARE_OF_EARNINGS - $this->math->estimatePensionContributionThisYear($user, $context->overrides),
                $availableAA,
                $this->basicRateTaxedIncome($user, $taxable),
            );

        // Down, never up: rounding up would relieve tax the user does not pay.
        $display = (int) (floor($contribution / 100) * 100);
        if ($display < 100) {
            return [];
        }

        $rate = $this->math->bandRateForBand($band);
        $saving = round($display * $rate, 2);
        $ratePct = (int) round($rate * 100);
        $basicPct = (int) round($this->math->bandRateForBand('basic') * 100);
        // Relief "through your pay" is net pay arrangement, open only to
        // employees in a workplace scheme. Someone with no employment income
        // pays into a personal pension under relief at source and claims the
        // higher-rate part through Self Assessment (SaveTax matrix E7):
        // https://www.gov.uk/tax-on-your-private-pension/pension-tax-relief
        $reliefRoute = (float) ($user->annual_employment_income ?? 0) > 0
            ? sprintf('A workplace scheme gives the relief through your pay; for a personal pension the provider adds %d%% and you claim the rest through Self Assessment.', $basicPct)
            : sprintf('Your pension provider adds %d%% to what you pay, and you claim the rest through your Self Assessment tax return.', $basicPct);

        return [new StrategyRecommendation(
            // One type for every band, so a user's done or dismissed state for
            // this action survives a change of band.
            type: 'pension_tax_relief',
            category: StrategyCategory::IncomeBand,
            priority: $band === 'higher' ? StrategyPriority::High : StrategyPriority::Medium,
            title: sprintf('Pay £%s more into your pension and save £%s in tax', number_format($display), number_format((int) floor($saving))),
            description: $band === 'higher'
                ? sprintf(
                    'Pension contributions get tax relief at your highest rate. £%s of your income is taxed at %d%%, so paying that amount into a pension saves £%s this year. %s',
                    number_format($display), $ratePct, number_format((int) floor($saving)), $reliefRoute,
                )
                // The saving is the card's "why" line, so it is not repeated here (CSJ 2026-09-28).
                : sprintf('Every £%s you pay into a pension gets %d%% tax relief.', number_format(100), $ratePct),
            estimatedAnnualTaxSaved: $saving,
            extra: [
                'suggested_contribution' => (float) $display,
                'relief_rate' => $rate,
                'tax_band' => $band,
            ],
        )];
    }

    /**
     * Someone with no relevant earnings (a retiree, or income only from rent or
     * savings) still gets relief on contributions up to the basic amount,
     * pension.relevant_earnings_minimum (FA 2004 s190). The /savetax funnel
     * promises the same line, priced by the same TaxStrategyMath call.
     *
     * @return array<int, StrategyRecommendation>
     */
    private function nonEarnerItem(TaxStrategyContext $context, float $availableAA, int $maxAge): array
    {
        $user = $context->user;
        // Funded from savings, not income they do not have: never more than
        // their recorded cash covers (CSJ 2026-09-29).
        $gross = floor(min(
            $this->math->pensionReliefLimit(0.0) - $this->math->estimatePensionContributionThisYear($user, $context->overrides),
            $availableAA,
            $this->math->nonEarnerFundableGross($user),
        ) / 100) * 100;
        if ($gross < 100) {
            return [];
        }

        $basic = $this->math->bandRateForBand('basic');
        $atSource = round($gross * (float) $this->taxConfig->getPensionAllowances()['tax_relief']['basic_rate'], 2);
        $saving = round($this->math->reliefAtSourceSaving($user, $gross), 2);
        $net = $gross - $atSource;
        $claim = $saving - $atSource >= 1
            ? sprintf(' You claim the other £%s back through Self Assessment.', number_format((int) floor($saving - $atSource)))
            : '';

        return [new StrategyRecommendation(
            type: 'pension_tax_relief',
            category: StrategyCategory::IncomeBand,
            priority: StrategyPriority::Medium,
            // Worded as money HMRC adds, not tax saved: the user may pay no tax
            // (CSJ 2026-09-29). The provider claims basic-rate relief from HMRC
            // and adds it (https://www.gov.uk/tax-on-your-private-pension/pension-tax-relief).
            title: sprintf('Pay £%s into a personal pension and HMRC adds £%s', number_format((int) $net), number_format((int) $atSource)),
            description: sprintf(
                'Without earnings from work you can still get tax relief on up to £%s a year of pension contributions. Pay £%s into a personal pension and HMRC adds £%s through your pension provider, making £%s.%s Relief stops once you reach %d.',
                number_format((int) $gross), number_format((int) $net), number_format((int) $atSource), number_format((int) $gross), $claim, $maxAge,
            ),
            estimatedAnnualTaxSaved: $saving,
            extra: [
                'suggested_contribution' => (float) $gross,
                'relief_rate' => $basic,
                'tax_band' => 'no_earnings',
            ],
        )];
    }

    /**
     * Income actually taxed at the basic rate: non-savings income above the
     * Personal Allowance, plus interest left after any unused allowance, the
     * starting rate for savings (ITA 2007 s12) and the Personal Savings
     * Allowance (s12B). Dividends are taxed at the dividend rate (s8), not the
     * basic rate, so relief on them is not claimed here.
     */
    private function basicRateTaxedIncome(User $user, float $taxable): float
    {
        $parts = $this->math->incomePartsFor($user);
        $interest = $parts['interest'];
        $nonSavings = max(0.0, $taxable - $interest - $parts['dividends']);
        $allowance = $this->math->personalAllowanceFor($user);
        $nonSavingsAbove = max(0.0, $nonSavings - $allowance);
        $startingRate = max(0.0, (float) $this->taxConfig->getIncomeTax()['starting_rate_for_savings']['band'] - $nonSavingsAbove);
        $interestTaxed = max(0.0, $interest - max(0.0, $allowance - $nonSavings) - $startingRate - $this->math->psaForBand('basic'));

        return $nonSavingsAbove + $interestTaxed;
    }
}
