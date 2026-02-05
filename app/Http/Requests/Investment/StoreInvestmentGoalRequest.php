<?php

declare(strict_types=1);

namespace App\Http\Requests\Investment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form Request for creating investment goals.
 */
class StoreInvestmentGoalRequest extends FormRequest
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
            'goal_name' => 'required|string|max:255',
            'goal_type' => ['required', Rule::in(['retirement', 'education', 'wealth', 'home'])],
            'target_amount' => 'required|numeric|min:0',
            'target_date' => 'required|date',
            'priority' => ['nullable', Rule::in(['high', 'medium', 'low'])],
            'is_essential' => 'nullable|boolean',
            'linked_account_ids' => 'nullable|array',
        ];
    }
}
