<?php

declare(strict_types=1);

namespace App\Http\Requests\Goals;

use Illuminate\Foundation\Http\FormRequest;

class StoreGoalRequest extends FormRequest
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
            'custom_goal_type_name' => 'nullable|required_if:goal_type,custom|string|max:100',
            'description' => 'nullable|string|max:500',
            'target_amount' => 'sometimes|numeric|min:1|max:100000000',
            'current_amount' => 'nullable|numeric|min:0',
            'target_date' => 'sometimes|date|after:today',
            'start_date' => 'nullable|date',
            'assigned_module' => 'nullable|string|in:savings,investment,property,retirement',
            'module_override' => 'nullable|boolean',
            'priority' => 'nullable|string|in:critical,high,medium,low',
            'is_essential' => 'nullable|boolean',
            'monthly_contribution' => 'nullable|numeric|min:0',
            'contribution_frequency' => 'nullable|string|in:weekly,monthly,quarterly,annually',
            'linked_account_ids' => 'nullable|array',
            'linked_savings_account_id' => 'nullable|integer|exists:savings_accounts,id',
            'risk_preference' => 'nullable|integer|min:1|max:5',
            'use_global_risk_profile' => 'nullable|boolean',
            'ownership_type' => 'nullable|string|in:individual,joint',
            'joint_owner_id' => 'nullable|required_if:ownership_type,joint|integer|exists:users,id',
            'ownership_percentage' => 'nullable|numeric|min:1|max:100',
            // Property-specific fields
            'property_location' => 'nullable|string|max:255',
            'property_type' => 'nullable|string|in:house,flat,bungalow,terraced,semi_detached,detached,other',
            'is_first_time_buyer' => 'nullable|boolean',
            'estimated_property_price' => 'nullable|numeric|min:0',
            'deposit_percentage' => 'nullable|numeric|min:0|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'goal_name.required' => 'Please provide a name for your goal.',
            'goal_type.required' => 'Please select a goal type.',
            'target_amount.required' => 'Please set a target amount for your goal.',
            'target_amount.min' => 'Target amount must be at least £1.',
            'target_date.required' => 'Please set a target date for your goal.',
            'target_date.after' => 'Target date must be in the future.',
            'custom_goal_type_name.required_if' => 'Please provide a name for your custom goal type.',
            'joint_owner_id.required_if' => 'Please select a joint owner for joint goals.',
        ];
    }
}
