<?php

declare(strict_types=1);

namespace App\Http\Requests\Protection;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Base class for protection policy requests
 *
 * Contains common validation rules and messages shared across all policy types.
 */
abstract class BasePolicyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get common validation rules shared by all policy types.
     */
    protected function commonRules(): array
    {
        return [
            'provider' => ['nullable', 'string', 'max:255'],
            'policy_number' => ['nullable', 'string', 'max:255'],
            'sum_assured' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'premium_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'premium_frequency' => ['nullable', Rule::in(['monthly', 'quarterly', 'annually'])],
            'policy_start_date' => ['nullable', 'date', 'before_or_equal:today'],
            'policy_end_date' => ['nullable', 'date', 'after:today'],
            'policy_term_years' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    /**
     * Get common validation messages shared by all policy types.
     */
    protected function commonMessages(): array
    {
        return [
            'provider.required' => 'Provider is required.',
            'provider.max' => 'Provider name cannot exceed 255 characters.',
            'policy_number.max' => 'Policy number cannot exceed 255 characters.',
            'sum_assured.required' => 'Sum assured is required.',
            'sum_assured.min' => 'Sum assured must be at least £1,000.',
            'sum_assured.numeric' => 'Sum assured must be a valid number.',
            'premium_amount.required' => 'Premium amount is required.',
            'premium_amount.numeric' => 'Premium amount must be a valid number.',
            'premium_frequency.required' => 'Premium frequency is required.',
            'premium_frequency.in' => 'Premium frequency must be monthly, quarterly, or annually.',
            'policy_start_date.date' => 'Policy start date must be a valid date.',
            'policy_start_date.before_or_equal' => 'Policy start date cannot be in the future.',
            'policy_end_date.date' => 'Policy end date must be a valid date.',
            'policy_end_date.after' => 'Policy end date must be in the future.',
            'policy_term_years.integer' => 'Policy term must be a whole number of years.',
            'policy_term_years.min' => 'Policy term must be at least 1 year.',
            'policy_term_years.max' => 'Policy term cannot exceed 50 years.',
        ];
    }

    /**
     * Merge common rules with specific rules.
     */
    protected function mergeWithCommonRules(array $specificRules): array
    {
        return array_merge($this->commonRules(), $specificRules);
    }

    /**
     * Merge common messages with specific messages.
     */
    protected function mergeWithCommonMessages(array $specificMessages): array
    {
        return array_merge($this->commonMessages(), $specificMessages);
    }
}
