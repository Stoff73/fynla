<?php

declare(strict_types=1);

namespace App\Http\Requests\Protection;

use Illuminate\Validation\Rule;

class UpdateCriticalIllnessPolicyRequest extends BasePolicyRequest
{
    public function rules(): array
    {
        $commonOverrides = [
            'provider' => ['sometimes', 'string', 'max:255'],
            'policy_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sum_assured' => ['sometimes', 'numeric', 'min:1000'],
            'premium_amount' => ['sometimes', 'numeric', 'min:0'],
            'premium_frequency' => ['sometimes', Rule::in(['monthly', 'quarterly', 'annually'])],
            'policy_start_date' => ['sometimes', 'date', 'before_or_equal:today'],
            'policy_end_date' => ['sometimes', 'nullable', 'date', 'after:policy_start_date'],
            'policy_term_years' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];

        return array_merge($commonOverrides, [
            'policy_type' => ['sometimes', Rule::in(['standalone', 'accelerated', 'additional'])],
            'conditions_covered' => ['sometimes', 'nullable', 'array'],
        ]);
    }

    public function messages(): array
    {
        return $this->mergeWithCommonMessages([
            'policy_type.in' => 'Invalid policy type. Choose from standalone, accelerated, or additional.',
            'sum_assured.min' => 'Sum assured must be at least £1,000.',
        ]);
    }
}
