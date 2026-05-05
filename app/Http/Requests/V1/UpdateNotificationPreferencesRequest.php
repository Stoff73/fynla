<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'policy_renewals' => ['nullable', 'boolean'],
            'goal_milestones' => ['nullable', 'boolean'],
            'contribution_reminders' => ['nullable', 'boolean'],
            'market_updates' => ['nullable', 'boolean'],
            'fyn_daily_insight' => ['nullable', 'boolean'],
            'security_alerts' => ['nullable', 'boolean'],
            'payment_alerts' => ['nullable', 'boolean'],
            'mortgage_rate_alerts' => ['nullable', 'boolean'],
            'estate_alerts' => ['nullable', 'boolean'],
            'lifecycle_empty_trialer' => ['nullable', 'boolean'],
            'lifecycle_engaged_trialer' => ['nullable', 'boolean'],
            'lifecycle_cancelled_trialer' => ['nullable', 'boolean'],
            'lifecycle_churned_subscriber' => ['nullable', 'boolean'],
            'lifecycle_lapsed_subscriber' => ['nullable', 'boolean'],
        ];
    }
}
