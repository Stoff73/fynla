<?php

declare(strict_types=1);

namespace App\Http\Requests\Protection;

use Illuminate\Validation\Rule;

class StoreIncomeProtectionPolicyRequest extends BasePolicyRequest
{
    public function rules(): array
    {
        return $this->mergeWithCommonRules([
            'benefit_amount' => ['sometimes', 'numeric', 'min:1000'],
            'benefit_frequency' => ['sometimes', Rule::in(['monthly', 'weekly'])],
            'deferred_period_weeks' => ['nullable', 'integer', 'min:0', 'max:104'],
            'benefit_period_months' => ['nullable', 'integer', 'min:1', 'max:720'],
            'occupation_class' => ['nullable', 'string', 'max:255'],
        ]);
    }

    public function messages(): array
    {
        return $this->mergeWithCommonMessages([
            'benefit_amount.min' => 'The benefit amount must be at least £1,000.',
        ]);
    }
}
