<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'subscription_id' => Subscription::factory(),
            'user_id' => User::factory(),
            'revolut_order_id' => 'rev_'.fake()->uuid(),
            'amount' => fake()->randomElement([499, 999, 1999, 4999]),
            'currency' => 'GBP',
            'status' => 'completed',
            'revolut_payment_data' => [
                'order_id' => 'rev_'.fake()->uuid(),
                'state' => 'COMPLETED',
                'created_at' => now()->toIso8601String(),
            ],
        ];
    }

    /**
     * A pending payment.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'revolut_payment_data' => [
                'order_id' => $attributes['revolut_order_id'] ?? 'rev_'.fake()->uuid(),
                'state' => 'PENDING',
                'created_at' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * A failed payment.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'revolut_payment_data' => [
                'order_id' => $attributes['revolut_order_id'] ?? 'rev_'.fake()->uuid(),
                'state' => 'FAILED',
                'failure_reason' => 'insufficient_funds',
                'created_at' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * A refunded payment.
     */
    public function refunded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'refunded',
            'revolut_payment_data' => [
                'order_id' => $attributes['revolut_order_id'] ?? 'rev_'.fake()->uuid(),
                'state' => 'REFUNDED',
                'created_at' => now()->toIso8601String(),
            ],
        ]);
    }
}
