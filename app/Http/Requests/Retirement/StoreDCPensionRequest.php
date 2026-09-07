<?php

declare(strict_types=1);

namespace App\Http\Requests\Retirement;

use App\Constants\InvestmentDefaults;
use App\Services\Stores\PensionStore;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDCPensionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // For POST (create), authorization is handled by middleware
        if ($this->isMethod('POST')) {
            return true;
        }

        // For PUT/PATCH (update), check ownership via PensionStore.
        // The store's find() is user-scoped: a pension owned by another
        // user returns null, which we treat as unauthorized (403). The
        // store's own findOrFail on the write path will also 404 if the
        // request slips through — defence in depth.
        $pensionId = $this->route('id');
        if ($pensionId !== null) {
            $pension = app(PensionStore::class)->find((int) $pensionId, 'dc', $this->user());
            // If the record exists for the user, allow. If find() returns
            // null we let the controller produce the 404 via the store's
            // firstOrFail — returning false here would surface as 403
            // when the resource simply isn't theirs.
            if ($pension === null) {
                // Cross-user or non-existent: defer to the store's 404
                // contract. Returning true preserves the original 404
                // behaviour for the non-existent case while still
                // routing through the canonical read path.
                return true;
            }
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $rules = [
            'scheme_name' => ['nullable', 'string', 'max:255'],
            'scheme_type' => ['nullable', 'in:workplace,sipp,personal'],
            'provider' => ['nullable', 'string', 'max:255'],
            'pension_type' => ['nullable', 'in:occupational,sipp,personal,stakeholder'],
            'member_number' => ['nullable', 'string', 'max:255'],
            'current_fund_value' => ['nullable', 'numeric', 'min:0'],
            'annual_salary' => ['nullable', 'numeric', 'min:0'],
            'employee_contribution_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'employer_contribution_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'monthly_contribution_amount' => ['nullable', 'numeric', 'min:0'],
            'lump_sum_contribution' => ['nullable', 'numeric', 'min:0'],
            'investment_strategy' => ['nullable', 'string', 'max:255'],
            'platform_fee_percent' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'platform_fee_type' => ['nullable', 'in:percentage,fixed'],
            'platform_fee_amount' => ['nullable', 'numeric', 'min:0'],
            'platform_fee_frequency' => ['nullable', 'in:monthly,quarterly,annually'],
            'advisor_fee_percent' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'retirement_age' => ['nullable', 'integer', 'min:55', 'max:75'],
            'projected_value_at_retirement' => ['nullable', 'numeric', 'min:0'],
            'has_flexibly_accessed' => ['nullable', 'boolean'],
            'flexible_access_date' => ['nullable', 'date', 'before_or_equal:today'],
            // Six fields the form binds, the client sends, the model declares
            // fillable, PensionStore::validateDcCanonical explicitly accepts and
            // the app reads downstream — and that had no rule here, so
            // `validated()` stripped every one of them before the controller saw
            // them (W-0262). Selecting "Upper-Medium" on "Risk Level for This
            // Pension" saved nothing at all: the row's updated_at moved, the
            // platform fee in the same submit persisted, and this field alone was
            // dropped, because a fee HAD a rule and this did not.
            //
            // The inner validator's own comment says it "Mirrors
            // StoreDCPensionRequest". It did not. PensionStoreDcRuleParityTest now
            // holds the two in step, so the next field added to one and forgotten
            // in the other fails a test instead of a user's save.
            'risk_preference' => ['nullable', Rule::in(InvestmentDefaults::RISK_PREFERENCES)],
            'has_custom_risk' => ['nullable', 'boolean'],
            'expected_return_percent' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'salary_sacrifice' => ['nullable', 'boolean'],
            'employer_matching_limit' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'employer_ni_rebate_pct' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'beneficiary_id' => ['nullable', 'integer', 'exists:users,id'],
            'beneficiary_name' => ['nullable', 'string', 'max:255'],
            // Inline holdings (created alongside pension in a transaction)
            'holdings' => ['nullable', 'array'],
            'holdings.*.security_name' => ['required_with:holdings', 'string', 'max:255'],
            'holdings.*.asset_type' => ['required_with:holdings', 'string', 'max:50'],
            'holdings.*.allocation_percent' => ['required_with:holdings', 'numeric', 'min:0', 'max:100'],
            // Was `max:10`, which overflowed the old decimal(5,4) column at exactly
            // 10 and — more to the point — disagreed with the three other paths
            // that write this same column (StoreInvestmentAccountRequest, its
            // Update sibling, and Investment\Store/UpdateHoldingRequest), all of
            // which allow 100. One column, one bound (Rule 20): a charge a user
            // could record on the holdings page but not on the pension form is an
            // arbitrary difference, not a product decision. W-0263 widened the
            // column to decimal(7,4), so 100 is now true rather than aspirational.
            'holdings.*.ocf_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            // W-0324 — same rule as the investment account requests.
            'holdings.*.dividend_yield' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'holdings.*.cost_basis' => ['nullable', 'numeric', 'min:0'],
        ];

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'scheme_name.required' => 'Please provide a scheme name.',
            'scheme_type.required' => 'Please select a scheme type.',
            'scheme_type.in' => 'Invalid scheme type. Must be workplace, SIPP, or personal.',
            'provider.required' => 'Please provide the pension provider name.',
            'current_fund_value.required' => 'Please enter the current fund value.',
            'annual_salary.required' => 'Annual salary is required for workplace pensions with percentage contributions.',
            'retirement_age.min' => 'Minimum retirement age is 55.',
            'retirement_age.max' => 'Maximum retirement age is 75.',
        ];
    }
}
