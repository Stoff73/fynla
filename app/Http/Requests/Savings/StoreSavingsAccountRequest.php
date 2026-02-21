<?php

declare(strict_types=1);

namespace App\Http\Requests\Savings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSavingsAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_type' => 'nullable|string|max:255',
            'institution' => 'nullable|string|max:255',
            'account_number' => [
                'nullable',
                'string',
                'max:20',
                function (string $attribute, mixed $value, \Closure $fail) {
                    $cleaned = preg_replace('/[\s\-]/', '', $value);
                    if (! preg_match('/^[A-Za-z0-9]{4,20}$/', $cleaned)) {
                        $fail('The account number format is invalid. UK accounts should be 8 digits.');
                    }
                },
            ],
            'current_balance' => 'nullable|numeric|min:0',
            'interest_rate' => 'nullable|numeric|min:0|max:20',
            'access_type' => 'nullable|in:immediate,notice,fixed',
            'notice_period_days' => 'nullable|integer|min:0',
            'maturity_date' => 'nullable|date|after:today',
            'is_emergency_fund' => 'nullable|boolean',
            'is_isa' => 'nullable|boolean',
            'country' => 'nullable|string|max:255',
            'isa_type' => 'nullable|in:cash,stocks_shares,LISA',
            'isa_subscription_year' => 'nullable|string',
            'isa_subscription_amount' => 'nullable|numeric|min:0',

            // Ownership - defaults to 'individual' if not provided
            'ownership_type' => ['nullable', Rule::in(['individual', 'joint', 'trust'])],
            'ownership_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'joint_owner_id' => ['nullable', 'exists:users,id'],
            'trust_id' => ['nullable', 'exists:trusts,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'interest_rate.max' => 'Interest rate cannot exceed 20% (please enter realistic values)',
            'isa_type.required_if' => 'ISA type is required when account is an ISA',
            'isa_subscription_year.required_if' => 'ISA subscription year is required when account is an ISA',
            'isa_subscription_amount.required_if' => 'ISA subscription amount is required when account is an ISA',
        ];
    }
}
