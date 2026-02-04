<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLifeEventRequest extends FormRequest
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
            'event_name' => 'sometimes|string|max:100',
            'event_type' => 'sometimes|string|in:inheritance,gift_received,bonus,redundancy_payment,property_sale,business_sale,pension_lump_sum,lottery_windfall,large_purchase,home_improvement,wedding,education_fees,gift_given,medical_expense,custom_income,custom_expense',
            'description' => 'nullable|string|max:1000',
            'amount' => 'sometimes|numeric|min:0.01|max:999999999.99',
            'impact_type' => 'nullable|string|in:income,expense',
            'expected_date' => 'sometimes|date',
            'certainty' => 'nullable|string|in:confirmed,likely,possible,speculative',
            'icon' => 'nullable|string|max:50',
            'show_in_projection' => 'nullable|boolean',
            'show_in_household_view' => 'nullable|boolean',
            'ownership_type' => 'nullable|string|in:individual,joint',
            'joint_owner_id' => 'nullable|integer|exists:users,id',
            'ownership_percentage' => 'nullable|numeric|min:0|max:100',
            'status' => 'nullable|string|in:expected,confirmed,completed,cancelled',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'event_name.max' => 'Event name cannot exceed 100 characters.',
            'event_type.in' => 'Invalid event type selected.',
            'amount.min' => 'Amount must be greater than zero.',
        ];
    }
}
