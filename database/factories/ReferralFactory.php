<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Referral;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Referral>
 */
class ReferralFactory extends Factory
{
    protected $model = Referral::class;

    public function definition(): array
    {
        return [
            'referrer_id' => User::factory(),
            'referee_id' => null,
            'referral_code' => strtoupper(fake()->unique()->bothify('REF-??????')),
            'referee_email' => fake()->safeEmail(),
            'status' => 'pending',
            'bonus_applied' => false,
            'referred_at' => fake()->dateTimeBetween('-1 year', 'now'),
            'registered_at' => null,
            'converted_at' => null,
        ];
    }

    /**
     * A referral where the referee has registered.
     */
    public function registered(): static
    {
        return $this->state(fn (array $attributes) => [
            'referee_id' => User::factory(),
            'status' => 'registered',
            'registered_at' => fake()->dateTimeBetween('-6 months', 'now'),
        ]);
    }

    /**
     * A referral where the referee has converted (subscribed).
     */
    public function converted(): static
    {
        $registeredAt = fake()->dateTimeBetween('-6 months', '-1 day');

        return $this->state(fn (array $attributes) => [
            'referee_id' => User::factory(),
            'status' => 'converted',
            'registered_at' => $registeredAt,
            'converted_at' => fake()->dateTimeBetween($registeredAt, 'now'),
            'bonus_applied' => true,
        ]);
    }
}
