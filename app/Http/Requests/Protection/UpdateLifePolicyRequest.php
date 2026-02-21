<?php

declare(strict_types=1);

namespace App\Http\Requests\Protection;

use Illuminate\Validation\Rule;

class UpdateLifePolicyRequest extends BasePolicyRequest
{
    public function rules(): array
    {
        $specificRules = [
            'policy_type' => ['sometimes', Rule::in(['term', 'whole_of_life', 'decreasing_term', 'family_income_benefit', 'level_term'])],
            'in_trust' => ['sometimes', 'boolean'],
            'is_mortgage_protection' => ['sometimes', 'boolean'],
            'beneficiaries' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'indexation_rate' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:0.10'],
            'start_value' => ['sometimes', 'nullable', 'numeric', 'min:1000', 'max:9999999999999.99'],
            'decreasing_rate' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:1'],
        ];

        // Override common rules with 'sometimes' for updates
        $commonOverrides = [
            'provider' => ['sometimes', 'string', 'max:255'],
            'policy_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sum_assured' => ['sometimes', 'numeric', 'min:0', 'max:9999999999999.99'],
            'premium_amount' => ['sometimes', 'numeric', 'min:0', 'max:9999999.99'],
            'premium_frequency' => ['sometimes', Rule::in(['monthly', 'quarterly', 'annually'])],
            'policy_start_date' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
            'policy_end_date' => ['sometimes', 'nullable', 'date', 'after:today'],
            'policy_term_years' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:50'],
        ];

        return array_merge($commonOverrides, $specificRules);
    }
}
