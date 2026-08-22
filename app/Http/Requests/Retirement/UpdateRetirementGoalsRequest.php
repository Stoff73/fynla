<?php

declare(strict_types=1);

namespace App\Http\Requests\Retirement;

use App\Constants\ValidationLimits;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for the retirement goals a user sets for themselves (W-0035).
 *
 * Bounds come from `ValidationLimits`, which is the enforcing declaration, rather
 * than from numbers typed in here. Three different retirement-age ranges were in
 * force when this was written — `ValidationLimits` 50-100, Fyn's
 * `capture_retirement_goals` 55-75 (`CoordinatingAgent.php:5610`) and
 * `UpdateIncomeOccupationRequest.php:30` 30-100 for the `users` column — so picking
 * the constants class is the only choice that does not add a fourth.
 */
class UpdateRetirementGoalsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'target_retirement_age' => [
                'sometimes',
                'nullable',
                'integer',
                'min:'.ValidationLimits::MIN_RETIREMENT_AGE,
                'max:'.ValidationLimits::MAX_RETIREMENT_AGE,
            ],
            'target_retirement_income' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:'.ValidationLimits::MIN_CURRENCY_VALUE,
                'max:'.ValidationLimits::MAX_CURRENCY_VALUE,
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'target_retirement_age.min' => 'Target retirement age must be at least '.ValidationLimits::MIN_RETIREMENT_AGE.'.',
            'target_retirement_age.max' => 'Target retirement age cannot be more than '.ValidationLimits::MAX_RETIREMENT_AGE.'.',
            'target_retirement_income.min' => 'Target retirement income cannot be negative.',
        ];
    }
}
