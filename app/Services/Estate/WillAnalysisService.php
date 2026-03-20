<?php

declare(strict_types=1);

namespace App\Services\Estate;

use App\Models\Estate\Bequest;
use App\Models\Estate\Will;
use App\Models\User;
use App\Services\TaxConfigService;

/**
 * Will Analysis Service
 *
 * Analyzes wills for:
 * - Charitable bequest status (10% threshold for reduced IHT rate)
 * - Trust-triggering wishes detection
 * - Bequest totals and allocations
 */
class WillAnalysisService
{
    public function __construct(
        private readonly TaxConfigService $taxConfig
    ) {}

    /**
     * Analyze charitable bequests against the 10% threshold for reduced IHT rate
     *
     * The baseline for the 10% calculation is: Net Estate - NRB (RNRB is excluded)
     * If charitable giving >= 10% of baseline, the reduced 36% rate applies
     *
     * @param  User  $user  The user whose charitable bequests to analyze
     * @param  float  $netEstate  Total net estate value
     * @return array Analysis with status, amounts, and potential savings
     */
    public function analyzeCharitableBequests(User $user, float $netEstate): array
    {
        $ihtConfig = $this->taxConfig->getInheritanceTax();
        $nrb = $ihtConfig['nil_rate_band'];

        // Baseline calculation: Net Estate - NRB (RNRB is excluded per HMRC rules)
        $baseline = max(0, $netEstate - $nrb);
        $threshold = $baseline * 0.10;

        // Get total charitable bequests
        $charitableBequests = $this->getCharitableBequestTotal($user, $netEstate);

        if ($charitableBequests >= $threshold && $threshold > 0) {
            $status = $charitableBequests > $threshold * 1.01 ? 'above' : 'at';
            $effectiveRate = $ihtConfig['reduced_rate_charity'] ?? 0.36;
            $shortfall = 0;
            $excess = $charitableBequests - $threshold;
        } else {
            $status = 'below';
            $effectiveRate = $ihtConfig['standard_rate'];
            $shortfall = $threshold - $charitableBequests;
            $excess = 0;
        }

        // Potential saving = 4% of baseline (difference between 40% and 36%)
        $potentialSaving = $baseline * 0.04;

        return [
            'status' => $status,                    // 'below', 'at', 'above'
            'charitable_total' => round($charitableBequests, 2),
            'baseline' => round($baseline, 2),
            'threshold' => round($threshold, 2),
            'shortfall' => round($shortfall, 2),
            'excess' => round($excess, 2),
            'effective_rate' => $effectiveRate,
            'effective_rate_percent' => round($effectiveRate * 100, 0),
            'potential_saving' => $status === 'below' ? round($potentialSaving, 2) : 0,
            'current_saving' => $status !== 'below' ? round($potentialSaving, 2) : 0,
            'message' => $this->getCharitableStatusMessage($status, $shortfall, $excess, $threshold, $potentialSaving),
        ];
    }

    /**
     * Get total value of charitable bequests for a user
     *
     * Includes both percentage-based and specific amount bequests to charities
     *
     * @param  User  $user  The user to check
     * @param  float  $netEstate  Net estate for percentage calculations
     * @return float Total charitable bequest value
     */
    public function getCharitableBequestTotal(User $user, float $netEstate = 0): float
    {
        $will = Will::where('user_id', $user->id)->with('bequests')->first();

        if (! $will) {
            return 0;
        }

        $total = 0;

        foreach ($will->bequests as $bequest) {
            // Check if bequest is to a charity
            if (! $this->isCharitableBequest($bequest)) {
                continue;
            }

            if ($bequest->bequest_type === 'percentage' && $bequest->percentage_of_estate) {
                $total += $netEstate * ($bequest->percentage_of_estate / 100);
            } elseif ($bequest->bequest_type === 'specific' && $bequest->specific_amount) {
                $total += (float) $bequest->specific_amount;
            }
        }

        return $total;
    }

    /**
     * Detect trust-triggering wishes in will bequests and executor notes
     *
     * Scans bequest notes and executor instructions for patterns that suggest
     * trust structures should be recommended
     *
     * @param  Will  $will  The will to analyze
     * @return array Array of detected wish triggers with recommendations
     */
    public function detectTrustTriggeringWishes(Will $will): array
    {
        $triggers = [];
        $wishPatterns = $this->getWishPatterns();

        // Scan bequest notes and executor instructions
        $textToScan = collect($will->bequests)->pluck('notes')->filter()->join(' ')
            .' '.($will->executor_notes ?? '');

        // Normalize text for matching
        $textToScan = strtolower($textToScan);

        foreach ($wishPatterns as $key => $config) {
            foreach ($config['patterns'] as $pattern) {
                if (str_contains($textToScan, strtolower($pattern))) {
                    $triggers[] = [
                        'wish_type' => $key,
                        'matched_pattern' => $pattern,
                        'trust_type' => $config['trust_type'],
                        'description' => $config['description'],
                        'iht_treatment' => $config['iht_treatment'],
                        'recommendation' => "Consider creating a {$config['description']} to fulfil this wish",
                    ];
                    break; // One match per category is enough
                }
            }
        }

        return $triggers;
    }

    /**
     * Get all charitable bequests for a user
     *
     * @param  User  $user  The user to check
     * @return \Illuminate\Support\Collection Collection of charitable bequests
     */
    public function getCharitableBequests(User $user): \Illuminate\Support\Collection
    {
        $will = Will::where('user_id', $user->id)->with('bequests')->first();

        if (! $will) {
            return collect();
        }

        return $will->bequests->filter(fn ($bequest) => $this->isCharitableBequest($bequest));
    }

    /**
     * Check if a bequest is charitable
     *
     * @param  Bequest  $bequest  The bequest to check
     * @return bool True if charitable
     */
    private function isCharitableBequest(Bequest $bequest): bool
    {
        // Check beneficiary_type if set (new field)
        if (isset($bequest->beneficiary_type) && $bequest->beneficiary_type === 'charity') {
            return true;
        }

        // Check charity registration number
        if (! empty($bequest->charity_registration_number)) {
            return true;
        }

        // Check beneficiary name for charity indicators
        $name = strtolower($bequest->beneficiary_name ?? '');
        $charityIndicators = [
            'charity',
            'charitable',
            'foundation',
            'trust',
            'cancer',
            'heart',
            'hospice',
            'nspcc',
            'rspca',
            'oxfam',
            'red cross',
            'british heart',
            'macmillan',
            'marie curie',
            'shelter',
            'save the children',
            'unicef',
        ];

        foreach ($charityIndicators as $indicator) {
            if (str_contains($name, $indicator)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get wish patterns for trust detection
     *
     * @return array Pattern configuration
     */
    private function getWishPatterns(): array
    {
        return [
            'education_trust' => [
                'patterns' => ['education', 'school fees', 'university', 'college', 'school'],
                'trust_type' => 'bare_trust',
                'description' => 'Education Trust for Children',
                'iht_treatment' => 'Bare trust = PET, not CLT',
            ],
            'income_family' => [
                'patterns' => ['income for family', 'income for spouse', 'living expenses', 'income to my wife', 'income to my husband'],
                'trust_type' => 'interest_in_possession',
                'description' => 'Interest in Possession Trust',
                'iht_treatment' => 'Pre-2006 IIP = not relevant property',
            ],
            'income_children' => [
                'patterns' => ['income for child', 'income for kids', 'maintenance', 'income for my son', 'income for my daughter'],
                'trust_type' => 'discretionary',
                'description' => 'Discretionary Trust for Minors',
                'iht_treatment' => 'Relevant property - 10-year charges apply',
            ],
            'age_restriction' => [
                'patterns' => ['at age 25', 'when they reach', 'upon turning', 'at age 21', 'when older', 'at age 30'],
                'trust_type' => 'age_18_to_25',
                'description' => 'Age 18-25 Trust',
                'iht_treatment' => 'Special treatment - reduced exit charges',
            ],
            'asset_protection' => [
                'patterns' => ['protect from divorce', 'creditor protection', 'bankruptcy', 'protect assets', 'safeguard from'],
                'trust_type' => 'discretionary',
                'description' => 'Asset Protection Trust',
                'iht_treatment' => 'Relevant property - full charges apply',
            ],
            'special_needs' => [
                'patterns' => ['special needs', 'disability', 'disabled', 'vulnerable', 'care needs'],
                'trust_type' => 'disabled_person',
                'description' => "Disabled Person's Trust",
                'iht_treatment' => 'Exempt from periodic/exit charges',
            ],
            'business_succession' => [
                'patterns' => ['business to continue', 'company shares', 'business succession', 'keep the business running'],
                'trust_type' => 'business_property',
                'description' => 'Business Property Trust',
                'iht_treatment' => 'May qualify for BPR - IHT efficient',
            ],
            'property_management' => [
                'patterns' => ['property to be managed', 'rental income', 'let property', 'investment property managed'],
                'trust_type' => 'property_trust',
                'description' => 'Property Trust',
                'iht_treatment' => 'Relevant property - professional management',
            ],
        ];
    }

    /**
     * Generate message for charitable bequest status
     *
     * @param  string  $status  'below', 'at', or 'above'
     * @param  float  $shortfall  Amount below threshold
     * @param  float  $excess  Amount above threshold
     * @param  float  $threshold  10% threshold value
     * @param  float  $potentialSaving  Potential IHT saving
     * @return string Status message
     */
    private function getCharitableStatusMessage(
        string $status,
        float $shortfall,
        float $excess,
        float $threshold,
        float $potentialSaving
    ): string {
        $ihtConfig = $this->taxConfig->getInheritanceTax();
        $reducedRatePercent = round(((float) ($ihtConfig['reduced_rate_charity'] ?? 0.36)) * 100);

        return match ($status) {
            'above' => 'Your charitable bequests exceed the 10% threshold by £'.number_format($excess).'. You qualify for the reduced '.$reducedRatePercent.'% IHT rate, saving £'.number_format($potentialSaving).' in IHT.',
            'at' => 'Your charitable bequests meet the 10% threshold of £'.number_format($threshold).'. You qualify for the reduced '.$reducedRatePercent.'% IHT rate, saving £'.number_format($potentialSaving).' in IHT.',
            'below' => 'Your charitable bequests are £'.number_format($shortfall).' below the 10% threshold of £'.number_format($threshold).'. Increase charitable giving by £'.number_format($shortfall).' to qualify for the reduced '.$reducedRatePercent.'% rate and save £'.number_format($potentialSaving).' in IHT.',
            default => 'Unable to determine charitable bequest status.',
        };
    }
}
