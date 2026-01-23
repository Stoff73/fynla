<?php

declare(strict_types=1);

namespace App\Http\Requests\Chattel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChattelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Core chattel info
            'chattel_type' => ['nullable', Rule::in(['vehicle', 'art', 'antique', 'jewelry', 'collectible', 'other'])],
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'country' => ['nullable', 'string', 'max:255'],

            // Ownership
            'ownership_type' => ['nullable', Rule::in(['individual', 'joint', 'trust'])],
            'ownership_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'joint_owner_id' => ['nullable', 'exists:users,id'],
            'joint_owner_name' => ['nullable', 'string', 'max:255'],
            'household_id' => ['nullable', 'exists:households,id'],
            'trust_id' => ['nullable', 'exists:trusts,id'],

            // Valuation
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'purchase_date' => ['nullable', 'date'],
            'current_value' => ['nullable', 'numeric', 'min:0'],
            'valuation_date' => ['nullable', 'date'],

            // Vehicle-specific (conditional)
            'make' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'year' => ['nullable', 'integer', 'min:1900', 'max:'.(date('Y') + 1)],
            'registration_number' => ['nullable', 'string', 'max:20'],

            // Notes
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'chattel_type.required' => 'Please select a type.',
            'chattel_type.in' => 'Please select a valid type.',
            'name.required' => 'Please provide a name for this item.',
            'current_value.required' => 'Please provide the current value.',
            'current_value.numeric' => 'Current value must be a number.',
            'current_value.min' => 'Current value cannot be negative.',
            'joint_owner_id.exists' => 'The selected joint owner is not valid.',
        ];
    }
}
