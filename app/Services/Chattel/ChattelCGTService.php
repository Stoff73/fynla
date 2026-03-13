<?php

declare(strict_types=1);

namespace App\Services\Chattel;

use App\Models\Chattel;
use App\Models\User;
use App\Services\TaxConfigService;

/**
 * Capital Gains Tax calculation service for chattels (personal property)
 *
 * Implements UK CGT rules for chattels including:
 * - £6,000 threshold exemption
 * - Marginal relief formula for proceeds £6,000-£15,000
 * - Wasting asset exemption (predictable life <= 50 years)
 * - Non-residential CGT rates (10% basic / 20% higher)
 */
class ChattelCGTService
{
    private const CHATTEL_THRESHOLD = 6000;

    private const MARGINAL_RELIEF_MULTIPLIER = 5 / 3;

    public function __construct(private readonly TaxConfigService $taxConfig) {}

    /**
     * Calculate CGT for chattel disposal
     *
     * @return array CGT calculation breakdown
     */
    public function calculateCGT(
        Chattel $chattel,
        float $disposalPrice,
        float $disposalCosts,
        User $user
    ): array {
        // Check if wasting asset (exempt from CGT)
        if ($this->isWastingAsset($chattel)) {
            return [
                'is_exempt' => true,
                'exemption_reason' => 'Wasting asset (predictable life 50 years or less)',
                'chattel_type' => $chattel->chattel_type,
                'cgt_liability' => 0,
                'disposal_price' => $disposalPrice,
                'disposal_costs' => $disposalCosts,
            ];
        }

        // Check £6,000 exemption threshold
        if ($disposalPrice <= self::CHATTEL_THRESHOLD) {
            return [
                'is_exempt' => true,
                'exemption_reason' => 'Disposal proceeds do not exceed £6,000 threshold',
                'chattel_type' => $chattel->chattel_type,
                'cgt_liability' => 0,
                'disposal_price' => $disposalPrice,
                'disposal_costs' => $disposalCosts,
                'threshold' => self::CHATTEL_THRESHOLD,
            ];
        }

        // Calculate raw gain
        $acquisitionCost = (float) ($chattel->purchase_price ?? 0);
        $rawGain = $disposalPrice - $acquisitionCost - $disposalCosts;

        // If loss, handle separately
        if ($rawGain < 0) {
            return $this->calculateLoss($chattel, $disposalPrice, $disposalCosts, $acquisitionCost, $rawGain);
        }

        // Apply marginal relief if applicable
        // Maximum gain = (proceeds - £6,000) × 5/3
        $marginalReliefMaxGain = ($disposalPrice - self::CHATTEL_THRESHOLD) * self::MARGINAL_RELIEF_MULTIPLIER;
        $marginalReliefApplied = $marginalReliefMaxGain < $rawGain;
        $taxableGainBeforeExemption = $marginalReliefApplied ? $marginalReliefMaxGain : $rawGain;

        // Get CGT configuration
        $cgtConfig = $this->taxConfig->getCapitalGainsTax();
        $annualExemptAmount = $cgtConfig['annual_exempt_amount'] ?? 3000;

        // Apply annual exempt amount
        $taxableGain = max(0, $taxableGainBeforeExemption - $annualExemptAmount);

        // Determine CGT rate based on user's income (non-residential rates)
        $cgtRate = $this->determineCGTRate($user, $cgtConfig);
        $cgtLiability = $taxableGain * $cgtRate;

        $effectiveRate = $rawGain > 0 ? ($cgtLiability / $rawGain) * 100 : 0;

        return [
            'is_exempt' => false,
            'exemption_reason' => null,
            'chattel_type' => $chattel->chattel_type,
            'disposal_price' => $disposalPrice,
            'acquisition_cost' => $acquisitionCost,
            'disposal_costs' => $disposalCosts,
            'raw_gain' => round($rawGain, 2),
            'marginal_relief_applied' => $marginalReliefApplied,
            'marginal_relief_max_gain' => round($marginalReliefMaxGain, 2),
            'taxable_gain_before_exemption' => round($taxableGainBeforeExemption, 2),
            'annual_exempt_amount' => $annualExemptAmount,
            'taxable_gain' => round($taxableGain, 2),
            'cgt_rate' => round($cgtRate * 100, 1),
            'cgt_liability' => round($cgtLiability, 2),
            'effective_rate' => round($effectiveRate, 2),
            'threshold' => self::CHATTEL_THRESHOLD,
        ];
    }

    /**
     * Check if chattel is a wasting asset (CGT exempt)
     *
     * Wasting assets have a predictable life of 50 years or less.
     * Vehicles are always wasting assets.
     */
    public function isWastingAsset(Chattel $chattel): bool
    {
        // Vehicles are always wasting assets
        if ($chattel->chattel_type === 'vehicle') {
            return true;
        }

        // Watches and clocks with moving parts are typically wasting assets
        // but we'd need a flag to identify these - for now just vehicles

        return false;
    }

    /**
     * Calculate loss for chattel disposal
     *
     * When disposal proceeds are below £6,000, the loss is calculated
     * by treating the proceeds as £6,000 (restricting the loss)
     */
    private function calculateLoss(
        Chattel $chattel,
        float $disposalPrice,
        float $disposalCosts,
        float $acquisitionCost,
        float $actualLoss
    ): array {
        // If proceeds < £6,000, calculate loss as if proceeds were £6,000
        $adjustedProceeds = $disposalPrice < self::CHATTEL_THRESHOLD
            ? self::CHATTEL_THRESHOLD
            : $disposalPrice;

        $adjustedLoss = $adjustedProceeds - $acquisitionCost - $disposalCosts;
        $allowableLoss = min(0, $adjustedLoss);

        return [
            'is_exempt' => false,
            'is_loss' => true,
            'exemption_reason' => null,
            'chattel_type' => $chattel->chattel_type,
            'disposal_price' => $disposalPrice,
            'acquisition_cost' => $acquisitionCost,
            'disposal_costs' => $disposalCosts,
            'actual_loss' => round($actualLoss, 2),
            'loss_restriction_applied' => $disposalPrice < self::CHATTEL_THRESHOLD,
            'adjusted_proceeds' => $adjustedProceeds,
            'allowable_loss' => round($allowableLoss, 2),
            'cgt_liability' => 0,
            'threshold' => self::CHATTEL_THRESHOLD,
        ];
    }

    /**
     * Determine CGT rate based on user's income
     *
     * Uses non-residential rates:
     * - Basic rate taxpayer: 10%
     * - Higher/additional rate taxpayer: 20%
     */
    private function determineCGTRate(User $user, array $cgtConfig): float
    {
        $incomeTaxConfig = $this->taxConfig->getIncomeTax();

        $totalIncome = (float) ($user->annual_employment_income ?? 0) +
            (float) ($user->annual_self_employment_income ?? 0) +
            (float) ($user->annual_rental_income ?? 0) +
            (float) ($user->annual_dividend_income ?? 0) +
            (float) ($user->annual_other_income ?? 0);

        $personalAllowance = $incomeTaxConfig['personal_allowance'];
        $basicRateBand = (float) ($incomeTaxConfig['bands'][0]['max'] ?? 37700);
        $basicRateThreshold = $personalAllowance + $basicRateBand;

        // Use non-residential CGT rates (not property rates)
        $basicRate = $cgtConfig['basic_rate'] ?? 0.10;
        $higherRate = $cgtConfig['higher_rate'] ?? 0.20;

        return $totalIncome > $basicRateThreshold ? $higherRate : $basicRate;
    }

    /**
     * Check if chattel would be CGT exempt on disposal
     *
     * @param  float  $estimatedDisposalPrice  Expected sale price
     */
    public function wouldBeExempt(Chattel $chattel, float $estimatedDisposalPrice): array
    {
        if ($this->isWastingAsset($chattel)) {
            return [
                'exempt' => true,
                'reason' => 'Wasting asset - no CGT applies regardless of value',
            ];
        }

        if ($estimatedDisposalPrice <= self::CHATTEL_THRESHOLD) {
            return [
                'exempt' => true,
                'reason' => 'Disposal proceeds would not exceed £6,000 threshold',
            ];
        }

        return [
            'exempt' => false,
            'reason' => 'CGT may apply - use calculator for detailed breakdown',
        ];
    }
}
