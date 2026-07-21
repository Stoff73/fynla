<?php

declare(strict_types=1);

namespace App\Http\Requests\Savings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSavingsAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The savings_accounts.country column is NOT NULL DEFAULT 'United Kingdom'.
     * Drop the key when it arrives null/empty so the DB default kicks in instead
     * of an integrity-constraint 500. Same pattern as PR #269 (investment_accounts).
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('country') && in_array($this->input('country'), [null, ''], true)) {
            $this->offsetUnset('country');
        }
    }

    public function rules(): array
    {
        return [
            'account_name' => 'nullable|string|max:255',
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
            'ownership_type' => ['nullable', Rule::in(['individual', 'joint', 'tenants_in_common', 'trust'])],
            'ownership_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'joint_owner_id' => ['nullable', 'exists:users,id'],
            'trust_id' => ['nullable', 'exists:trusts,id'],
        ];
    }

    /**
     * Joint ISAs do not exist in UK law — every ISA is held in a single name.
     * Reject combinations that try to create one before they reach the controller.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $isIsa = $this->boolean('is_isa') || in_array($this->input('account_type'), ['cash_isa', 'stocks_shares_isa', 'lifetime_isa', 'innovative_finance_isa'], true);
            $ownershipType = $this->input('ownership_type');
            $isJoint = in_array($ownershipType, ['joint', 'tenants_in_common'], true) || $this->filled('joint_owner_id');

            if (in_array($ownershipType, ['joint', 'tenants_in_common'], true) && ! $this->filled('ownership_percentage')) {
                $v->errors()->add('ownership_percentage', 'An explicit ownership share is required for a shared account.');
            } elseif (in_array($ownershipType, ['joint', 'tenants_in_common'], true)) {
                $share = (float) $this->input('ownership_percentage');
                if ($share <= 0 || $share >= 100) {
                    $v->errors()->add('ownership_percentage', 'The ownership share must be between 0% and 100% for a shared account.');
                }
            }

            if ($isIsa && $isJoint) {
                $v->errors()->add('ownership_type', 'ISAs cannot be jointly owned — every ISA is held in a single name under UK law.');
            }
        });
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
