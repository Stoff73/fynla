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
        // Two states since 2026-09-08: a subscription is premium, or it is not one.
        $plan = 'premium';
        $billingCycle = fake()->randomElement(['monthly', 'yearly']);
        $amount = $billingCycle === 'monthly' ? 699 : 5999;

        return [
            'user_id' => User::factory(),
            'plan' => $plan,
            'billing_cycle' => $billingCycle,
            'status' => 'active',
            'current_period_start' => now(),
            'current_period_end' => $billingCycle === 'monthly' ? now()->addMonth() : now()->addYear(),
            'revolut_order_id' => 'rev_'.fake()->uuid(),
            'amount' => $amount,
        ];
    }

    /**
     * A pending checkout that does not confer paid entitlement.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Subscription::STATUS_PENDING,
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
