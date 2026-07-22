<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\TaxConfigService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavingsAccount>
 */
class SavingsAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'account_type' => fake()->randomElement(['easy_access', 'notice', 'fixed_rate']),
            'institution' => fake()->company(),
            'account_number' => fake()->numerify('########'),
            'current_balance' => fake()->randomFloat(2, 100, 50000),
            'interest_rate' => fake()->randomFloat(4, 0.01, 0.05),
            'access_type' => fake()->randomElement(['immediate', 'notice', 'fixed']),
            'notice_period_days' => null,
            'maturity_date' => null,
            'is_isa' => false,
            'isa_type' => null,
            'isa_subscription_year' => null,
            'isa_subscription_amount' => null,
        ];
    }

    public function joint(?User $partner = null): static
    {
        return $this->state(fn (array $attrs) => [
            'ownership_type' => 'joint',
            'joint_owner_id' => $partner?->id ?? User::factory(),
            'ownership_percentage' => 50.00,
            // Joint ISAs do not exist in UK law — never combine isa() with joint().
            'is_isa' => false,
        ]);
    }

    public function isa(): static
    {
        return $this->state(fn () => [
            'account_type' => 'cash_isa',
            'is_isa' => true,
            'isa_type' => 'cash',
            'isa_subscription_year' => app(TaxConfigService::class)->getTaxYear(),
            'isa_subscription_amount' => fake()->randomFloat(2, 1000, 20000),
            // Explicit: ISAs are individual-only.
            'ownership_type' => 'individual',
            'joint_owner_id' => null,
            'ownership_percentage' => 100.00,
        ]);
    }
}
