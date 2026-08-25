<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Traits\ValidatesSharedOwnership;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePropertyRequest extends FormRequest
{
    use ValidatesSharedOwnership;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The properties.country column is NOT NULL DEFAULT 'United Kingdom'.
     * Drop the key when it arrives null/empty so the DB default kicks in instead
     * of an integrity-constraint 500. Same pattern as PR #269 (investment_accounts).
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('country') && in_array($this->input('country'), [null, ''], true)) {
            $this->offsetUnset('country');
        }
    }

    /**
     * Get the validation rules that apply to the request.
     */
    /**
     * A stated ownership share that is not a shared split is refused rather
     * than rewritten (W-0040).
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $this->validateSharedOwnershipSplit($v, $this->input('ownership_type'), $this->input('ownership_percentage'));
        });
    }

    public function rules(): array
    {
        return [
            'trust_id' => ['nullable', 'exists:trusts,id'],
            'property_type' => ['nullable', Rule::in(['main_residence', 'secondary_residence', 'buy_to_let'])],
            'ownership_type' => ['nullable', Rule::in(['individual', 'joint', 'tenants_in_common', 'trust'])],
            'joint_ownership_type' => ['nullable', Rule::in(['joint_tenancy', 'tenants_in_common'])],
            'country' => ['nullable', 'string', 'max:255'],
            'ownership_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'joint_owner_id' => ['nullable', 'exists:users,id'],
            'joint_owner_name' => ['nullable', 'string', 'max:255'],
            'household_id' => ['nullable', 'exists:households,id'],
            'trust_name' => ['nullable', 'string', 'max:255'],

            // Tenure
            'tenure_type' => ['nullable', Rule::in(['freehold', 'leasehold'])],
            'lease_remaining_years' => ['nullable', 'integer', 'min:0', 'max:999'],
            'lease_expiry_date' => ['nullable', 'date'],

            // Address - all fields optional
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'county' => ['nullable', 'string', 'max:255'],
            'postcode' => ['nullable', 'string', 'max:10'],

            // Financial
            'purchase_date' => ['nullable', 'date'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'current_value' => ['nullable', 'numeric', 'min:0'],
            'valuation_date' => ['nullable', 'date'],
            'sdlt_paid' => ['nullable', 'numeric', 'min:0'],
            'outstanding_mortgage' => ['nullable', 'numeric', 'min:0'],

            // Mortgage details (when auto-creating mortgage from property form)
            'mortgage_lender_name' => ['nullable', 'string', 'max:255'],
            // W-0012. The wizard has always collected this and the request never
            // accepted it, so it was stripped at validation on every property
            // created with a mortgage — the tenth dropped field, and the one the
            // field-list fix did not recover because the gap is on the RECEIVING
            // side rather than the sending one. `mortgages.mortgage_account_number`
            // exists and `UpdateMortgageRequest` already accepts it; only creation
            // could not store it.
            'mortgage_account_number' => ['nullable', 'string', 'max:50'],
            'mortgage_type' => ['nullable', Rule::in(['repayment', 'interest_only', 'mixed'])],
            'mortgage_repayment_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'mortgage_interest_only_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'mortgage_monthly_payment' => ['nullable', 'numeric', 'min:0'],
            'mortgage_interest_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'mortgage_rate_type' => ['nullable', Rule::in(['fixed', 'variable', 'tracker', 'discount', 'mixed', 'capped', 'offset'])],
            'mortgage_fixed_rate_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'mortgage_variable_rate_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'mortgage_fixed_interest_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'mortgage_variable_interest_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'mortgage_start_date' => ['nullable', 'date'],
            'mortgage_maturity_date' => ['nullable', 'date'],
            // The wizard renders these three; without rules here validated() strips
            // them and the mortgage is written with a hardcoded term, no rate-fix
            // end date and no interest portion (W-0012).
            'mortgage_rate_fix_end_date' => ['nullable', 'date'],
            'mortgage_remaining_term_months' => ['nullable', 'integer', 'min:0'],
            'mortgage_monthly_interest_portion' => ['nullable', 'numeric', 'min:0'],
            'mortgage_ownership_type' => ['nullable', Rule::in(['individual', 'joint'])],
            'mortgage_original_loan_amount' => ['nullable', 'numeric', 'min:0'],
            'mortgage_joint_owner_id' => ['nullable', 'exists:users,id'],
            'mortgage_joint_owner_name' => ['nullable', 'string', 'max:255'],
            'mortgage_ownership_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],

            // Rental (for BTL)
            'rental_income' => ['nullable', 'numeric', 'min:0'],
            'monthly_rental_income' => ['nullable', 'numeric', 'min:0'],
            'tenant_name' => ['nullable', 'string', 'max:255'],
            'tenant_email' => ['nullable', 'email', 'max:255'],
            'managing_agent_name' => ['nullable', 'string', 'max:255'],
            'managing_agent_company' => ['nullable', 'string', 'max:255'],
            'managing_agent_email' => ['nullable', 'email', 'max:255'],
            'managing_agent_phone' => ['nullable', 'string', 'max:255'],
            'managing_agent_fee' => ['nullable', 'numeric', 'min:0'],
            'lease_start_date' => ['nullable', 'date'],
            'lease_end_date' => ['nullable', 'date', 'after_or_equal:lease_start_date'],

            // Monthly Costs
            'monthly_council_tax' => ['nullable', 'numeric', 'min:0'],
            'monthly_gas' => ['nullable', 'numeric', 'min:0'],
            'monthly_electricity' => ['nullable', 'numeric', 'min:0'],
            'monthly_water' => ['nullable', 'numeric', 'min:0'],
            'monthly_building_insurance' => ['nullable', 'numeric', 'min:0'],
            'monthly_contents_insurance' => ['nullable', 'numeric', 'min:0'],
            'monthly_service_charge' => ['nullable', 'numeric', 'min:0'],
            'monthly_maintenance_reserve' => ['nullable', 'numeric', 'min:0'],
            'other_monthly_costs' => ['nullable', 'numeric', 'min:0'],

            // Notes
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'sdlt_paid' => 'SDLT paid',
            'annual_service_charge' => 'annual service charge',
            'annual_ground_rent' => 'annual ground rent',
            'annual_insurance' => 'annual insurance',
            'annual_maintenance_reserve' => 'annual maintenance reserve',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'postcode.regex' => 'The postcode must be a valid UK postcode.',
            'lease_end_date.after_or_equal' => 'The lease end date must be after or equal to the lease start date.',
        ];
    }
}
