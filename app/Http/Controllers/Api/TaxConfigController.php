<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TaxConfigService;
use Illuminate\Http\JsonResponse;

/**
 * Returns the active UK tax-year configuration snapshot the frontend needs to
 * render allowances, thresholds, and rates without falling back to the
 * hardcoded constants in `resources/js/constants/taxConfig.js`.
 *
 * Backend `TaxConfigService` is the authoritative source. This endpoint exposes
 * a curated, frontend-shaped projection — same key set the JS constants file
 * exports, in nested form — so the Vuex store can hydrate getters that match
 * each constant 1:1.
 *
 * Public-style data (UK tax values are not sensitive) but auth-gated to avoid
 * unnecessary exposure; admin-only fields (full band tables, scotland bands,
 * trust rules, etc.) stay behind `permission:admin.tax_config`.
 */
class TaxConfigController extends Controller
{
    public function __construct(private readonly TaxConfigService $taxConfig) {}

    public function show(): JsonResponse
    {
        // No cross-request cache: TaxConfigService is request-scoped so each
        // call already loads the active config from the DB exactly once, and a
        // longer-lived cache would go stale when admin edits the active year.
        return response()->json([
            'success' => true,
            'data' => $this->buildPayload(),
        ]);
    }

    private function buildPayload(): array
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

            'other' => [
                'hicbc_threshold' => (int) $this->taxConfig->get('benefits.child_benefit.high_income_charge_threshold', 0),
                'ssp_weekly_rate' => (float) $this->taxConfig->get('benefits.ssp.weekly_rate', 0),
            ],
        ];
    }
}
