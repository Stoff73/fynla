<?php

declare(strict_types=1);

namespace App\Http\Requests\Protection;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLifePolicyRequest extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'policy_type' => ['nullable', Rule::in(['term', 'whole_of_life', 'decreasing_term', 'family_income_benefit', 'level_term'])],
            'provider' => ['nullable', 'string', 'max:255'],
            'policy_number' => ['nullable', 'string', 'max:255'],
            'sum_assured' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'premium_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'premium_frequency' => ['nullable', Rule::in(['monthly', 'quarterly', 'annually'])],
            'in_trust' => ['nullable', 'boolean'],
            'is_mortgage_protection' => ['nullable', 'boolean'],
            'beneficiaries' => ['nullable', 'string', 'max:1000'],
        ];

        // Conditional rules based on policy type
        // NOTE: All dates made optional per Nov 24 patch (v0.2.13) - users may not know exact dates
        $policyType = $this->input('policy_type');

        if ($policyType === 'decreasing_term') {
            // Decreasing policies may have start value and decreasing rate for calculations
            $rules['start_value'] = ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'];
            $rules['decreasing_rate'] = ['nullable', 'numeric', 'min:0', 'max:1'];
            $rules['policy_start_date'] = ['nullable', 'date', 'before_or_equal:today'];
            $rules['policy_end_date'] = ['nullable', 'date', 'after:today'];
            $rules['policy_term_years'] = ['nullable', 'integer', 'min:1', 'max:50'];
        } elseif ($policyType === 'term' || $policyType === 'level_term' || $policyType === 'family_income_benefit') {
            // Term policies - dates are optional
            $rules['policy_start_date'] = ['nullable', 'date', 'before_or_equal:today'];
            $rules['policy_end_date'] = ['nullable', 'date', 'after:today'];
            $rules['policy_term_years'] = ['nullable', 'integer', 'min:1', 'max:50'];
            $rules['start_value'] = ['nullable'];
            $rules['decreasing_rate'] = ['nullable'];
        } elseif ($policyType === 'whole_of_life') {
            // Whole of life policies - dates are optional
            $rules['policy_start_date'] = ['nullable', 'date', 'before_or_equal:today'];
            $rules['policy_end_date'] = ['nullable', 'date', 'after:today'];
            $rules['policy_term_years'] = ['nullable', 'integer', 'min:1', 'max:50'];
            $rules['start_value'] = ['nullable'];
            $rules['decreasing_rate'] = ['nullable'];
        }

        // Always allow indexation_rate regardless of policy type
        $rules['indexation_rate'] = ['nullable', 'numeric', 'min:0', 'max:0.10'];

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'policy_type.required' => 'Policy type is required.',
            'policy_type.in' => 'Invalid policy type selected.',
            'provider.required' => 'Provider is required.',
            'sum_assured.required' => 'Sum assured is required.',
            'sum_assured.min' => 'Sum assured must be at least £1,000.',
            'premium_amount.required' => 'Premium amount is required.',
            'premium_frequency.required' => 'Premium frequency is required.',
            'policy_end_date.required' => 'Policy end date is required.',
            'policy_end_date.after' => 'Policy end date must be in the future.',
        ];
    }
}
