<?php

declare(strict_types=1);

namespace App\Services\Tax\Strategies;

use App\DataTransferObjects\StrategyRecommendation;
use App\Enums\StrategyCategory;
use App\Enums\StrategyPriority;
use App\Services\Tax\Strategies\Contract\TaxStrategy;
use App\Services\Tax\TaxStrategyMath;
use App\Services\TaxConfigService;

/**
 * Strategies #1 (PA Taper Rescue, 60% band) + #2 (Additional-Rate Avoidance,
 * 45% band) — fire when the user's taxable income crosses the relevant
 * thresholds AND there's pension AA headroom to deploy.
 */
final class IncomeBandStrategy implements TaxStrategy
{
    public function __construct(
        private readonly TaxStrategyMath $math,
        private readonly TaxConfigService $taxConfig,
    ) {}

    public function generate(TaxStrategyContext $context): array
    {
        $user = $context->user;
        $overrides = $context->overrides;

        $income = $this->taxConfig->getIncomeTax();
        $taperThreshold = (float) $income['personal_allowance_taper_threshold'];
        $additionalRateThreshold = $this->math->bandThresholdsFor($user)['additional'];
        $higherRateThreshold = $this->math->bandThresholdsFor($user)['higher'];
        // Where the 60% band ends: the allowance is gone £1 for every £(1 / taper
        // rate) over the threshold. Adjusted net income is already net of Gift
        // Aid, so the band top is NOT extended by it the way the additional-rate
        // threshold is; the strip says the same figure.
        $taperRate = max((float) $income['personal_allowance_taper_rate'], 0.0001);
        $taperEnd = $taperThreshold + (float) $income['personal_allowance'] / $taperRate;

        $taxableIncome = $this->math->taxableIncomeFor($user);
        $adjustedNetIncome = $this->math->adjustedNetIncomeFor($user);
        $availableAA = $this->math->availableAnnualAllowance($user, $overrides);
        // Relief is only given on contributions up to the greater of relevant
        // UK earnings and the basic amount (FA 2004 s190,
        // https://www.legislation.gov.uk/ukpga/2004/12/section/190), less what
        // the user already pays in; a large dividend income earns none.
        $earnings = (float) ($user->annual_employment_income ?? 0) + (float) ($user->annual_self_employment_income ?? 0);
        $reliefLimit = $this->math->pensionReliefLimit($earnings)
            - $this->math->grossEmployeePensionContributions($user);
        $availableAA = min($availableAA, max(0.0, $reliefLimit));
        // Never more than the money the user has to pay it with (CSJ
        // 2026-09-30; PensionAffordability). Unknown money leaves it as it was.
        $fundable = $context->pensionFundableGross((float) $this->taxConfig->getPensionAllowances()['tax_relief']['basic_rate']);
        if ($fundable !== null) {
            $availableAA = min($availableAA, $fundable);
        }
        if ($availableAA <= 0) {
            return [];
        }

        // M7 — source band rates from TaxConfigService rather than hardcoding.
        // The effective rate in the PA-taper band: each £1 earned is taxed at
        // the higher rate and withdraws £(taper rate) of allowance that was
        // taxed at the same rate, so higher rate × (1 + taper rate).
        $higherRate = $this->math->bandRateForBand('higher');
        $taperEffectiveRate = $higherRate * (1 + $taperRate);

        $recommendations = [];

        // #1 — Personal Allowance Taper Rescue (60% effective rate band).
        // First the slice between the taper threshold and the user's income,
        // then, once the allowance is back, the income still taxed at the
        // higher rate down to where that rate starts (SaveTax matrix E3), as
        // #2 already does. The part below the threshold is suggested only when
        // the money to pay it is known (CSJ 2026-09-30, "We ask for
        // expenditure"); short money goes to the 60% slice first.
        if ($adjustedNetIncome > $taperThreshold && $adjustedNetIncome <= $additionalRateThreshold) {
            $taperSlice = $adjustedNetIncome - $taperThreshold;
            $belowTaper = $fundable !== null
                ? max(0.0, $this->math->higherRateSlice($user, $taxableIncome, $higherRateThreshold) - $taperSlice)
                : 0.0;
            // Sized in TaxStrategyMath, where the /savetax funnel sizes its
            // taper line (without the part below the threshold).
            $displayContribution = (int) $this->math->taperRescueContribution($adjustedNetIncome, $availableAA, $belowTaper);
            $reachesBelow = $displayContribution > $taperSlice;
            if ($displayContribution > 0) {
                // Split the 60% saving into the two mechanisms the user can see
                // in their own figures (CSJ): (1) Personal Allowance reclaimed —
                // £1 of allowance restored for every £2 contributed in this band,
                // and that allowance was being taxed at the higher rate; (2)
                // direct higher-rate relief on the whole contribution. Every
                // figure derives from the same rounded contribution so the two
                // parts always sum to the headline total. Rates from
                // TaxConfigService (Rule #2) — never hardcoded.
                // The total comes from the tax engine, so interest in
                // the Personal Savings Allowance and dividends are priced at
                // their own rates; the direct relief is what the reclaimed
                // allowance does not account for.
                // The allowance actually won back: never more than was lost,
                // which matters above the end of the band (ITA 2007 s35(2)).
                $paReclaimed = (int) ($this->math->personalAllowanceForIncome($adjustedNetIncome - $displayContribution)
                    - $this->math->personalAllowanceForIncome($adjustedNetIncome));
                $paReclaimSaving = (int) round($paReclaimed * $higherRate);
                $totalSaving = (int) round($this->math->pensionContributionSaving($user, $displayContribution));
                $directRelief = max(0, $totalSaving - $paReclaimSaving);
                // The agreed wording names the rate; it only holds when the
                // engine agrees the relief is at that rate (not when the top
                // slice is dividends or allowance-covered interest).
                $atHigherRate = $directRelief === (int) round($displayContribution * $higherRate);
                // "Each £1,000 still saves £400" holds only where the engine
                // agrees the part below the threshold is all at that rate; taxed
                // interest or dividends at the top can put some of it at another
                // rate (ITA 2007 s12B, s16), and then the sentence is left out.
                // The sentence's floor is the higher-rate threshold, which
                // holds only when nothing but pay sits above it: interest or
                // dividends above it keep the 40% stretch short of the
                // threshold (ITA 2007 s16, s12A(4); walk R25 tax review).
                $onlyPayAbove = $this->math->higherRateSliceParts($user, $taxableIncome, $higherRateThreshold);
                $saysBelow = $reachesBelow && $atHigherRate
                    && $onlyPayAbove['interest_covered'] + $onlyPayAbove['interest_taxed'] + $onlyPayAbove['dividends'] < 1;
                $directLine = $atHigherRate
                    ? sprintf("Reduce your income tax at %d%% by £%s.\n\n", (int) round($higherRate * 100), number_format($directRelief))
                    : sprintf("Reduce the rest of your income tax by £%s.\n\n", number_format($directRelief));
                $paReclaimPct = (int) round($higherRate * $taperRate * 100);
                $effectivePct = (int) round($taperEffectiveRate * 100);
                $reclaimShare = $reachesBelow ? '' : sprintf(' (%d%% of your contribution)', $paReclaimPct);
                // Wording approved by CSJ 2026-10-01 (spec section 3.2). The
                // floor is the higher-rate threshold before any extension:
                // the card speaks in adjusted net income, which is already
                // net of Gift Aid and relief at source (ITA 2007 s58).
                $closing = $saysBelow
                    ? sprintf(
                        "Together that's £%s back this year. Income between £%s and £%s is taxed at %d%%. Below £%s, each £%s you pay in still saves £%s, down to £%s.",
                        number_format($totalSaving),
                        number_format((int) $taperThreshold),
                        number_format((int) $taperEnd),
                        $effectivePct,
                        number_format((int) $taperThreshold),
                        number_format(1000),
                        number_format((int) round(1000 * $higherRate)),
                        number_format((int) $this->math->bandThresholds()['higher']),
                    )
                    : sprintf(
                        "Together that's £%s back this year — income between £%s and £%s is taxed at %d%%.",
                        number_format($totalSaving),
                        number_format((int) $taperThreshold),
                        number_format((int) $taperEnd),
                        $effectivePct,
                    );

                $recommendations[] = new StrategyRecommendation(
                    type: 'pa_taper_rescue',
                    category: StrategyCategory::IncomeBand,
                    priority: StrategyPriority::High,
                    title: 'Reclaim your Personal Allowance with a pension contribution',
                    description: sprintf(
                        "For your income of £%s, a £%s pension contribution would:\n\n"
                        ."Reclaim £%s of your Personal Allowance, saving £%s%s.\n\n"
                        .'%s'
                        .'%s',
                        number_format((int) round($adjustedNetIncome)),
                        number_format($displayContribution),
                        number_format($paReclaimed),
                        number_format($paReclaimSaving),
                        $reclaimShare,
                        $directLine,
                        $closing,
                    ),
                    estimatedAnnualTaxSaved: (float) $totalSaving,
                    extra: [
                        'suggested_contribution' => (float) $displayContribution,
                        'effective_marginal_rate' => round($taperEffectiveRate, 4),
                        // The how-to's step for the part below the threshold.
                        'below_taper' => $saysBelow,
                        'higher_relief_rate' => $higherRate,
                        'higher_rate_threshold' => $this->math->bandThresholds()['higher'],
                    ],
                );
            }
        }

        // #2 — Additional-Rate Avoidance. The contribution covers the slice
        // above the additional-rate threshold, then the taper band, then the
        // higher-rate band down to its threshold (never below it: relief there
        // is only the basic rate). The saving is priced by the tax engine.
        if ($additionalRateThreshold > 0 && $taxableIncome > $additionalRateThreshold) {
            // Sized in TaxStrategyMath, shared with the /savetax funnel.
            [
                'contribution' => $contribution,
                'additional_slice' => $additionalSlice,
                'taper_slice' => $taperSlice,
            ] = $this->math->additionalRateAvoidanceContribution(
                $taxableIncome,
                $availableAA,
                ['higher' => $higherRateThreshold, 'additional' => $additionalRateThreshold],
            );
            $saving = $this->math->pensionContributionSaving($user, $contribution);

            if ($contribution > 0) {
                $recommendations[] = new StrategyRecommendation(
                    type: 'additional_rate_avoidance',
                    category: StrategyCategory::IncomeBand,
                    priority: StrategyPriority::High,
                    // Rates from tax config, never typed in (Rule 2).
                    title: sprintf('Shift income out of the %d%% additional-rate band', (int) round($this->math->bandRateForBand('additional') * 100)),
                    description: sprintf(
                        'Income above £%s is taxed at %d%%. A £%s pension contribution moves that slice into the %d%% band and reclaims part of your Personal Allowance, saving around £%s in tax this year.',
                        number_format((int) $additionalRateThreshold),
                        (int) round($this->math->bandRateForBand('additional') * 100),
                        number_format((int) $contribution),
                        (int) round($this->math->bandRateForBand('higher') * 100),
                        number_format((int) floor($saving)),
                    ),
                    estimatedAnnualTaxSaved: round($saving, 2),
                    extra: [
                        'suggested_contribution' => round($contribution, 2),
                        'additional_rate_slice' => round($additionalSlice, 2),
                        'taper_band_slice' => round($taperSlice, 2),
                    ],
                );
            }
        }

        return $recommendations;
    }
}
