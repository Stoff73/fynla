<?php

declare(strict_types=1);

namespace App\Http\Requests\Investment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form Request for creating/updating risk profiles.
 */
class StoreRiskProfileRequest extends FormRequest
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
            'risk_tolerance' => ['required', Rule::in(['cautious', 'balanced', 'adventurous'])],
            'capacity_for_loss_percent' => 'required|numeric|min:0|max:100',
            'time_horizon_years' => 'required|integer|min:0|max:100',
            'knowledge_level' => ['required', Rule::in(['novice', 'intermediate', 'experienced'])],
            'attitude_to_volatility' => 'nullable|string|max:255',
            'esg_preference' => 'nullable|boolean',
        ];
    }
}
