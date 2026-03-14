<?php

declare(strict_types=1);

namespace App\Services\Estate;

use App\Models\Estate\Gift;
use App\Models\Estate\IHTProfile;
use App\Models\User;
use App\Services\TaxConfigService;
use Carbon\Carbon;

class SpouseNRBTrackerService
{
    public function __construct(
        private readonly TaxConfigService $taxConfig
    ) {}

    /**
     * Calculate how much NRB the spouse has used from their own estate
     *
     * This determines how much transferable NRB the surviving spouse will inherit.
     * If spouse used £0 of their NRB, surviving spouse gets full £325k transfer (total £650k NRB).
     * If spouse used £100k of their NRB, surviving spouse gets £225k transfer (total £550k NRB).
     *
     * @param  User  $spouse  The deceased spouse
     * @return array NRB usage breakdown
     */
    public function calculateSpouseNRBUsage(User $spouse): array
    {
        $ihtConfig = $this->taxConfig->getInheritanceTax();
        $nrb = $ihtConfig['nil_rate_band']; // £325,000

        // Get spouse's IHT profile
        $spouseIHTProfile = IHTProfile::where('user_id', $spouse->id)->first();

        // Get spouse's gifts within 7 years of death (both PETs and CLTs consume NRB)
        $spouseGifts = Gift::where('user_id', $spouse->id)->get();

        // Filter gifts within 7 years (assuming death is now for calculation purposes)
        // Both PETs and CLTs consume the Nil Rate Band
        $recentGifts = $spouseGifts->filter(function ($gift) {
            $yearsAgo = Carbon::now()->diffInYears($gift->gift_date);

            return $yearsAgo < 7 && in_array($gift->gift_type, ['pet', 'clt'], true);
        })->sortBy('gift_date');

        // 14-year rule: CLTs made 7-14 years before death also reduce available NRB
        $historicalCLTs = $spouseGifts->filter(function ($gift) {
            $yearsAgo = Carbon::now()->diffInYears($gift->gift_date);

            return $yearsAgo >= 7 && $yearsAgo < 14 && $gift->gift_type === 'clt';
        });
        $historicalCLTValue = $historicalCLTs->sum('gift_value');

        // Calculate cumulative gift value that would use NRB
        // Historical CLTs (7-14 years) consume NRB first, then recent gifts
        $totalGiftValue = $recentGifts->sum('gift_value') + $historicalCLTValue;
        $nrbUsedByGifts = min($nrb, $totalGiftValue);

        // Get spouse's estate assets (would need to check all assets)
        // For simplicity, we'll check if they have an IHT profile with transferred NRB
        $spouseNRBTransferredIn = $spouseIHTProfile->nrb_transferred_from_spouse ?? 0;

        // Calculate total NRB the spouse had available
        $spouseTotalNRB = $nrb + $spouseNRBTransferredIn;

        // Calculate how much of spouse's OWN NRB was used
        // (We only transfer unused portion of their original NRB, not what they inherited)
        $spouseOwnNRBUsed = min($nrb, $nrbUsedByGifts);

        // Calculate transferable amount (unused portion of spouse's own NRB)
        $transferableNRB = $nrb - $spouseOwnNRBUsed;

        // Percentage of spouse's NRB that was unused
        $unusedPercentage = ($transferableNRB / $nrb) * 100;

        return [
            'spouse_name' => $spouse->name,
            'spouse_id' => $spouse->id,
            'spouse_original_nrb' => round($nrb, 2),
            'spouse_nrb_transferred_in' => round($spouseNRBTransferredIn, 2),
            'spouse_total_nrb_available' => round($spouseTotalNRB, 2),
            'spouse_gifts_within_7_years' => round($totalGiftValue, 2),
            'spouse_nrb_used_by_gifts' => round($nrbUsedByGifts, 2),
            'spouse_own_nrb_used' => round($spouseOwnNRBUsed, 2),
            'transferable_nrb_to_survivor' => round($transferableNRB, 2),
            'transferable_percentage' => round($unusedPercentage, 2),
            'gift_count' => $recentGifts->count(),
            'gifts' => $recentGifts->map(function ($gift) {
                return [
                    'gift_date' => $gift->gift_date->format('Y-m-d'),
                    'recipient' => $gift->recipient,
                    'value' => round($gift->gift_value, 2),
                    'type' => $gift->gift_type,
                ];
            })->toArray(),
        ];
    }

    /**
     * Calculate survivor's total NRB including transferred amount
     *
     * @param  User  $survivor  The surviving spouse
     * @param  User  $deceased  The deceased spouse
     * @return array Total NRB calculation
     */
    public function calculateSurvivorTotalNRB(User $survivor, User $deceased): array
    {
        $ihtConfig = $this->taxConfig->getInheritanceTax();
        $nrb = $ihtConfig['nil_rate_band'];

        // Get deceased spouse's NRB usage
        $deceasedNRBUsage = $this->calculateSpouseNRBUsage($deceased);

        // Survivor's own NRB
        $survivorOwnNRB = $nrb;

        // NRB transferred from deceased spouse
        $transferredNRB = $deceasedNRBUsage['transferable_nrb_to_survivor'];

        // Total NRB available to survivor
        $survivorTotalNRB = $survivorOwnNRB + $transferredNRB;

        return [
            'survivor_name' => $survivor->name,
            'survivor_own_nrb' => round($survivorOwnNRB, 2),
            'deceased_spouse_name' => $deceased->name,
            'transferred_nrb_from_deceased' => round($transferredNRB, 2),
            'transferred_percentage' => round($deceasedNRBUsage['transferable_percentage'], 2),
            'survivor_total_nrb' => round($survivorTotalNRB, 2),
            'deceased_nrb_details' => $deceasedNRBUsage,
        ];
    }

    /**
     * Check if full NRB transfer is available (spouse used none of their NRB)
     *
     * @param  User  $spouse  The deceased spouse
     * @return bool True if full £325k is transferable
     */
    public function isFullNRBTransferable(User $spouse): bool
    {
        $usage = $this->calculateSpouseNRBUsage($spouse);

        return $usage['spouse_own_nrb_used'] == 0;
    }

    /**
     * Get RNRB transferability check
     *
     * RNRB can also be transferred between spouses (up to £175k)
     *
     * @param  User  $deceased  The deceased spouse
     * @return array RNRB transfer details
     */
    public function calculateRNRBTransfer(User $deceased): array
    {
        $ihtConfig = $this->taxConfig->getInheritanceTax();
        $rnrb = $ihtConfig['residence_nil_rate_band']; // £175,000

        $deceasedIHTProfile = IHTProfile::where('user_id', $deceased->id)->first();

        // Check if deceased owned a home
        $deceasedOwnedHome = $deceasedIHTProfile->own_home ?? false;

        // If deceased owned a home and left it to direct descendants, they may have used RNRB
        // For simplicity, we'll assume if they owned a home, RNRB was used
        // In reality, you'd need to check the will and beneficiaries

        $rnrbUsed = $deceasedOwnedHome ? $rnrb : 0;
        $transferableRNRB = $rnrb - $rnrbUsed;

        return [
            'deceased_owned_home' => $deceasedOwnedHome,
            'rnrb_available' => round($rnrb, 2),
            'rnrb_used_by_deceased' => round($rnrbUsed, 2),
            'transferable_rnrb' => round($transferableRNRB, 2),
            'note' => $deceasedOwnedHome
                ? 'Deceased owned home - RNRB may have been used depending on beneficiaries'
                : 'Deceased did not own home - full RNRB transferable',
        ];
    }
}
