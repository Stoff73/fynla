<?php

declare(strict_types=1);

namespace App\Http\Requests\Protection;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIncomeProtectionPolicyRequest extends FormRequest
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
            'provider' => ['required', 'string', 'max:255'],
            'policy_number' => ['nullable', 'string', 'max:255'],
            'benefit_amount' => ['required', 'numeric', 'min:1000'],
            'benefit_frequency' => ['required', Rule::in(['monthly', 'weekly'])],
            'deferred_period_weeks' => ['nullable', 'integer', 'min:0', 'max:104'],
            'benefit_period_months' => ['nullable', 'integer', 'min:1', 'max:720'],
            'premium_amount' => ['required', 'numeric', 'min:0'],
            'premium_frequency' => ['required', Rule::in(['monthly', 'quarterly', 'annually'])],
            'occupation_class' => ['nullable', 'string', 'max:255'],
            'policy_start_date' => ['nullable', 'date', 'before_or_equal:today'],
            'policy_end_date' => ['nullable', 'date', 'after:policy_start_date'],
            'policy_term_years' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'benefit_amount' => 'benefit amount',
            'benefit_frequency' => 'benefit frequency',
            'deferred_period_weeks' => 'deferred period',
            'benefit_period_months' => 'benefit period',
            'premium_amount' => 'premium amount',
            'premium_frequency' => 'premium frequency',
            'occupation_class' => 'occupation class',
            'policy_start_date' => 'policy start date',
            'policy_end_date' => 'policy end date',
            'policy_term_years' => 'policy term',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'benefit_amount.min' => 'The benefit amount must be at least £1,000.',
            'policy_end_date.after' => 'The policy end date must be after the start date.',
        ];
    }
}
