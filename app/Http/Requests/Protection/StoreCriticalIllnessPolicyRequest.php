<?php

declare(strict_types=1);

namespace App\Http\Requests\Protection;

use Illuminate\Validation\Rule;

class StoreCriticalIllnessPolicyRequest extends BasePolicyRequest
{
    public function rules(): array
    {
        return $this->mergeWithCommonRules([
            'policy_type' => ['nullable', Rule::in(['standalone', 'accelerated', 'additional'])],
            'conditions_covered' => ['nullable', 'array'],
        ]);
    }

    public function messages(): array
    {
        return $this->mergeWithCommonMessages([
            'policy_type.in' => 'Invalid policy type. Choose from standalone, accelerated, or additional.',
        ]);
    }
}
