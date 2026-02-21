<?php

declare(strict_types=1);

namespace App\Http\Requests\Protection;

use Illuminate\Validation\Rule;

class StoreDisabilityPolicyRequest extends BasePolicyRequest
{
    public function rules(): array
    {
        return $this->mergeWithCommonRules([
            'benefit_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'benefit_frequency' => ['nullable', Rule::in(['monthly', 'weekly'])],
            'deferred_period_weeks' => ['nullable', 'integer', 'min:0', 'max:104'],
            'benefit_period_months' => ['nullable', 'integer', 'min:1', 'max:720'],
            'occupation_class' => ['nullable', 'string', 'max:255'],
            'coverage_type' => ['nullable', Rule::in(['accident_only', 'accident_and_sickness'])],
        ]);
    }

    public function messages(): array
    {
        return $this->mergeWithCommonMessages([
            'coverage_type.in' => 'Coverage type must be accident only or accident and sickness.',
        ]);
    }
}
