<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CashAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashAccount>
 */
class CashAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Default to non-ISA, individually owned. State methods opt into ISA or joint.
        // Important: ISAs cannot be jointly owned under UK law — never combine isa() with joint().
        $accountType = fake()->randomElement(['current_account', 'savings_account', 'fixed_term_deposit']);

        return [
            'user_id' => User::factory(),
            'account_name' => fake()->randomElement(['Main', 'Emergency Fund', 'Savings', 'Holiday Fund']).' Account',
            'institution_name' => fake()->randomElement(['Barclays', 'HSBC', 'Lloyds', 'NatWest', 'Santander']),
            'account_number' => fake()->numerify('########'),
            'sort_code' => fake()->numerify('##-##-##'),
            'account_type' => $accountType,
            'purpose' => fake()->optional()->randomElement(['emergency_fund', 'savings_goal', 'operating_cash', 'other']),
            'ownership_type' => 'individual',
            'ownership_percentage' => 100.00,
            'current_balance' => fake()->randomFloat(2, 500, 50000),
            'interest_rate' => fake()->randomFloat(4, 0.5, 5.0),
            'is_isa' => false,
            'isa_subscription_current_year' => 0,
            'tax_year' => '2025',
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function joint(?User $partner = null): static
    {
        return $this->state(fn (array $attrs) => [
            'ownership_type' => 'joint',
            'joint_owner_id' => $partner?->id ?? User::factory(),
            'ownership_percentage' => 50.00,
            // Re-affirm non-ISA — joint ISAs are illegal under UK law.
            'is_isa' => false,
            'account_type' => $attrs['account_type'] === 'cash_isa' ? 'savings_account' : ($attrs['account_type'] ?? 'savings_account'),
        ]);
    }

    public function isa(): static
    {
        return $this->state(fn () => [
            'account_type' => 'cash_isa',
            'is_isa' => true,
            'isa_subscription_current_year' => fake()->randomFloat(2, 1000, 20000),
            // Explicitly keep individual ownership — never join an ISA.
            'ownership_type' => 'individual',
            'joint_owner_id' => null,
            'ownership_percentage' => 100.00,
        ]);
    }
}
