<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form Request for updating investment accounts.
 *
 * All fields are optional as partial updates are allowed.
 * Uses same validation rules as StoreInvestmentAccountRequest
 * but with nullable requirements.
 */
class UpdateInvestmentAccountRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // Core account fields
            'account_type' => ['nullable', Rule::in(StoreInvestmentAccountRequest::getAccountTypes())],
            'account_type_other' => 'required_if:account_type,other|nullable|string|max:255',
            'provider' => 'nullable|string|max:255',
            'account_number' => 'nullable|string|max:255',
            'platform' => 'nullable|string|max:255',
            'current_value' => 'nullable|numeric|min:0',
            'contributions_ytd' => 'nullable|numeric|min:0',
            'tax_year' => 'nullable|string|max:10',

            // Platform fees
            'platform_fee_percent' => 'nullable|numeric|min:0',
            'platform_fee_amount' => 'nullable|numeric|min:0',
            'platform_fee_type' => ['nullable', Rule::in(['percentage', 'fixed'])],
            'platform_fee_frequency' => ['nullable', Rule::in(['monthly', 'quarterly', 'annually'])],

            // Ownership
            'ownership_type' => ['nullable', Rule::in(['individual', 'joint', 'trust'])],
            'ownership_percentage' => 'nullable|numeric|min:0|max:100',
            'joint_owner_id' => 'nullable|exists:users,id',

            // ISA specific
            'isa_type' => ['nullable', Rule::in(['stocks_and_shares', 'lifetime', 'innovative_finance'])],
            'isa_subscription_current_year' => 'nullable|numeric|min:0|max:'.\App\Constants\TaxDefaults::ISA_ALLOWANCE,

            // Contributions
            'monthly_contribution_amount' => 'nullable|numeric|min:0',
            'contribution_frequency' => ['nullable', Rule::in(['monthly', 'quarterly', 'annually'])],
            'planned_lump_sum_amount' => 'nullable|numeric|min:0',
            'planned_lump_sum_date' => 'nullable|date',
            'country' => 'nullable|string|max:255',
            'risk_preference' => 'nullable|string|max:50',

            // Private Company / Crowdfunding fields
            ...$this->getPrivateCompanyRules(),

            // Employee Share Scheme fields
            ...$this->getEmployeeShareSchemeRules(),

            // Inline holdings
            'holdings' => 'sometimes|array',
            'holdings.*.security_name' => 'required_with:holdings|string|max:255',
            'holdings.*.asset_type' => ['required_with:holdings', Rule::in([
                'equity', 'bond', 'fund', 'etf', 'alternative',
                'uk_equity', 'us_equity', 'international_equity', 'cash', 'property',
            ])],
            'holdings.*.allocation_percent' => 'required_with:holdings|numeric|min:0|max:100',
            'holdings.*.cost_basis' => 'nullable|numeric|min:0',
            'holdings.*.ocf_percent' => 'nullable|numeric|min:0|max:100',
        ];
    }

    /**
     * Get validation rules for private company/crowdfunding accounts.
     */
    private function getPrivateCompanyRules(): array
    {
        return [
            'company_legal_name' => 'nullable|string|max:255',
            'company_registration_number' => 'nullable|string|max:50',
            'company_country' => 'nullable|string|max:100',
            'company_website' => 'nullable|url|max:255',
            'company_trading_name' => 'nullable|string|max:255',
            'crowdfunding_platform' => 'nullable|string|max:255',
            'investment_date' => 'nullable|date',
            'investment_amount' => 'nullable|numeric|min:0',
            'investment_currency' => 'nullable|string|size:3',
            'funding_round' => ['nullable', Rule::in(['pre_seed', 'seed', 'series_a', 'series_b', 'series_c', 'bridge', 'safe', 'other'])],
            'pre_money_valuation' => 'nullable|numeric|min:0',
            'post_money_valuation' => 'nullable|numeric|min:0',
            'price_per_share' => 'nullable|numeric|min:0',
            'number_of_shares' => 'nullable|integer|min:0',
            'instrument_type' => ['nullable', Rule::in(['ordinary_shares', 'preference_shares', 'convertible_loan_note', 'safe', 'revenue_share', 'fund_nominee_interest'])],
            'share_class' => 'nullable|string|max:100',
            'liquidation_preference' => 'nullable|string|max:100',
            'has_anti_dilution' => 'nullable|boolean',
            'holding_structure' => ['nullable', Rule::in(['direct', 'nominee'])],
            'nominee_name' => 'nullable|string|max:255',
            'conversion_terms' => 'nullable|string',
            'interest_rate' => 'nullable|numeric|min:0|max:100',
            'maturity_date' => 'nullable|date',
            'tax_relief_type' => ['nullable', Rule::in(['eis', 'seis', 'sitr', 'vct', 'none'])],
            'eis3_certificate_number' => 'nullable|string|max:50',
            'hmrc_reference' => 'nullable|string|max:50',
            'relief_claimed_date' => 'nullable|date',
            'relief_amount_claimed' => 'nullable|numeric|min:0',
            'disposal_restriction_date' => 'nullable|date',
            'clawback_risk' => 'nullable|boolean',
            'clawback_notes' => 'nullable|string',
            'latest_valuation' => 'nullable|numeric|min:0',
            'latest_valuation_date' => 'nullable|date',
            'current_ownership_percent' => 'nullable|numeric|min:0|max:100',
            'company_status' => ['nullable', Rule::in(['active', 'distressed', 'dormant', 'failed', 'exited'])],
            'status_notes' => 'nullable|string',
            'exit_type' => ['nullable', Rule::in(['acquisition', 'secondary_sale', 'buyback', 'ipo', 'liquidation'])],
            'exit_date' => 'nullable|date',
            'exit_gross_proceeds' => 'nullable|numeric|min:0',
            'exit_fees' => 'nullable|numeric|min:0',
            'exit_net_proceeds' => 'nullable|numeric|min:0',
            'exit_moic' => 'nullable|numeric|min:0',
            'loss_relief_eligible' => 'nullable|boolean',
            'capital_loss_amount' => 'nullable|numeric|min:0',
            'negligible_value_claim' => 'nullable|boolean',
        ];
    }

    /**
     * Get validation rules for employee share scheme accounts.
     */
    private function getEmployeeShareSchemeRules(): array
    {
        return [
            // Employer Details
            'employer_name' => 'nullable|string|max:255',
            'employer_registration' => 'nullable|string|max:50',
            'employer_ticker' => 'nullable|string|max:20',
            'employer_is_listed' => 'nullable|boolean',
            'parent_company_name' => 'nullable|string|max:255',
            'parent_company_country' => 'nullable|string|max:100',
            'ers_scheme_reference' => 'nullable|string|max:50',
            'ers_registered' => 'nullable|boolean',

            // Grant Details
            'grant_date' => 'nullable|date',
            'grant_reference' => 'nullable|string|max:100',
            'units_granted' => 'nullable|integer|min:0',
            'exercise_price' => 'nullable|numeric|min:0',
            'market_value_at_grant' => 'nullable|numeric|min:0',
            'share_class_scheme' => 'nullable|string|max:100',
            'grant_currency' => 'nullable|string|size:3',
            'option_price_paid' => 'nullable|numeric|min:0',
            'scheme_start_date' => 'nullable|date',
            'scheme_duration_months' => ['nullable', 'integer', Rule::in([36, 60])],

            // Vesting Schedule
            'vesting_type' => ['nullable', Rule::in(['cliff', 'monthly', 'quarterly', 'annual', 'performance', 'immediate'])],
            'cliff_date' => 'nullable|date',
            'cliff_percentage' => 'nullable|integer|min:0|max:100',
            'vesting_period_months' => 'nullable|integer|min:0',
            'vesting_frequency_months' => 'nullable|integer|min:0',
            'has_performance_conditions' => 'nullable|boolean',
            'performance_conditions_description' => 'nullable|string',
            'performance_period_end' => 'nullable|date',
            'performance_vesting_min_percent' => 'nullable|integer|min:0|max:100',
            'performance_vesting_max_percent' => 'nullable|integer|min:0|max:100',
            'full_vest_date' => 'nullable|date',
            'accelerated_vesting_allowed' => 'nullable|boolean',

            // Current Status
            'units_vested' => 'nullable|integer|min:0',
            'units_unvested' => 'nullable|integer|min:0',
            'units_exercised' => 'nullable|integer|min:0',
            'units_forfeited' => 'nullable|integer|min:0',
            'units_expired' => 'nullable|integer|min:0',
            'scheme_status' => ['nullable', Rule::in(['active', 'vesting', 'exercisable', 'exercised', 'expired', 'forfeited', 'cancelled'])],
            'current_share_price' => 'nullable|numeric|min:0',
            'share_price_date' => 'nullable|date',

            // Exercise & Expiry
            'exercise_window_start' => 'nullable|date',
            'exercise_window_end' => 'nullable|date',
            'last_exercise_date' => 'nullable|date',
            'total_exercise_proceeds' => 'nullable|numeric|min:0',
            'total_exercise_cost' => 'nullable|numeric|min:0',
            'exercise_history_json' => 'nullable|string',

            // Tax Treatment
            'tax_treatment' => ['nullable', Rule::in(['tax_advantaged', 'unapproved', 'mixed'])],
            'is_readily_convertible_asset' => 'nullable|boolean',
            'paye_via_payroll' => 'nullable|boolean',
            'income_tax_at_vest_exercise' => 'nullable|numeric|min:0',
            'ni_at_vest_exercise' => 'nullable|numeric|min:0',
            'csop_disqualifying_event' => 'nullable|boolean',
            'csop_three_year_date' => 'nullable|date',
            'cost_basis_for_cgt' => 'nullable|numeric|min:0',

            // SAYE-Specific
            'saye_monthly_savings' => 'nullable|numeric|min:0|max:500',
            'saye_current_savings_balance' => 'nullable|numeric|min:0',
            'saye_maturity_date' => 'nullable|date',
            'saye_option_discount_percent' => 'nullable|numeric|min:0|max:20',
            'saye_bonus_amount' => 'nullable|numeric|min:0',

            // Leaver Terms
            'leaver_category' => ['nullable', Rule::in(['good_leaver', 'bad_leaver', 'death', 'redundancy', 'retirement', 'unknown'])],
            'post_termination_exercise_days' => 'nullable|integer|min:0',
            'termination_date' => 'nullable|date',
            'leaver_notes' => 'nullable|string',
        ];
    }
}
