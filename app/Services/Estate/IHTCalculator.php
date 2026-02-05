<?php

declare(strict_types=1);

namespace App\Services\Estate;

use App\Models\Estate\Gift;
use App\Models\Estate\IHTProfile;
use App\Services\TaxConfigService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * IHT Calculator Service
 *
 * Handles Inheritance Tax calculations including:
 * - NRB (Nil Rate Band) and RNRB (Residence Nil Rate Band)
 * - Spouse NRB transfers
 * - Charitable giving reductions
 * - PET (Potentially Exempt Transfer) liability
 * - Taper relief calculations
 */
class IHTCalculator
{
    private array $ihtConfig;

    public function __construct(
        private readonly TaxConfigService $taxConfig
    ) {
        $this->ihtConfig = $this->taxConfig->getInheritanceTax();
    }

    /**
     * Calculate IHT liability for an estate
     */
    public function calculateIHTLiability(Collection $assets, IHTProfile $profile): array
    {
        $grossEstateValue = $assets->sum(fn ($asset) => (float) ($asset->current_value ?? 0));

        // Calculate NRB
        $nrb = (float) $this->ihtConfig['nil_rate_band'];
        $nrbFromSpouse = (float) ($profile->nrb_transferred_from_spouse ?? 0);
        $totalNrb = $nrb + $nrbFromSpouse;

        // Calculate RNRB
        $rnrbEligible = $this->checkRNRBEligibility($profile, $assets);
        $rnrb = 0.0;

        if ($rnrbEligible) {
            $rnrb = (float) $this->ihtConfig['residence_nil_rate_band'];

            // Apply RNRB taper for estates over £2m
            $taperThreshold = (float) $this->ihtConfig['rnrb_taper_threshold'];
            if ($grossEstateValue > $taperThreshold) {
                $excessAmount = $grossEstateValue - $taperThreshold;
                $taperRate = (float) $this->ihtConfig['rnrb_taper_rate'];
                $rnrbReduction = $excessAmount * $taperRate;
                $rnrb = max(0, $rnrb - $rnrbReduction);
            }
        }

        // Calculate total allowance
        $totalAllowance = $totalNrb + $rnrb;

        // Calculate taxable estate
        $taxableEstate = (float) max(0, $grossEstateValue - $totalAllowance);

        // Determine IHT rate (charitable reduction if applicable)
        $charitablePercent = (float) ($profile->charitable_giving_percent ?? 0);
        $ihtRate = $this->calculateCharitableReduction($grossEstateValue, $charitablePercent);

        // Calculate IHT liability
        $ihtLiability = $taxableEstate * $ihtRate;

        return [
            'gross_estate_value' => $grossEstateValue,
            'nrb' => $nrb,
            'nrb_from_spouse' => $nrbFromSpouse,
            'total_nrb' => $totalNrb,
            'rnrb' => $rnrb,
            'rnrb_eligible' => $rnrbEligible,
            'total_allowance' => $totalAllowance,
            'taxable_estate' => $taxableEstate,
            'iht_rate' => $ihtRate,
            'iht_liability' => $ihtLiability,
        ];
    }

    /**
     * Check if estate is eligible for Residence Nil Rate Band
     */
    public function checkRNRBEligibility(IHTProfile $profile, Collection $assets): bool
    {
        // Must own a home with value > 0
        if (! ($profile->own_home ?? false)) {
            return false;
        }

        if (($profile->home_value ?? 0) <= 0) {
            return false;
        }

        return true;
    }

    /**
     * Calculate charitable reduction rate
     *
     * If 10% or more of estate is left to charity, IHT rate reduces from 40% to 36%
     */
    public function calculateCharitableReduction(float $estateValue, float $charitablePercent): float
    {
        $standardRate = (float) $this->ihtConfig['standard_rate'];
        $reducedRate = (float) $this->ihtConfig['reduced_rate_charity'];

        if ($charitablePercent >= 10) {
            return $reducedRate;
        }

        return $standardRate;
    }

    /**
     * Apply taper relief to a gift based on how long ago it was made
     *
     * Gifts made 3-7 years before death may qualify for taper relief
     */
    public function applyTaperRelief(Gift $gift): float
    {
        $giftDate = Carbon::parse($gift->gift_date);
        $yearsAgo = $giftDate->diffInYears(Carbon::now());
        $giftValue = (float) ($gift->gift_value ?? 0);

        // Gifts over 7 years old are fully exempt
        if ($yearsAgo >= 7) {
            return 0.0;
        }

        // Get taper relief rates from config
        $taperRelief = $this->ihtConfig['potentially_exempt_transfers']['taper_relief'] ?? [];

        // Find applicable rate based on years
        foreach ($taperRelief as $bracket) {
            if ($yearsAgo < $bracket['years']) {
                return $giftValue * $bracket['rate'];
            }
        }

        // Default to full IHT rate if within 3 years
        return $giftValue * (float) $this->ihtConfig['standard_rate'];
    }

    /**
     * Calculate total PET (Potentially Exempt Transfer) liability
     *
     * PETs are gifts that become chargeable if donor dies within 7 years
     */
    public function calculatePETLiability(Collection $gifts): array
    {
        $nrb = (float) $this->ihtConfig['nil_rate_band'];

        // Filter to only PET gifts within 7 years
        $eligibleGifts = $gifts->filter(function ($gift) {
            if ($gift->gift_type !== 'pet') {
                return false;
            }

            $giftDate = Carbon::parse($gift->gift_date);
            $yearsAgo = $giftDate->diffInYears(Carbon::now());

            return $yearsAgo < 7;
        })->sortBy('gift_date'); // Sort oldest first (for NRB allocation)

        $totalGiftValue = (float) $eligibleGifts->sum(fn ($gift) => (float) ($gift->gift_value ?? 0));
        $giftCount = (int) $eligibleGifts->count();

        // Calculate which gifts exceed NRB
        $runningTotal = 0;
        $chargeableGifts = [];

        foreach ($eligibleGifts as $gift) {
            $giftValue = (float) ($gift->gift_value ?? 0);
            $runningTotal += $giftValue;

            // Only gifts that push total over NRB are chargeable
            if ($runningTotal > $nrb) {
                $taxableAmount = $runningTotal - $nrb;
                $thisGiftTaxable = min($giftValue, $taxableAmount);
                $runningTotal = $nrb; // Reset so we don't double-count

                $chargeableGifts[] = [
                    'id' => $gift->id,
                    'recipient' => $gift->recipient,
                    'gift_date' => $gift->gift_date,
                    'gift_value' => $giftValue,
                    'taxable_amount' => $thisGiftTaxable,
                    'tax_due' => $this->applyTaperRelief($gift),
                ];
            }
        }

        return [
            'total_gift_value' => $totalGiftValue,
            'gift_count' => $giftCount,
            'gifts' => $chargeableGifts,
        ];
    }
}
