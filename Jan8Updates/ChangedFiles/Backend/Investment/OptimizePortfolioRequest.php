<?php

declare(strict_types=1);

namespace App\Http\Requests\Investment;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for portfolio optimization requests
 */
class OptimizePortfolioRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'optimization_type' => 'nullable|string|in:min_variance,max_sharpe,target_return,risk_parity',
            'target_return' => 'nullable|numeric|min:0|max:1',
            'risk_free_rate' => 'nullable|numeric|min:0|max:0.15',
            'constraints' => 'nullable|array',
            'constraints.min_weight' => 'nullable|numeric|min:0|max:1',
            'constraints.max_weight' => 'nullable|numeric|min:0|max:1',
            'constraints.sector_limits' => 'nullable|array',
            'constraints.sector_limits.*.sector' => 'required_with:constraints.sector_limits|string',
            'constraints.sector_limits.*.max_weight' => 'required_with:constraints.sector_limits|numeric|min:0|max:1',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'optimization_type.required' => 'Please specify an optimization type',
            'optimization_type.in' => 'Invalid optimization type. Must be one of: min_variance, max_sharpe, target_return, risk_parity',
            'target_return.required_if' => 'Target return is required when optimization type is target_return',
            'target_return.min' => 'Target return cannot be negative',
            'target_return.max' => 'Target return cannot exceed 100%',
            'risk_free_rate.min' => 'Risk-free rate cannot be negative',
            'risk_free_rate.max' => 'Risk-free rate seems unrealistic (max 15%)',
            'constraints.min_weight.max' => 'Minimum weight cannot exceed 100%',
            'constraints.max_weight.max' => 'Maximum weight cannot exceed 100%',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'optimization_type' => 'optimization type',
            'target_return' => 'target return',
            'risk_free_rate' => 'risk-free rate',
            'constraints.min_weight' => 'minimum weight constraint',
            'constraints.max_weight' => 'maximum weight constraint',
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Ensure min_weight <= max_weight
            $minWeight = $this->input('constraints.min_weight', 0);
            $maxWeight = $this->input('constraints.max_weight', 1);

            if ($minWeight > $maxWeight) {
                $validator->errors()->add(
                    'constraints.min_weight',
                    'Minimum weight cannot be greater than maximum weight'
                );
            }

            // Validate sector limits don't exceed 100% total
            $sectorLimits = $this->input('constraints.sector_limits', []);
            $totalSectorLimit = array_sum(array_column($sectorLimits, 'max_weight'));

            if ($totalSectorLimit > 1.0) {
                $validator->errors()->add(
                    'constraints.sector_limits',
                    'Total sector limits cannot exceed 100%'
                );
            }
        });
    }
}
