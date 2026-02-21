<?php

declare(strict_types=1);

namespace App\Http\Requests\Protection;

use Illuminate\Validation\Rule;

class StoreSicknessIllnessPolicyRequest extends BasePolicyRequest
{
    public function rules(): array
    {
        return $this->mergeWithCommonRules([
            'benefit_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'benefit_frequency' => ['nullable', Rule::in(['monthly', 'weekly', 'lump_sum'])],
            'deferred_period_weeks' => ['nullable', 'integer', 'min:0', 'max:104'],
            'benefit_period_months' => ['nullable', 'integer', 'min:1', 'max:720'],
            'conditions_covered' => ['nullable', 'array'],
            'conditions_covered.*' => ['string', 'max:255'],
            'exclusions' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    public function messages(): array
    {
        return $this->mergeWithCommonMessages([]);
    }
}
