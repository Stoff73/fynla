<?php

declare(strict_types=1);

namespace App\Services\Tax\Strategies;

use App\DataTransferObjects\StrategyRecommendation;
use App\Enums\StrategyCategory;
use App\Enums\StrategyPriority;
use App\Services\Retirement\AnnualAllowanceChecker;
use App\Services\Stores\PensionStore;
use App\Services\Stores\SavingsStore;
use App\Services\Tax\Strategies\Contract\TaxStrategy;
use App\Services\Tax\TaxStrategyMath;
use App\Services\TaxConfigService;

/**
 * Strategy #3 — Pension Annual Allowance Carry-Forward.
 *
 * Fires when the user is in the higher or additional band, has not maxed
 * the current year's AA, AND has unused AA from the previous three tax years.
 * Saving = unused_carry_forward × user_marginal_rate.
 *
 * Carry-forward window: HMRC allows looking back 3 tax years. We sum
 * max(0, AA_for_year - input_for_year) over pension_input_history entries
 * inside the exact 3-prior-tax-years window (labels shared with
 * AnnualAllowanceChecker::getPrevious3TaxYears — stale rows outside the
 * window never count). AA is held at the current value across the window
 * — a conservative simplification (AA was the same £40k/£60k over the
 * relevant period); refine if HMRC changes mid-window.
 */
final class PensionAACarryForwardStrategy implements TaxStrategy
{
    private const LOOKBACK_YEARS = 3;

    /**
     * Minimum non-ISA liquid wealth (cash + non-ISA savings) before we
     * recommend carry-forward. Below this the user almost certainly cannot
     * deploy meaningful amounts into a pension, so the recommendation is
     * just noise. £10k is a soft heuristic — refine with persona evidence.
     */
    private const MIN_LIQUID_WEALTH_TO_RECOMMEND = 10000.0;

    public function __construct(
        private readonly TaxStrategyMath $math,
        private readonly TaxConfigService $taxConfig,
    ) {}

    public function generate(TaxStrategyContext $context): array
    {
        $user = $context->user;

        // MPAA gate (FA 2004 s227ZA): once the user has flexibly accessed a DC
        // pension, no carry-forward is available against the Money Purchase
        // Annual Allowance — a top-up recommendation would create an AA charge.
        if (app(PensionStore::class)->hasFlexiblyAccessedDcPension($user)) {
            return [];
        }

        $band = $this->math->bandFromIncome($this->math->taxableIncomeFor($user));
        if (! in_array($band, ['higher', 'additional'], true)) {
            return [];
        }

        $aa = (float) ($this->taxConfig->getPensionAllowances()['annual_allowance'] ?? 60000);
        $currentInput = $this->math->estimatePensionContributionThisYear($user, $context->overrides);
        if ($currentInput >= $aa) {
            return [];
        }

        // Exact HMRC window: only the 3 tax years before the active year are
        // eligible — stale rows from older captures must never count.
        $priorYears = app(AnnualAllowanceChecker::class)
            ->getPrevious3TaxYears($this->taxConfig->getTaxYear());

        $history = app(PensionStore::class)
            ->pensionInputHistory($user)
            ->filter(fn ($row) => in_array($row->tax_year, $priorYears, true));

        if ($history->isEmpty()) {
            return [];
        }

        $unused = 0.0;
        foreach ($history as $row) {
            $unused += max(0.0, $aa - (float) $row->pension_input_amount);
        }

        if ($unused <= 0) {
            return [];
        }

        // Cap by HMRC tax-relief rule: pension contributions only attract
        // tax relief up to the user's gross UK earnings for the year.
        // Carry-forward of £162k means nothing to a £75k earner — they can
        // only get tax relief on (gross_income − current_year_input).
        $grossIncome = (float) ($user->annual_employment_income ?? 0)
            + (float) ($user->annual_self_employment_income ?? 0);
        $taxReliefHeadroom = max(0, $grossIncome - $currentInput);
        $usableThisYear = min($unused, $taxReliefHeadroom);

        if ($usableThisYear <= 0) {
            return [];
        }

        // Gate by liquid wealth — recommending carry-forward to someone who
        // has < £10k of non-ISA liquid savings is non-actionable advice.
        // forUser() is joint-aware; the Collection-level where('user_id')
        // post-filter preserves the original single-owner sum.
        $liquidWealth = (float) app(SavingsStore::class)->forUser($user)
            ->where('user_id', $user->id)
            ->where('is_isa', false)
            ->sum('current_balance');

        if ($liquidWealth < self::MIN_LIQUID_WEALTH_TO_RECOMMEND) {
            return [];
        }

        // Cap the headline figure by both the tax-relief rule and a
        // conservative slice of the user's liquid wealth (1/2 of cash —
        // leaves room for emergency fund, won't suggest emptying their
        // bank account into a pension).
        $affordableCap = $liquidWealth * 0.5;
        $recommended = min($usableThisYear, $affordableCap);

        // Round DOWN to the nearest £1,000 so the headline never overstates.
        $recommended = floor($recommended / 1000) * 1000;
        if ($recommended <= 0) {
            return [];
        }

        $marginalRate = $this->math->bandRateFor($user);
        $saving = $recommended * $marginalRate;

        if ($saving < 1) {
            return [];
        }

        return [new StrategyRecommendation(
            type: 'pension_aa_carry_forward',
            category: StrategyCategory::Allowance,
            priority: StrategyPriority::Medium,
            title: sprintf(
                'Top up your pension by up to £%s using carry-forward',
                number_format((int) $recommended),
            ),
            description: sprintf(
                'You\'ve contributed below the £%s Pension Annual Allowance in each of the last 3 tax years. Based on your current earnings and savings you could put up to £%s into your pension this year and reclaim around £%s in income tax. Your full unused allowance over the lookback window is £%s, but contributions only get tax relief up to your gross UK earnings.',
                number_format((int) $aa),
                number_format((int) $recommended),
                number_format((int) round($saving)),
                number_format((int) round($unused / 1000) * 1000),
            ),
            estimatedAnnualTaxSaved: round($saving, 2),
            extra: [
                'unused_carry_forward_total' => round($unused, 2),
                'recommended_contribution' => round($recommended, 2),
                'tax_relief_headroom' => round($taxReliefHeadroom, 2),
                'liquid_wealth' => round($liquidWealth, 2),
                'marginal_rate' => $marginalRate,
                'lookback_years' => self::LOOKBACK_YEARS,
                'current_year_input' => round($currentInput, 2),
                'annual_allowance' => $aa,
            ],
        )];
    }
}
