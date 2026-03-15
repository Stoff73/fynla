<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationPreferenceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'policy_renewals' => true,
            'goal_milestones' => true,
            'contribution_reminders' => true,
            'market_updates' => false,
            'fyn_daily_insight' => true,
            'security_alerts' => true,
            'payment_alerts' => true,
        ];
    }

    public function allEnabled(): static
    {
        return $this->state(fn () => [
            'market_updates' => true,
        ]);
    }

    public function allDisabled(): static
    {
        return $this->state(fn () => [
            'policy_renewals' => false,
            'goal_milestones' => false,
            'contribution_reminders' => false,
            'market_updates' => false,
            'fyn_daily_insight' => false,
            'security_alerts' => false,
            'payment_alerts' => false,
        ]);
    }
}
