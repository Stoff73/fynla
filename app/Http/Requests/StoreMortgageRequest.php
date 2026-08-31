<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Traits\ValidatesSharedOwnership;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMortgageRequest extends FormRequest
{
    use ValidatesSharedOwnership;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The mortgages.country column is NOT NULL DEFAULT 'United Kingdom'.
     * Drop the key when it arrives null/empty so the DB default kicks in instead
     * of an integrity-constraint 500. Same pattern as PR #269 (investment_accounts).
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('country') && in_array($this->input('country'), [null, ''], true)) {
            $this->offsetUnset('country');
        }
    }

    /**
     * Get the validation rules that apply to the request.
     */
    /**
     * W-0142 — a jointly-held mortgage must name the other borrower.
     *
     * `ownership_type` and `joint_owner_id` were both accepted here with nothing
     * requiring the second where the first says the debt is shared, so a joint
     * mortgage could be stored with half of it owed by nobody.
     *
     * This is not the same question as the SHARE, which resolves from the
     * securing property rather than from the mortgage row (CSJ's W-0228 ruling)
     * — hence the counterparty check and not the split check. The two disagreeing
     * is exactly the case W-0338 had to handle on the read side.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $this->validateSharedOwnershipCounterparty($v, $this->input('ownership_type'), $this->all());
        });
    }

    public function rules(): array
    {
        return [
            'lender_name' => ['nullable', 'string', 'max:255'],
            'mortgage_account_number' => ['nullable', 'string', 'max:50'],
            'mortgage_type' => ['nullable', Rule::in(['repayment', 'interest_only', 'mixed'])],
            'country' => ['nullable', 'string', 'max:255'],

            // Mixed mortgage type fields (repayment vs interest-only split)
            'repayment_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'interest_only_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],

            // Loan details - all optional now
            'original_loan_amount' => ['nullable', 'numeric', 'min:0'],
            'outstanding_balance' => ['nullable', 'numeric', 'min:0'],

            // Interest - optional, default to 0 if not provided
            'interest_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'rate_type' => ['nullable', Rule::in(['fixed', 'variable', 'tracker', 'discount', 'mixed', 'capped', 'offset'])],
            'rate_fix_end_date' => ['nullable', 'date'],

            // Mixed rate type fields (fixed vs variable split)
            'fixed_rate_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'variable_rate_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'fixed_interest_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'variable_interest_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],

            // Payment
            'monthly_payment' => ['nullable', 'numeric', 'min:0'],
            'monthly_interest_portion' => ['nullable', 'numeric', 'min:0'],

            // Dates
            'start_date' => ['nullable', 'date'],
            'maturity_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'remaining_term_months' => ['nullable', 'integer', 'min:0'],

            // Ownership
            'ownership_type' => ['nullable', Rule::in(['individual', 'joint'])],
            'joint_owner_id' => ['nullable', 'exists:users,id'],
            'joint_owner_name' => ['nullable', 'string', 'max:255'],

            // Notes
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'lender_name' => 'lender name',
            'rate_fix_end_date' => 'rate fix end date',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'maturity_date.after' => 'The maturity date must be after the start date.',
        ];
    }
}
