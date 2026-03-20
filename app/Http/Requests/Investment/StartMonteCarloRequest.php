<?php

declare(strict_types=1);

namespace App\Http\Requests\Investment;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request for starting Monte Carlo simulation.
 */
class StartMonteCarloRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'start_value' => 'sometimes|numeric|min:0',
            'monthly_contribution' => 'sometimes|numeric|min:0',
            'expected_return' => 'sometimes|numeric|min:0|max:0.5',
            'volatility' => 'sometimes|numeric|min:0|max:1',
            'years' => 'sometimes|integer|min:1|max:50',
            'iterations' => 'nullable|integer|min:100|max:10000',
            'goal_amount' => 'nullable|numeric|min:0',
        ];
    }
}
