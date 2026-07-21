<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Builds the curated, frontend-shaped tax-year snapshot the Vuex
 * `taxConfig` store hydrates from. The same projection is served from
 * two HTTP routes:
 *
 *   - `/api/tax/config`         (auth)   for authenticated app boot
 *   - `/api/public/tax-config`  (public) for unauthenticated public pages
 *     (CalculatorsPage etc. that mount under PublicLayout)
 *
 * Keeping the snapshot here means the two controllers stay one-line
 * delegates and the projection logic — including the lower-bound →
 * upper-bound band conversion for SDLT/LBTT/LTT — has a single home.
 */
class TaxConfigSnapshotService
{
    public function __construct(private readonly TaxConfigService $taxConfig) {}

    public function build(): array
    {
        $statePensionAnnual = (float) $this->taxConfig->get('pension.state_pension.full_new_state_pension', 0);

        return [
            'tax_year' => $this->taxConfig->getTaxYear(),
            'effective_from' => $this->taxConfig->getEffectiveFrom(),
            'effective_to' => $this->taxConfig->getEffectiveTo(),

            'isa' => [
                'annual_allowance' => (int) $this->taxConfig->get('isa.annual_allowance', 0),
                'lifetime_isa_allowance' => (int) $this->taxConfig->get('isa.lifetime_isa.annual_allowance', 0),
                'junior_isa_allowance' => (int) $this->taxConfig->get('isa.junior_isa.annual_allowance', 0),
            ],

            'pension' => [
                'annual_allowance' => (int) $this->taxConfig->get('pension.annual_allowance', 0),
                'money_purchase_annual_allowance' => (int) $this->taxConfig->get('pension.money_purchase_annual_allowance', 0),
                // LTA was abolished in April 2024 — null mirrors the legacy JS constant.
                'lifetime_allowance' => $this->taxConfig->get('pension.lifetime_allowance_abolished') === true
                    ? null
                    : $this->taxConfig->get('pension.lifetime_allowance'),
                'taper_threshold_income' => (int) $this->taxConfig->get('pension.tapered_annual_allowance.threshold_income', 0),
                'taper_adjusted_income' => (int) $this->taxConfig->get('pension.tapered_annual_allowance.adjusted_income', 0),
                'tax_free_cash_rate' => (float) $this->taxConfig->get('pension.pcls_rate', 0.25),
                'tax_free_lump_sum_limit' => (int) $this->taxConfig->get('pension.lump_sum_allowance', 0),
                'state_pension_annual' => $statePensionAnnual,
                // Derived — seeder only stores the annual figure.
                'state_pension_weekly' => $statePensionAnnual > 0
                    ? round($statePensionAnnual / 52, 2)
                    : 0.0,
            ],

            'income_tax' => [
                'personal_allowance' => (int) $this->taxConfig->get('income_tax.personal_allowance', 0),
                'personal_allowance_taper_threshold' => (int) $this->taxConfig->get('income_tax.personal_allowance_taper_threshold', 0),
                'higher_rate_threshold' => (int) $this->taxConfig->get('income_tax.higher_rate_threshold', 0),
                'additional_rate_threshold' => (int) $this->taxConfig->get('income_tax.additional_rate_threshold', 0),
                'basic_rate' => (float) $this->taxConfig->get('income_tax.bands.0.rate', 0),
                'higher_rate' => (float) $this->taxConfig->get('income_tax.bands.1.rate', 0),
                'additional_rate' => (float) $this->taxConfig->get('income_tax.bands.2.rate', 0),
                'savings_allowance_basic' => (int) $this->taxConfig->get('income_tax.personal_savings_allowance.basic', 0),
                'savings_allowance_higher' => (int) $this->taxConfig->get('income_tax.personal_savings_allowance.higher', 0),
                'marriage_allowance' => (int) $this->taxConfig->get('income_tax.marriage_allowance.amount', 0),
            ],

            'national_insurance' => [
                'primary_threshold' => (int) $this->taxConfig->get('national_insurance.class_1.employee.primary_threshold', 0),
                'upper_earnings_limit' => (int) $this->taxConfig->get('national_insurance.class_1.employee.upper_earnings_limit', 0),
                'basic_rate' => (float) $this->taxConfig->get('national_insurance.class_1.employee.main_rate', 0),
                'additional_rate' => (float) $this->taxConfig->get('national_insurance.class_1.employee.additional_rate', 0),
            ],

            'capital_gains_tax' => [
                'annual_allowance' => (int) $this->taxConfig->get('capital_gains_tax.annual_exempt_amount', 0),
                'basic_rate' => (float) $this->taxConfig->get('capital_gains_tax.basic_rate', 0),
                'higher_rate' => (float) $this->taxConfig->get('capital_gains_tax.higher_rate', 0),
                'badr_rate' => (float) $this->taxConfig->get('capital_gains_tax.business_asset_disposal_relief_rate', 0),
                'badr_lifetime_limit' => (int) $this->taxConfig->get('capital_gains_tax.business_asset_disposal_relief_lifetime_limit', 0),
            ],

            'inheritance_tax' => [
                'nil_rate_band' => (int) $this->taxConfig->get('inheritance_tax.nil_rate_band', 0),
                'residence_nil_rate_band' => (int) $this->taxConfig->get('inheritance_tax.residence_nil_rate_band', 0),
                'rnrb_taper_threshold' => (int) $this->taxConfig->get('inheritance_tax.rnrb_taper_threshold', 0),
                'standard_rate' => (float) $this->taxConfig->get('inheritance_tax.standard_rate', 0),
                'reduced_rate' => (float) $this->taxConfig->get('inheritance_tax.reduced_rate_charity', 0),
            ],

            'dividend_tax' => [
                'allowance' => (int) $this->taxConfig->get('dividend_tax.allowance', 0),
                'basic_rate' => (float) $this->taxConfig->get('dividend_tax.basic_rate', 0),
                'higher_rate' => (float) $this->taxConfig->get('dividend_tax.higher_rate', 0),
                'additional_rate' => (float) $this->taxConfig->get('dividend_tax.additional_rate', 0),
            ],

            'gifting_exemptions' => [
                'annual_exemption' => (int) $this->taxConfig->get('gifting_exemptions.annual_exemption', 0),
                'small_gift_exemption' => (int) $this->taxConfig->get('gifting_exemptions.small_gifts_limit', 0),
            ],

            // Property transfer taxes (England SDLT, Scotland LBTT, Wales LTT).
            // Bands are returned in upper-bound shape with integer-percent rates
            // so they map directly onto the constants in
            // resources/js/constants/taxConfig.js (CalculatorsPage uses
            // `effectiveRate / 100` math). The open-ended top band uses
            // Number.MAX_SAFE_INTEGER as its sentinel threshold.
            'stamp_duty' => [
                'england' => [
                    'standard_bands' => $this->convertBandsToFrontendShape(
                        $this->taxConfig->get('stamp_duty.residential.standard.bands', [])
                    ),
                    'ftb_bands' => $this->convertBandsToFrontendShape(
                        $this->taxConfig->get('stamp_duty.residential.first_time_buyers.bands', [])
                    ),
                    'ftb_max_price' => (int) $this->taxConfig->get('stamp_duty.residential.first_time_buyers.max_property_value', 0),
                    'additional_surcharge' => (int) round(
                        ((float) $this->taxConfig->get('stamp_duty.residential.additional_properties.surcharge', 0)) * 100
                    ),
                    'non_uk_surcharge' => (int) round(
                        ((float) $this->taxConfig->get('stamp_duty.residential.non_resident_surcharge', 0)) * 100
                    ),
                ],
                'scotland' => [
                    'lbtt_bands' => $this->convertBandsToFrontendShape(
                        $this->taxConfig->get('stamp_duty.lbtt.bands', [])
                    ),
                    'lbtt_additional_surcharge' => (int) round(
                        ((float) $this->taxConfig->get('stamp_duty.lbtt.additional_surcharge', 0)) * 100
                    ),
                    'lbtt_non_uk_surcharge' => (int) round(
                        ((float) $this->taxConfig->get('stamp_duty.lbtt.non_uk_surcharge', 0)) * 100
                    ),
                ],
                'wales' => [
                    'ltt_bands' => $this->convertBandsToFrontendShape(
                        $this->taxConfig->get('stamp_duty.ltt.bands', [])
                    ),
                    'ltt_additional_surcharge' => (int) round(
                        ((float) $this->taxConfig->get('stamp_duty.ltt.additional_surcharge', 0)) * 100
                    ),
                    'ltt_non_uk_surcharge' => (int) round(
                        ((float) $this->taxConfig->get('stamp_duty.ltt.non_uk_surcharge', 0)) * 100
                    ),
                ],
            ],

            'student_loan' => [
                'repayment_rate' => (float) $this->taxConfig->get('student_loan.repayment_rate', 0),
            ],

            'other' => [
                'hicbc_threshold' => (int) $this->taxConfig->get('benefits.child_benefit.high_income_charge_threshold', 0),
                'ssp_weekly_rate' => (float) $this->taxConfig->get('benefits.ssp.weekly_rate', 0),
            ],
        ];
    }

    /**
     * Convert seeder lower-bound bands (rate applies from threshold up, decimal
     * rates) into the upper-bound integer-percent shape the frontend expects:
     *
     *   in:  [{threshold:0, rate:0.00}, {threshold:125000, rate:0.02}, ...]
     *   out: [{threshold:125000, rate:0}, {threshold:250000, rate:2}, ...,
     *         {threshold: 9007199254740991, rate: 12}]
     *
     * The last band's threshold is Number.MAX_SAFE_INTEGER so the frontend's
     * `Math.min(price, band.threshold)` math stays correct without an Infinity
     * sentinel that JSON cannot represent.
     */
    private function convertBandsToFrontendShape(array $bands): array
    {
        if (empty($bands)) {
            return [];
        }

        $output = [];
        $count = count($bands);

        for ($i = 0; $i < $count; $i++) {
            $upperThreshold = $i + 1 < $count
                ? (int) $bands[$i + 1]['threshold']
                : 9007199254740991; // Number.MAX_SAFE_INTEGER

            $output[] = [
                'threshold' => $upperThreshold,
                'rate' => round(((float) $bands[$i]['rate']) * 100, 2),
            ];
        }

        return $output;
    }
}
