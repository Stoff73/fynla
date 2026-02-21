<?php

declare(strict_types=1);

namespace App\Services\Estate;

use App\Models\Estate\Trust;

class TrustValuationService
{
    /**
     * Calculate the IHT value of this trust (value that counts toward estate).
     */
    public function calculateIHTValue(Trust $trust): float
    {
        switch ($trust->trust_type) {
            case 'bare':
                // Bare trusts - beneficiary absolutely entitled, counts in beneficiary's estate
                return 0;

            case 'discounted_gift':
                // Discounted gift trust - only retained income stream counts
                return $trust->discount_amount ?? 0;

            case 'loan':
                // Loan trust - outstanding loan counts in estate, growth doesn't
                return $trust->loan_amount ?? 0;

            case 'life_insurance':
                // Life insurance in trust - outside estate
                return 0;

            case 'interest_in_possession':
                // Depends if qualifying or non-qualifying - simplified: assume qualifying
                // Qualifying IIP - counts in life tenant's estate
                return $trust->current_value;

            case 'discretionary':
            case 'accumulation_maintenance':
                // Relevant property trusts - outside settlor's estate but subject to periodic charges
                return 0;

            default:
                return 0;
        }
    }

    /**
     * Check if trust is a relevant property trust (subject to 10-year charges).
     */
    public function isRelevantPropertyTrust(Trust $trust): bool
    {
        return in_array($trust->trust_type, [
            'discretionary',
            'accumulation_maintenance',
        ]) || $trust->is_relevant_property_trust;
    }
}
