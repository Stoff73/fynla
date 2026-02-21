<?php

declare(strict_types=1);

namespace App\Http\Requests\Protection;

use Illuminate\Validation\Rule;

class UpdateSicknessIllnessPolicyRequest extends BasePolicyRequest
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
            'benefit_frequency' => ['sometimes', Rule::in(['monthly', 'weekly', 'lump_sum'])],
            'deferred_period_weeks' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:104'],
            'benefit_period_months' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:720'],
            'conditions_covered' => ['sometimes', 'nullable', 'array'],
            'conditions_covered.*' => ['string', 'max:255'],
            'exclusions' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);
    }
}
