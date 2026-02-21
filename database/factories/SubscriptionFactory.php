<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        $plan = $this->faker->randomElement(['student', 'standard', 'pro']);
        $billingCycle = $this->faker->randomElement(['monthly', 'yearly']);

        $amount = match ($plan) {
            'student' => $billingCycle === 'monthly' ? 499 : 4990,
            'standard' => $billingCycle === 'monthly' ? 999 : 9990,
            'pro' => $billingCycle === 'monthly' ? 1999 : 19990,
        };

        return [
            'user_id' => User::factory(),
            'plan' => $plan,
            'billing_cycle' => $billingCycle,
            'status' => 'active',
            'trial_started_at' => null,
            'trial_ends_at' => null,
            'current_period_start' => now(),
            'current_period_end' => $billingCycle === 'monthly' ? now()->addMonth() : now()->addYear(),
            'revolut_order_id' => 'rev_' . $this->faker->uuid(),
            'amount' => $amount,
        ];
    }

    /**
     * A trialing subscription.
     */
    public function trialing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'trialing',
            'trial_started_at' => now(),
            'trial_ends_at' => now()->addDays(14),
            'current_period_start' => null,
            'current_period_end' => null,
            'revolut_order_id' => null,
        ]);
    }

    /**
     * An expired subscription.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'expired',
            'current_period_start' => now()->subMonth(),
            'current_period_end' => now()->subDay(),
        ]);
    }

    /**
     * A cancelled subscription.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
        ]);
    }
}
