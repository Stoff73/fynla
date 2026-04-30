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
        $taperThreshold = (float) ($income['personal_allowance_taper_threshold'] ?? 100000);
        $personalAllowance = (float) ($income['personal_allowance'] ?? 12570);
        $additionalRateThreshold = $this->math->bandThresholds()['additional'] ?: 125140;

        $taxableIncome = $this->math->taxableIncomeFor($user);
        $availableAA = $this->math->availableAnnualAllowance($user, $overrides);
        if ($availableAA <= 0) {
            return [];
        }

        $recommendations = [];

        // #1 — Personal Allowance Taper Rescue (60% effective rate band).
        // Effective only when contributing to drop income BELOW the taper
        // threshold, i.e. only the slice between £100k and the user's income
        // counts. Cap by both the in-band slice and the available AA.
        if ($taxableIncome > $taperThreshold && $taxableIncome <= $additionalRateThreshold) {
            $inBandSlice = $taxableIncome - $taperThreshold;
            $contribution = min($inBandSlice, $availableAA);
            if ($contribution > 0) {
                $saving = $contribution * 0.60;
                $recommendations[] = new StrategyRecommendation(
                    type: 'pa_taper_rescue',
                    category: StrategyCategory::IncomeBand,
                    priority: StrategyPriority::High,
                    title: 'Reclaim your Personal Allowance with a pension contribution',
                    description: sprintf(
                        'Income between £%s and £%s is taxed at 60%% because the Personal Allowance tapers in this band. A £%s pension contribution drops you below £%s and saves around £%s in tax this year.',
                        number_format((int) $taperThreshold),
                        number_format((int) $additionalRateThreshold),
                        number_format((int) round($contribution / 100) * 100),
                        number_format((int) $taperThreshold),
                        number_format((int) round($saving)),
                    ),
                    estimatedAnnualTaxSaved: round($saving, 2),
                    extra: [
                        'suggested_contribution' => round($contribution, 2),
                        'effective_marginal_rate' => 0.60,
                    ],
                );
            }
        }

        // #2 — Additional-Rate Avoidance (45% → 40%/60% bands).
        // Piecewise saving: top slice 45 → 40 (5pp), middle slice 40 → 60 swing
        // (gain), but the gain only materialises when contribution dips into
        // the £100k-£125,140 band. Approximate as: above-AR slice × 5pp +
        // continuation into taper band × 60pp differential vs nothing.
        if ($taxableIncome > $additionalRateThreshold) {
            $additionalSlice = min($taxableIncome - $additionalRateThreshold, $availableAA);
            $remaining = max(0, $availableAA - $additionalSlice);
            $taperSlice = min($remaining, $additionalRateThreshold - $taperThreshold);
            $remainingAfterTaper = max(0, $remaining - $taperSlice);
            $belowTaperSlice = min($remainingAfterTaper, max(0, $taperThreshold - max(0, $taxableIncome - $availableAA)));

            $saving = ($additionalSlice * 0.45) + ($taperSlice * 0.60) + ($belowTaperSlice * 0.40);
            $contribution = $additionalSlice + $taperSlice + $belowTaperSlice;

            if ($contribution > 0) {
                $recommendations[] = new StrategyRecommendation(
                    type: 'additional_rate_avoidance',
                    category: StrategyCategory::IncomeBand,
                    priority: StrategyPriority::High,
                    title: 'Shift income out of the 45% additional-rate band',
                    description: sprintf(
                        'Income above £%s is taxed at 45%%. A £%s pension contribution moves that slice into the 40%% band and reclaims part of your Personal Allowance, saving around £%s in tax this year.',
                        number_format((int) $additionalRateThreshold),
                        number_format((int) round($contribution / 100) * 100),
                        number_format((int) round($saving)),
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
