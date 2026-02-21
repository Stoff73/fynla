<?php

declare(strict_types=1);

namespace App\Http\Requests\Protection;

use Illuminate\Validation\Rule;

class StoreLifePolicyRequest extends BasePolicyRequest
{
    public function rules(): array
    {
        $specificRules = [
            'policy_type' => ['nullable', Rule::in(['term', 'whole_of_life', 'decreasing_term', 'family_income_benefit', 'level_term'])],
            'in_trust' => ['nullable', 'boolean'],
            'is_mortgage_protection' => ['nullable', 'boolean'],
            'beneficiaries' => ['nullable', 'string', 'max:1000'],
            'indexation_rate' => ['nullable', 'numeric', 'min:0', 'max:0.10'],
        ];

        // Conditional rules based on policy type
        $policyType = $this->input('policy_type');
        if ($policyType === 'decreasing_term') {
            $specificRules['start_value'] = ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'];
            $specificRules['decreasing_rate'] = ['nullable', 'numeric', 'min:0', 'max:1'];
        } else {
            $specificRules['start_value'] = ['nullable'];
            $specificRules['decreasing_rate'] = ['nullable'];
        }

        return $this->mergeWithCommonRules($specificRules);
    }

    public function messages(): array
    {
        return $this->mergeWithCommonMessages([
            'policy_type.in' => 'Invalid policy type selected.',
        ]);
    }
}
