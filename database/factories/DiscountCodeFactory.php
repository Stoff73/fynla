<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DiscountCode;
use Illuminate\Database\Eloquent\Factories\Factory;

class DiscountCodeFactory extends Factory
{
    protected $model = DiscountCode::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('??##??')),
            'type' => 'percentage',
            'value' => fake()->numberBetween(5, 50),
            'max_uses' => fake()->optional()->numberBetween(10, 1000),
            'times_used' => 0,
            'max_uses_per_user' => 1,
            'applicable_plans' => null,
            'applicable_cycles' => null,
            'starts_at' => null,
            'expires_at' => null,
            'is_active' => true,
        ];
    }

    public function percentage(int $value = 20): static
    {
        return $this->state(fn () => [
            'type' => 'percentage',
            'value' => $value,
        ]);
    }

    public function fixedAmount(int $pence = 1000): static
    {
        return $this->state(fn () => [
            'type' => 'fixed_amount',
            'value' => $pence,
        ]);
    }

    public function trialExtension(int $days = 14): static
    {
        return $this->state(fn () => [
            'type' => 'trial_extension',
            'value' => $days,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'expires_at' => now()->subDay(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }

    public function exhausted(): static
    {
        return $this->state(fn () => [
            'max_uses' => 10,
            'times_used' => 10,
        ]);
    }

    public function forPlans(array $plans): static
    {
        return $this->state(fn () => [
            'applicable_plans' => $plans,
        ]);
    }

    public function forCycles(array $cycles): static
    {
        return $this->state(fn () => [
            'applicable_cycles' => $cycles,
        ]);
    }
}
