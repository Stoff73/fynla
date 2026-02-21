<?php

declare(strict_types=1);

namespace App\Http\Requests\Savings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSavingsAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_type' => 'sometimes|string|max:255',
            'institution' => 'sometimes|string|max:255',
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
            'current_balance' => 'sometimes|numeric|min:0',
            'interest_rate' => 'sometimes|numeric|min:0|max:20',
            'access_type' => 'sometimes|in:immediate,notice,fixed',
            'notice_period_days' => 'nullable|integer|min:0',
            'maturity_date' => 'nullable|date',
            'is_emergency_fund' => 'sometimes|boolean',
            'is_isa' => 'sometimes|boolean',
            'ownership_type' => ['sometimes', Rule::in(['individual', 'joint', 'trust'])],
            'ownership_percentage' => 'sometimes|nullable|numeric|min:0|max:100',
            'joint_owner_id' => 'sometimes|nullable|exists:users,id',
            'country' => 'sometimes|nullable|string|max:255',
            'isa_type' => 'nullable|in:cash,stocks_shares,LISA',
            'isa_subscription_year' => 'nullable|string',
            'isa_subscription_amount' => 'nullable|numeric|min:0',
        ];
    }
}
