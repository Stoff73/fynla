<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTierConfigurationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->is_admin);
    }

    public function rules(): array
    {
        return [
            'display_name' => 'sometimes|string|max:255',
            'price_monthly_pence' => 'sometimes|integer|min:0',
            'price_annual_pence' => 'sometimes|integer|min:0',
            'capability_matrix' => 'sometimes|array',
            'count_caps' => 'sometimes|array',
            'document_upload_allowance' => 'sometimes|nullable|integer|min:0',
            'document_storage_gb' => 'sometimes|nullable|numeric|min:0',
            'fyn_weekly_token_budget' => 'sometimes|integer|min:0',
            'fyn_daily_hard_backstop' => 'sometimes|integer|min:0',
            'currency_display_mode' => 'sometimes|in:gbp_only,user_choice',
            'snapshot_surfacing_window_days' => 'sometimes|nullable|integer|min:0',
            'open_api_affordance' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
