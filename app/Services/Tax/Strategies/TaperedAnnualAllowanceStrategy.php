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
 * Strategy #14 — Tapered Annual Allowance warning.
 *
 * Fires when BOTH HMRC tapered-AA gates are breached:
 *   - threshold income > £200,000  (employment + bonus + other taxable
 *     income, no pension addback)
 *   - adjusted income  > £260,000  (threshold income + employer pension
 *     contributions added back)
 *
 * Either gate alone returns []. The dual-gate is HMRC's actual rule —
 * users above £260k adjusted but below £200k threshold (rare, e.g. heavy
 * employer contributions on modest salary) are NOT tapered.
 *
 * Tapered AA = max(annual_allowance − taper_rate × (adjusted − £260k),
 *                  minimum_allowance £10k).
 *
 * Surfaces as Warning category (sortWeight 0 — first on the dashboard)
 * and High priority because contributing the untapered AA when the taper
 * applies triggers an HMRC Annual Allowance charge at the user's marginal
 * rate. estimated_annual_tax_saved carries that avoided charge.
 */
final class TaperedAnnualAllowanceStrategy implements TaxStrategy
{
    public function __construct(
        private readonly TaxStrategyMath $math,
        private readonly TaxConfigService $taxConfig,
    ) {}

    public function generate(TaxStrategyContext $context): array
    {
        $user = $context->user;
        $pension = $this->taxConfig->getPensionAllowances();
        $taper = $pension['tapered_annual_allowance'] ?? [];

        $thresholdGate = (float) ($taper['threshold_income'] ?? 200000);
        $adjustedGate = (float) ($taper['adjusted_income_threshold']
            ?? $taper['adjusted_income']
            ?? 260000);
        $minimumAllowance = (float) ($taper['minimum_allowance'] ?? 10000);
        $taperRate = (float) ($taper['taper_rate'] ?? 0.5);
        $annualAllowance = (float) ($pension['annual_allowance'] ?? 60000);

        // Short-circuit on threshold first to avoid the employer-pension
        // DB query for users who can't possibly breach the dual gate.
        $threshold = $this->math->thresholdIncomeFor($user);
        if ($threshold <= $thresholdGate) {
            return [];
        }

        $adjusted = $threshold + $this->math->employerPensionContributionsFor($user);
        if ($adjusted <= $adjustedGate) {
            return [];
        }

        $excessAdjusted = $adjusted - $adjustedGate;
        $taperedAa = max($minimumAllowance, $annualAllowance - $taperRate * $excessAdjusted);
        $aaReduction = $annualAllowance - $taperedAa;

        if ($aaReduction <= 0) {
            return [];
        }

        $marginalRate = $this->math->bandRateFor($user);
        $avoidedCharge = $aaReduction * $marginalRate;

        return [new StrategyRecommendation(
            type: 'tapered_annual_allowance',
            category: StrategyCategory::Warning,
            priority: StrategyPriority::High,
            title: sprintf(
                'Your Pension Annual Allowance is tapered to £%s',
                number_format((int) round($taperedAa / 1000) * 1000),
            ),
            description: sprintf(
                'Your adjusted income of £%s exceeds the £%s tapered Annual Allowance threshold and your threshold income of £%s exceeds £%s, so HMRC reduces your Pension Annual Allowance by £1 for every £2 over £%s — down to £%s this year (floor £%s). Contributing the standard £%s allowance would trigger an Annual Allowance charge of around £%s at your marginal rate.',
                number_format((int) $adjusted),
                number_format((int) $adjustedGate),
                number_format((int) $threshold),
                number_format((int) $thresholdGate),
                number_format((int) $adjustedGate),
                number_format((int) round($taperedAa / 1000) * 1000),
                number_format((int) $minimumAllowance),
                number_format((int) $annualAllowance),
                number_format((int) round($avoidedCharge)),
            ),
            estimatedAnnualTaxSaved: round($avoidedCharge, 2),
            extra: [
                'threshold_income' => round($threshold, 2),
                'adjusted_income' => round($adjusted, 2),
                'threshold_income_gate' => $thresholdGate,
                'adjusted_income_gate' => $adjustedGate,
                'standard_annual_allowance' => $annualAllowance,
                'tapered_annual_allowance' => round($taperedAa, 2),
                'minimum_allowance' => $minimumAllowance,
                'taper_rate' => $taperRate,
                'aa_reduction' => round($aaReduction, 2),
                'marginal_rate' => $marginalRate,
            ],
        )];
    }
}
