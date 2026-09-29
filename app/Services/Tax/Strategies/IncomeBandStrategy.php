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
        // Where the 60% band ends: the allowance is gone £1 for every £2 over the
        // threshold, so at threshold + 2 × allowance. Adjusted net income is
        // already net of Gift Aid, so the band top is NOT extended by it the way
        // the additional-rate threshold is; the strip says the same figure.
        $taperEnd = $taperThreshold + 2 * (float) $income['personal_allowance'];

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
        if ($availableAA <= 0) {
            return [];
        }

        // M7 — source band rates from TaxConfigService rather than hardcoding.
        // The 60% effective rate in the PA-taper band is the higher rate
        // multiplied by 1.5 (every £1 earned = £0.40 tax + £0.50 PA reduction
        // taxed at higher rate = £0.40 + £0.20 = £0.60).
        $higherRate = $this->math->bandRateForBand('higher');
        $taperEffectiveRate = $higherRate * 1.5;

        $recommendations = [];

        // #1 — Personal Allowance Taper Rescue (60% effective rate band).
        // Effective only when contributing to drop income BELOW the taper
        // threshold, i.e. only the slice between £100k and the user's income
        // counts. Cap by both the in-band slice and the available AA.
        if ($adjustedNetIncome > $taperThreshold && $adjustedNetIncome <= $additionalRateThreshold) {
            // Shared with the /savetax funnel (TaxStrategyMath), so its promise
            // and this card size the contribution the same way.
            $displayContribution = (int) $this->math->taperRescueContribution($adjustedNetIncome, $availableAA);
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
                $paReclaimed = (int) ($displayContribution / 2);
                $paReclaimSaving = (int) round($paReclaimed * $higherRate);
                $totalSaving = (int) round($this->math->pensionContributionSaving($user, $displayContribution));
                $directRelief = max(0, $totalSaving - $paReclaimSaving);
                // The agreed wording names the rate; it only holds when the
                // engine agrees the relief is at that rate (not when the top
                // slice is dividends or allowance-covered interest).
                $directLine = $directRelief === (int) round($displayContribution * $higherRate)
                    ? sprintf("Reduce your income tax at %d%% by £%s.\n\n", (int) round($higherRate * 100), number_format($directRelief))
                    : sprintf("Reduce the rest of your income tax by £%s.\n\n", number_format($directRelief));
                $paReclaimPct = (int) round($higherRate * 50);
                $effectivePct = (int) round($taperEffectiveRate * 100);

                $recommendations[] = new StrategyRecommendation(
                    type: 'pa_taper_rescue',
                    category: StrategyCategory::IncomeBand,
                    priority: StrategyPriority::High,
                    title: 'Reclaim your Personal Allowance with a pension contribution',
                    description: sprintf(
                        "For your income of £%s, a £%s pension contribution would:\n\n"
                        ."Reclaim £%s of your Personal Allowance, saving £%s (%d%% of your contribution).\n\n"
                        .'%s'
                        ."Together that's £%s back this year — income between £%s and £%s is taxed at %d%%.",
                        number_format((int) round($adjustedNetIncome)),
                        number_format($displayContribution),
                        number_format($paReclaimed),
                        number_format($paReclaimSaving),
                        $paReclaimPct,
                        $directLine,
                        number_format($totalSaving),
                        number_format((int) $taperThreshold),
                        number_format((int) $taperEnd),
                        $effectivePct,
                    ),
                    estimatedAnnualTaxSaved: (float) $totalSaving,
                    extra: [
                        'suggested_contribution' => (float) $displayContribution,
                        'effective_marginal_rate' => round($taperEffectiveRate, 4),
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
