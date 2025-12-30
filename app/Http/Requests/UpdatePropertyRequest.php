<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePropertyRequest extends FormRequest
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
            'trust_id' => ['sometimes', 'nullable', 'exists:trusts,id'],
            'property_type' => ['sometimes', Rule::in(['main_residence', 'secondary_residence', 'buy_to_let'])],
            'ownership_type' => ['sometimes', Rule::in(['individual', 'joint', 'tenants_in_common', 'trust'])],
            'joint_ownership_type' => ['sometimes', 'nullable', Rule::in(['joint_tenancy', 'tenants_in_common'])],
            'country' => ['sometimes', 'nullable', 'string', 'max:255'],
            'ownership_percentage' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'joint_owner_id' => ['sometimes', 'nullable', 'exists:users,id'],
            'joint_owner_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'household_id' => ['sometimes', 'nullable', 'exists:households,id'],
            'trust_name' => ['sometimes', 'nullable', 'string', 'max:255'],

            // Tenure
            'tenure_type' => ['sometimes', 'nullable', Rule::in(['freehold', 'leasehold'])],
            'lease_remaining_years' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:999'],
            'lease_expiry_date' => ['sometimes', 'nullable', 'date'],

            // Address
            'address_line_1' => ['sometimes', 'string', 'max:255'],
            'address_line_2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'string', 'max:255'],
            'county' => ['sometimes', 'nullable', 'string', 'max:255'],
            'postcode' => ['sometimes', 'string', 'max:20'],

            // Financial
            'purchase_date' => ['sometimes', 'nullable', 'date'],
            'purchase_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'current_value' => ['sometimes', 'numeric', 'min:0'],
            'valuation_date' => ['sometimes', 'nullable', 'date'],
            'sdlt_paid' => ['sometimes', 'nullable', 'numeric', 'min:0'],

            // Rental
            'monthly_rental_income' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'annual_rental_income' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'occupancy_rate_percent' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
            'tenant_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'tenant_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'managing_agent_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'managing_agent_company' => ['sometimes', 'nullable', 'string', 'max:255'],
            'managing_agent_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'managing_agent_phone' => ['sometimes', 'nullable', 'string', 'max:255'],
            'managing_agent_fee' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'lease_start_date' => ['sometimes', 'nullable', 'date'],
            'lease_end_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:lease_start_date'],

            // Monthly Costs
            'monthly_council_tax' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'monthly_gas' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'monthly_electricity' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'monthly_water' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'monthly_building_insurance' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'monthly_contents_insurance' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'monthly_service_charge' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'monthly_maintenance_reserve' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'other_monthly_costs' => ['sometimes', 'nullable', 'numeric', 'min:0'],

            // Notes
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
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
            'lease_end_date.after_or_equal' => 'The lease end date must be after or equal to the lease start date.',
        ];
    }

    /**
     * Add conditional validation based on country.
     */
    public function withValidator($validator): void
    {
        $validator->sometimes('postcode', 'regex:/^[A-Z]{1,2}[0-9]{1,2}[A-Z]?\s?[0-9][A-Z]{2}$/i', function ($input) {
            // Only validate UK postcode format when country is UK or not specified
            return $input->country === 'United Kingdom' || $input->country === null;
        });
    }
}
