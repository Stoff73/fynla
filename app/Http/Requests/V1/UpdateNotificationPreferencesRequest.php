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
        ];
    }
}
