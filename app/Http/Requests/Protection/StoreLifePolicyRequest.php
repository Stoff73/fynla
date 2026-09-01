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
            'joint_life' => ['nullable', 'boolean'],
            // W-0200 — the second life assured, named rather than inferred from
            // users.spouse_id. The id where they hold an account, the free-text
            // name where they do not; the same pair every other shared record uses.
            'joint_life_with_user_id' => ['nullable', 'exists:users,id'],
            'joint_life_with_name' => ['nullable', 'string', 'max:255'],
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
