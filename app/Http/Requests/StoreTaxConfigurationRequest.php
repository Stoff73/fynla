<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaxConfigurationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $permissionService = app(\App\Services\Auth\PermissionService::class);

        return $this->user() && $permissionService->hasPermission($this->user(), \App\Models\Permission::ADMIN_TAX_CONFIG);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'tax_year' => 'required|string|regex:/^\d{4}\/\d{2}$/|unique:tax_configurations,tax_year,'.$this->route('id'),
            'effective_from' => 'sometimes|date',
            'effective_to' => 'sometimes|date|after:effective_from',
            'is_active' => 'sometimes|boolean',

            // Income Tax validation
            'config_data' => 'required|array|min:1',
            'config_data.income_tax' => 'sometimes|array',
            'config_data.income_tax.personal_allowance' => 'sometimes|numeric|min:0',
            'config_data.income_tax.bands' => 'sometimes|array|min:3',
            'config_data.income_tax.bands.*.name' => 'sometimes|string',
            'config_data.income_tax.bands.*.threshold' => 'sometimes|numeric|min:0',
            'config_data.income_tax.bands.*.rate' => 'sometimes|numeric|min:0|max:1',

            // National Insurance validation
            'config_data.national_insurance' => 'sometimes|array',
            'config_data.national_insurance.class_1_employee' => 'sometimes|array',
            'config_data.national_insurance.class_1_employee.primary_threshold' => 'sometimes|numeric|min:0',
            'config_data.national_insurance.class_1_employee.upper_earnings_limit' => 'sometimes|numeric|min:0',
            'config_data.national_insurance.class_1_employee.main_rate' => 'sometimes|numeric|min:0|max:1',
            'config_data.national_insurance.class_1_employee.additional_rate' => 'sometimes|numeric|min:0|max:1',

            // ISA Allowances validation
            'config_data.isa' => 'sometimes|array',
            'config_data.isa.annual_allowance' => 'sometimes|numeric|min:0',
            'config_data.isa.lifetime_isa.annual_allowance' => 'sometimes|numeric|min:0',
            'config_data.isa.junior_isa.annual_allowance' => 'sometimes|numeric|min:0',

            // Pension Allowances validation
            'config_data.pension' => 'sometimes|array',
            'config_data.pension.annual_allowance' => 'sometimes|numeric|min:0',
            'config_data.pension.mpaa' => 'sometimes|numeric|min:0',
            'config_data.pension.tapered_annual_allowance.threshold_income' => 'sometimes|numeric|min:0',
            'config_data.pension.tapered_annual_allowance.adjusted_income_threshold' => 'sometimes|numeric|min:0',
            'config_data.pension.tapered_annual_allowance.minimum_allowance' => 'sometimes|numeric|min:0',

            // Inheritance Tax validation
            'config_data.inheritance_tax' => 'sometimes|array',
            'config_data.inheritance_tax.nil_rate_band' => 'sometimes|numeric|min:0',
            'config_data.inheritance_tax.residence_nil_rate_band' => 'sometimes|numeric|min:0',
            'config_data.inheritance_tax.standard_rate' => 'sometimes|numeric|min:0|max:1',
            'config_data.inheritance_tax.reduced_rate_charity' => 'sometimes|numeric|min:0|max:1',

            // Gifting Exemptions validation
            'config_data.gifting_exemptions' => 'sometimes|array',
            'config_data.gifting_exemptions.annual_exemption' => 'sometimes|numeric|min:0',
            'config_data.gifting_exemptions.small_gifts_limit' => 'sometimes|numeric|min:0',

            // Capital Gains Tax validation
            'config_data.capital_gains_tax' => 'sometimes|array',
            'config_data.capital_gains_tax.annual_exempt_amount' => 'sometimes|numeric|min:0',
            'config_data.capital_gains_tax.basic_rate' => 'sometimes|numeric|min:0|max:1',
            'config_data.capital_gains_tax.higher_rate' => 'sometimes|numeric|min:0|max:1',

            // Dividend Tax validation
            'config_data.dividend_tax' => 'sometimes|array',
            'config_data.dividend_tax.allowance' => 'sometimes|numeric|min:0',
            'config_data.dividend_tax.basic_rate' => 'sometimes|numeric|min:0|max:1',
            'config_data.dividend_tax.higher_rate' => 'sometimes|numeric|min:0|max:1',
            'config_data.dividend_tax.additional_rate' => 'sometimes|numeric|min:0|max:1',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'tax_year.regex' => 'Tax year must be in format YYYY/YY (e.g., 2025/26)',
            'effective_to.after' => 'Effective to date must be after effective from date',
            'config_data.income_tax.bands.min' => 'Income tax must have at least 3 bands (basic, higher, additional)',
            '*.rate.max' => 'Tax rates must be between 0 and 1 (use 0.20 for 20%)',
            '*.numeric' => 'All tax values must be numeric',
            '*.min' => 'Tax values cannot be negative',
        ];
    }

    /**
     * Get custom attribute names for validator errors.
     */
    public function attributes(): array
    {
        return [
            'config_data.income_tax.personal_allowance' => 'personal allowance',
            'config_data.pension.annual_allowance' => 'pension annual allowance',
            'config_data.inheritance_tax.nil_rate_band' => 'nil rate band',
            'config_data.inheritance_tax.residence_nil_rate_band' => 'residence nil rate band',
        ];
    }
}
