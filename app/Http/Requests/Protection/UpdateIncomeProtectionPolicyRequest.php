<?php

declare(strict_types=1);

namespace App\Http\Requests\Protection;

use Illuminate\Validation\Rule;

class UpdateIncomeProtectionPolicyRequest extends BasePolicyRequest
{
    public function rules(): array
    {
        $commonOverrides = [
            'provider' => ['sometimes', 'string', 'max:255'],
            'policy_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'premium_amount' => ['sometimes', 'numeric', 'min:0'],
            'premium_frequency' => ['sometimes', Rule::in(['monthly', 'quarterly', 'annually'])],
            'policy_start_date' => ['sometimes', 'date', 'before_or_equal:today'],
            'policy_end_date' => ['sometimes', 'nullable', 'date', 'after:policy_start_date'],
        ];

        return array_merge($commonOverrides, [
            'benefit_amount' => ['sometimes', 'numeric', 'min:1000'],
            'benefit_frequency' => ['sometimes', Rule::in(['monthly', 'weekly'])],
            'deferred_period_weeks' => ['sometimes', 'integer', 'min:0', 'max:104'],
            'benefit_period_months' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:720'],
            'occupation_class' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);
    }

    public function messages(): array
    {
        return $this->mergeWithCommonMessages([
            'benefit_amount.min' => 'The benefit amount must be at least £1,000.',
        ]);
    }
}
