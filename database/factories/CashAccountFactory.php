<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CashAccount>
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
        $accountType = fake()->randomElement(['current_account', 'savings_account', 'cash_isa', 'fixed_term_deposit']);
        $isISA = $accountType === 'cash_isa';
        $ownershipType = fake()->randomElement(['individual', 'joint']);

        return [
            'account_name' => fake()->randomElement(['Main', 'Emergency Fund', 'Savings', 'Holiday Fund']).' Account',
            'institution_name' => fake()->randomElement(['Barclays', 'HSBC', 'Lloyds', 'NatWest', 'Santander']),
            'account_number' => fake()->numerify('########'),
            'sort_code' => fake()->numerify('##-##-##'),
            'account_type' => $accountType,
            'purpose' => fake()->optional()->randomElement(['emergency_fund', 'savings_goal', 'operating_cash', 'other']),
            'ownership_type' => $ownershipType,
            'ownership_percentage' => $ownershipType === 'joint' ? fake()->randomElement([50.00, 100.00]) : 100.00,
            'current_balance' => fake()->randomFloat(2, 500, 50000),
            'interest_rate' => fake()->randomFloat(4, 0.5, 5.0),
            'is_isa' => $isISA,
            'isa_subscription_current_year' => $isISA ? fake()->randomFloat(2, 1000, 20000) : 0,
            'tax_year' => '2025',
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
