<?php

declare(strict_types=1);

namespace App\Http\Requests\Retirement;

use Illuminate\Foundation\Http\FormRequest;

class StoreDCPensionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // For POST (create), authorization is handled by middleware
        if ($this->isMethod('POST')) {
            return true;
        }

        // For PUT/PATCH (update), check if user owns the pension
        $pensionId = $this->route('id');
        if ($pensionId) {
            $pension = \App\Models\DCPension::find($pensionId);
            if ($pension && $pension->user_id !== $this->user()->id) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $rules = [
            'scheme_name' => ['nullable', 'string', 'max:255'],
            'scheme_type' => ['nullable', 'in:workplace,sipp,personal'],
            'provider' => ['nullable', 'string', 'max:255'],
            'pension_type' => ['nullable', 'in:occupational,sipp,personal,stakeholder'],
            'member_number' => ['nullable', 'string', 'max:255'],
            'current_fund_value' => ['nullable', 'numeric', 'min:0'],
            'annual_salary' => ['nullable', 'numeric', 'min:0'],
            'employee_contribution_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'employer_contribution_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'monthly_contribution_amount' => ['nullable', 'numeric', 'min:0'],
            'lump_sum_contribution' => ['nullable', 'numeric', 'min:0'],
            'investment_strategy' => ['nullable', 'string', 'max:255'],
            'platform_fee_percent' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'platform_fee_type' => ['nullable', 'in:percentage,fixed'],
            'platform_fee_amount' => ['nullable', 'numeric', 'min:0'],
            'platform_fee_frequency' => ['nullable', 'in:monthly,quarterly,annually'],
            'advisor_fee_percent' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'retirement_age' => ['nullable', 'integer', 'min:55', 'max:75'],
            'projected_value_at_retirement' => ['nullable', 'numeric', 'min:0'],
            'has_flexibly_accessed' => ['nullable', 'boolean'],
            'flexible_access_date' => ['nullable', 'date', 'before_or_equal:today'],
            'beneficiary_id' => ['nullable', 'integer', 'exists:users,id'],
            'beneficiary_name' => ['nullable', 'string', 'max:255'],
            // Inline holdings (created alongside pension in a transaction)
            'holdings' => ['nullable', 'array'],
            'holdings.*.security_name' => ['required_with:holdings', 'string', 'max:255'],
            'holdings.*.asset_type' => ['required_with:holdings', 'string', 'max:50'],
            'holdings.*.allocation_percent' => ['required_with:holdings', 'numeric', 'min:0', 'max:100'],
            'holdings.*.ocf_percent' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'holdings.*.cost_basis' => ['nullable', 'numeric', 'min:0'],
        ];

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'scheme_name.required' => 'Please provide a scheme name.',
            'scheme_type.required' => 'Please select a scheme type.',
            'scheme_type.in' => 'Invalid scheme type. Must be workplace, SIPP, or personal.',
            'provider.required' => 'Please provide the pension provider name.',
            'current_fund_value.required' => 'Please enter the current fund value.',
            'annual_salary.required' => 'Annual salary is required for workplace pensions with percentage contributions.',
            'retirement_age.min' => 'Minimum retirement age is 55.',
            'retirement_age.max' => 'Maximum retirement age is 75.',
        ];
    }
}
