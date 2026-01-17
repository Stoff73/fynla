<?php

declare(strict_types=1);

namespace App\Http\Requests\Goals;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'goal_name' => 'sometimes|string|max:100',
            'goal_type' => 'sometimes|string|in:emergency_fund,property_purchase,home_deposit,education,retirement,wealth_accumulation,wedding,holiday,car_purchase,debt_repayment,custom',
            'custom_goal_type_name' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:500',
            'target_amount' => 'sometimes|numeric|min:1|max:100000000',
            'current_amount' => 'sometimes|numeric|min:0',
            'target_date' => 'sometimes|date',
            'start_date' => 'nullable|date',
            'assigned_module' => 'sometimes|string|in:savings,investment,property,retirement',
            'module_override' => 'sometimes|boolean',
            'priority' => 'sometimes|string|in:critical,high,medium,low',
            'is_essential' => 'sometimes|boolean',
            'status' => 'sometimes|string|in:active,paused,completed,abandoned',
            'monthly_contribution' => 'nullable|numeric|min:0',
            'contribution_frequency' => 'sometimes|string|in:weekly,monthly,quarterly,annually',
            'linked_account_ids' => 'nullable|array',
            'linked_savings_account_id' => 'nullable|integer|exists:savings_accounts,id',
            'risk_preference' => 'nullable|integer|min:1|max:5',
            'use_global_risk_profile' => 'sometimes|boolean',
            'ownership_type' => 'sometimes|string|in:individual,joint',
            'joint_owner_id' => 'nullable|integer|exists:users,id',
            'ownership_percentage' => 'nullable|numeric|min:1|max:100',
            // Property-specific fields
            'property_location' => 'nullable|string|max:255',
            'property_type' => 'nullable|string|in:house,flat,bungalow,terraced,semi_detached,detached,other',
            'is_first_time_buyer' => 'nullable|boolean',
            'estimated_property_price' => 'nullable|numeric|min:0',
            'deposit_percentage' => 'nullable|numeric|min:0|max:100',
            // Completion fields
            'completion_notes' => 'nullable|string|max:500',
        ];
    }
}
