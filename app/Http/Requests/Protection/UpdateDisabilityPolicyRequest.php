<?php

declare(strict_types=1);

namespace App\Http\Requests\Protection;

use Illuminate\Validation\Rule;

class UpdateDisabilityPolicyRequest extends BasePolicyRequest
{
    public function rules(): array
    {
        $commonOverrides = [
            'provider' => ['sometimes', 'string', 'max:255'],
            'policy_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'premium_amount' => ['sometimes', 'numeric', 'min:0', 'max:9999999.99'],
            'premium_frequency' => ['sometimes', Rule::in(['monthly', 'quarterly', 'annually'])],
            'policy_start_date' => ['sometimes', 'date', 'before_or_equal:today'],
            'policy_end_date' => ['sometimes', 'nullable', 'date', 'after:policy_start_date'],
            'policy_term_years' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:50'],
        ];

        return array_merge($commonOverrides, [
            'benefit_amount' => ['sometimes', 'numeric', 'min:100', 'max:9999999.99'],
            'benefit_frequency' => ['sometimes', Rule::in(['monthly', 'weekly'])],
            'deferred_period_weeks' => ['sometimes', 'integer', 'min:0', 'max:104'],
            'benefit_period_months' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:720'],
            'occupation_class' => ['sometimes', 'nullable', 'string', 'max:255'],
            'coverage_type' => ['sometimes', Rule::in(['accident_only', 'accident_and_sickness'])],
        ]);
    }
}
