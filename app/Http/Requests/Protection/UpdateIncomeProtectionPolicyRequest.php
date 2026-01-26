<?php

declare(strict_types=1);

namespace App\Http\Requests\Protection;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIncomeProtectionPolicyRequest extends FormRequest
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
            'provider' => ['sometimes', 'string', 'max:255'],
            'policy_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'benefit_amount' => ['sometimes', 'numeric', 'min:1000'],
            'benefit_frequency' => ['sometimes', Rule::in(['monthly', 'weekly'])],
            'deferred_period_weeks' => ['sometimes', 'integer', 'min:0', 'max:104'],
            'benefit_period_months' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:720'],
            'premium_amount' => ['sometimes', 'numeric', 'min:0'],
            'premium_frequency' => ['sometimes', Rule::in(['monthly', 'quarterly', 'annually'])],
            'occupation_class' => ['sometimes', 'nullable', 'string', 'max:255'],
            'policy_start_date' => ['sometimes', 'date', 'before_or_equal:today'],
            'policy_end_date' => ['sometimes', 'nullable', 'date', 'after:policy_start_date'],
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
