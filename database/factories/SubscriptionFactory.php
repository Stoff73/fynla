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
        $plan = fake()->randomElement(['student', 'standard', 'family', 'pro']);
        $billingCycle = fake()->randomElement(['monthly', 'yearly']);

        $amount = match ($plan) {
            'student' => $billingCycle === 'monthly' ? 399 : 3000,
            'standard' => $billingCycle === 'monthly' ? 1099 : 10000,
            'family' => $billingCycle === 'monthly' ? 1499 : 14000,
            'pro' => $billingCycle === 'monthly' ? 1999 : 20000,
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
            'revolut_order_id' => 'rev_'.fake()->uuid(),
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
            'cancelled_at' => now(),
        ]);
    }

    /**
     * A past_due subscription.
     */
    public function pastDue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'past_due',
        ]);
    }

    /**
     * Set a specific plan.
     */
    public function plan(string $plan): static
    {
        return $this->state(fn (array $attributes) => [
            'plan' => $plan,
        ]);
    }

    /**
     * Set a specific billing cycle.
     */
    public function billingCycle(string $cycle): static
    {
        return $this->state(fn (array $attributes) => [
            'billing_cycle' => $cycle,
        ]);
    }
}
