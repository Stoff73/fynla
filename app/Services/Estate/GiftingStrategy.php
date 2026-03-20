<?php

declare(strict_types=1);

namespace App\Services\Estate;

use App\Models\Estate\Gift;
use App\Models\Estate\IHTProfile;
use App\Services\TaxConfigService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * GiftingStrategy Service
 *
 * Analyzes and optimizes gifting strategies for IHT planning.
 * Handles PET analysis, annual exemptions, and strategy recommendations.
 */
class GiftingStrategy
{
    private array $ihtConfig;

    private array $giftingConfig;

    public function __construct(
        private ?TaxConfigService $taxConfig = null
    ) {
        // TaxConfigService is required - resolve from container if not provided
        if ($this->taxConfig === null) {
            $this->taxConfig = app(TaxConfigService::class);
        }

        $this->ihtConfig = $this->taxConfig->getInheritanceTax();
        $this->giftingConfig = $this->taxConfig->getGiftingExemptions();
    }

    /**
     * Analyze Potentially Exempt Transfers (PETs)
     *
     * @param  Collection  $gifts  Collection of Gift models
     * @return array PET analysis results
     */
    public function analyzePETs(Collection $gifts): array
    {
        $now = Carbon::now();

        // Filter to PET gifts within 7 years
        $activePets = $gifts->filter(function ($gift) use ($now) {
            if (($gift->gift_type ?? '') !== 'pet') {
                return false;
            }

            $giftDate = Carbon::parse($gift->gift_date);
            $yearsAgo = $giftDate->diffInYears($now);

            return $yearsAgo < 7;
        });

        $petsData = $activePets->map(function ($gift) use ($now) {
            $giftDate = Carbon::parse($gift->gift_date);
            $yearsAgo = (int) $giftDate->diffInYears($now);
            $yearsRemaining = 7 - $yearsAgo;

            return [
                'id' => $gift->id,
                'gift_date' => $gift->gift_date,
                'recipient' => $gift->recipient,
                'gift_value' => (float) ($gift->gift_value ?? 0),
                'years_ago' => $yearsAgo,
                'years_remaining' => max(0, $yearsRemaining),
                'taper_relief_applicable' => $yearsAgo >= 3,
            ];
        })->values();

        $totalValue = $activePets->sum(fn ($gift) => (float) ($gift->gift_value ?? 0));

        return [
            'active_pets_count' => $activePets->count(),
            'total_pet_value' => (float) $totalValue,
            'pets' => $petsData->toArray(),
        ];
    }

    /**
     * Calculate annual exemption available for a tax year
     *
     * @param  int  $userId  User ID
     * @param  string  $taxYear  Tax year (e.g., '2024')
     * @return float Available exemption including carry forward
     */
    public function calculateAnnualExemption(int $userId, string $taxYear): float
    {
        $annualExemption = (float) $this->giftingConfig['annual_exemption'];

        // Get tax year boundaries
        $startYear = (int) $taxYear;
        $taxYearStart = Carbon::createFromDate($startYear, 4, 6);
        $taxYearEnd = $taxYearStart->copy()->addYear()->subDay();

        // Get previous tax year boundaries for carry forward
        $prevTaxYearStart = $taxYearStart->copy()->subYear();
        $prevTaxYearEnd = $taxYearStart->copy()->subDay();

        // Get gifts in current tax year
        $currentYearGifts = Gift::where('user_id', $userId)
            ->whereBetween('gift_date', [$taxYearStart, $taxYearEnd])
            ->sum('gift_value');

        // Get gifts in previous tax year (for carry forward calculation)
        $previousYearGifts = Gift::where('user_id', $userId)
            ->whereBetween('gift_date', [$prevTaxYearStart, $prevTaxYearEnd])
            ->sum('gift_value');

        // Calculate carry forward (unused exemption from previous year)
        $carryForward = max(0, $annualExemption - (float) $previousYearGifts);

        // Calculate available exemption
        $totalAvailable = $annualExemption + $carryForward;
        $remaining = max(0, $totalAvailable - (float) $currentYearGifts);

        return $remaining;
    }

    /**
     * Identify and validate small gifts (£250 per recipient per year)
     *
     * @param  Collection  $gifts  Collection of Gift models
     * @return array Small gifts analysis
     */
    public function identifySmallGifts(Collection $gifts): array
    {
        $smallGiftsLimit = (float) $this->giftingConfig['small_gifts_limit'];

        // Filter to small gift type
        $smallGifts = $gifts->filter(fn ($gift) => ($gift->gift_type ?? '') === 'small_gift');

        // Group by recipient
        $byRecipient = $smallGifts->groupBy('recipient')->map(function ($recipientGifts, $recipient) use ($smallGiftsLimit) {
            $totalValue = $recipientGifts->sum(fn ($gift) => (float) ($gift->gift_value ?? 0));
            $isValid = $totalValue <= $smallGiftsLimit;

            return [
                'recipient' => $recipient,
                'total_value' => (float) $totalValue,
                'gift_count' => $recipientGifts->count(),
                'is_valid' => $isValid,
                'warning' => $isValid ? null : "Exceeds £250 limit for recipient: {$recipient}",
            ];
        })->values();

        $totalValue = $smallGifts->sum(fn ($gift) => (float) ($gift->gift_value ?? 0));

        return [
            'small_gifts_count' => $smallGifts->count(),
            'total_value' => (float) $totalValue,
            'by_recipient' => $byRecipient->toArray(),
        ];
    }

    /**
     * Calculate wedding/civil partnership gift allowance based on relationship
     *
     * @param  string  $relationship  Relationship to recipient
     * @return float Allowable gift amount
     */
    public function calculateMarriageGifts(string $relationship): float
    {
        $weddingGifts = $this->giftingConfig['wedding_gifts'];

        return match (strtolower($relationship)) {
            'child', 'son', 'daughter' => (float) $weddingGifts['child'],
            'grandchild', 'grandson', 'granddaughter', 'great_grandchild' => (float) $weddingGifts['grandchild_great_grandchild'],
            default => (float) $weddingGifts['other'],
        };
    }

    /**
     * Recommend optimal gifting strategy based on estate value and profile
     *
     * @param  float  $estateValue  Total estate value
     * @param  IHTProfile  $profile  User's IHT profile
     * @return array Gifting recommendations
     */
    public function recommendOptimalGiftingStrategy(float $estateValue, IHTProfile $profile): array
    {
        $nrb = (float) $this->ihtConfig['nil_rate_band'];
        $nrbFromSpouse = (float) ($profile->nrb_transferred_from_spouse ?? 0);
        $totalNrb = $nrb + $nrbFromSpouse;

        $ihtRate = (float) $this->ihtConfig['standard_rate'];

        // Calculate current IHT liability
        $taxableEstate = max(0, $estateValue - $totalNrb);
        $currentIHTLiability = $taxableEstate * $ihtRate;

        $recommendations = [];
        $priority = [];

        // 1. Annual Exemption recommendation
        $annualExemption = (float) $this->giftingConfig['annual_exemption'];
        $recommendations[] = [
            'strategy' => 'Annual Exemption',
            'description' => "Gift up to £{$annualExemption} per year tax-free",
            'potential_savings' => round($annualExemption * $ihtRate, 2),
        ];
        $priority[] = [
            'strategy' => 'Annual Exemption',
            'priority' => 1,
        ];

        // 2. Charitable Giving recommendation (if not already at 10%)
        $charitablePercent = (float) ($profile->charitable_giving_percent ?? 0);
        $reducedRate = (float) ($this->ihtConfig['reduced_rate_charity'] ?? 0.36);
        if ($charitablePercent < 10 && $currentIHTLiability > 0) {
            $standardRatePercent = round($ihtRate * 100);
            $reducedRatePercent = round($reducedRate * 100);
            $recommendations[] = [
                'strategy' => 'Charitable Giving',
                'description' => "Leave 10% to charity to reduce IHT rate from {$standardRatePercent}% to {$reducedRatePercent}%",
                'potential_savings' => round($taxableEstate * ($ihtRate - $reducedRate), 2),
            ];
            $priority[] = [
                'strategy' => 'Charitable Giving',
                'priority' => 2,
            ];
        }

        // 3. PET recommendation if estate is above NRB
        if ($currentIHTLiability > 0) {
            $recommendations[] = [
                'strategy' => 'Potentially Exempt Transfers (PETs)',
                'description' => 'Make larger gifts that become exempt after 7 years',
                'potential_savings' => round($currentIHTLiability, 2),
            ];
            $priority[] = [
                'strategy' => 'Potentially Exempt Transfers',
                'priority' => 3,
            ];
        }

        return [
            'estate_value' => round($estateValue, 2),
            'nil_rate_band' => round($totalNrb, 2),
            'taxable_estate' => round($taxableEstate, 2),
            'current_iht_liability' => round($currentIHTLiability, 2),
            'recommendations' => $recommendations,
            'priority' => $priority,
        ];
    }
}
