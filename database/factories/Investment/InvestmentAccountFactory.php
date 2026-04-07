<?php

declare(strict_types=1);

namespace Database\Factories\Investment;

use App\Models\Investment\InvestmentAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvestmentAccount>
 */
class InvestmentAccountFactory extends Factory
{
    protected $model = InvestmentAccount::class;

    public function definition(): array
    {
        $ownershipType = fake()->randomElement(['individual', 'joint']);
        $accountType = fake()->randomElement(['isa', 'gia', 'onshore_bond', 'offshore_bond', 'vct', 'eis']);

        return [
            'user_id' => User::factory(),
            'account_name' => match ($accountType) {
                'isa' => 'Stocks & Shares ISA',
                'gia' => 'General Investment Account',
                'onshore_bond' => 'Onshore Investment Bond',
                'offshore_bond' => 'Offshore Investment Bond',
                'vct' => 'Venture Capital Trust',
                'eis' => 'Enterprise Investment Scheme',
                default => fake()->words(3, true),
            },
            'account_type' => $accountType,
            'country' => 'UK',
            'provider' => fake()->randomElement(['Vanguard', 'Hargreaves Lansdown', 'Interactive Investor', 'AJ Bell', 'Fidelity']),
            'account_number' => strtoupper(fake()->bothify('???######')),
            'platform' => fake()->randomElement(['Vanguard Investor', 'HL Platform', 'ii Platform', 'AJ Bell Youinvest']),
            'current_value' => fake()->randomFloat(2, 10000, 200000),
            'contributions_ytd' => fake()->randomFloat(2, 0, 20000),
            'monthly_contribution_amount' => fake()->optional(0.6)->randomFloat(2, 100, 1000),
            'contribution_frequency' => fake()->randomElement(['monthly', 'quarterly', 'annually']),
            'tax_year' => fake()->randomElement(['2024/25', '2025/26']),
            'platform_fee_percent' => fake()->randomFloat(4, 0.10, 0.45),
            'ownership_type' => $ownershipType,
            'ownership_percentage' => $ownershipType === 'joint' ? 50.00 : 100.00,
        ];
    }

    /**
     * An ISA account.
     */
    public function isa(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_type' => 'isa',
            'account_name' => 'Stocks & Shares ISA',
            'isa_type' => 'stocks_shares',
            'isa_subscription_current_year' => fake()->randomFloat(2, 0, 20000),
        ]);
    }

    /**
     * A General Investment Account.
     */
    public function gia(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_type' => 'gia',
            'account_name' => 'General Investment Account',
        ]);
    }

    /**
     * A jointly owned account.
     */
    public function joint(): static
    {
        return $this->state(fn (array $attributes) => [
            'ownership_type' => 'joint',
            'joint_owner_id' => User::factory(),
            'ownership_percentage' => 50.00,
        ]);
    }

    /**
     * An onshore bond.
     */
    public function onshoreBond(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_type' => 'onshore_bond',
            'account_name' => 'Onshore Investment Bond',
            'bond_purchase_date' => fake()->dateTimeBetween('-10 years', '-1 year'),
        ]);
    }

    /**
     * An offshore bond.
     */
    public function offshoreBond(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_type' => 'offshore_bond',
            'account_name' => 'Offshore Investment Bond',
            'bond_purchase_date' => fake()->dateTimeBetween('-10 years', '-1 year'),
        ]);
    }

    /**
     * An EIS investment.
     */
    public function eis(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_type' => 'eis',
            'account_name' => 'Enterprise Investment Scheme',
            'tax_relief_type' => 'eis',
            'investment_date' => fake()->dateTimeBetween('-3 years', '-6 months'),
            'investment_amount' => fake()->randomFloat(2, 5000, 100000),
        ]);
    }

    /**
     * A VCT investment.
     */
    public function vct(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_type' => 'vct',
            'account_name' => 'Venture Capital Trust',
            'tax_relief_type' => 'vct',
        ]);
    }

    /**
     * An employee share scheme (CSOP).
     */
    public function csop(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_type' => 'csop',
            'account_name' => 'Company Share Option Plan',
            'employer_name' => fake()->company(),
            'grant_date' => fake()->dateTimeBetween('-5 years', '-1 year'),
            'units_granted' => fake()->numberBetween(1000, 50000),
            'exercise_price' => fake()->randomFloat(4, 0.50, 20.00),
            'units_vested' => fake()->numberBetween(500, 25000),
            'units_unvested' => fake()->numberBetween(0, 25000),
            'scheme_status' => 'active',
        ]);
    }

    /**
     * Include in retirement planning.
     */
    public function includedInRetirement(): static
    {
        return $this->state(fn (array $attributes) => [
            'include_in_retirement' => true,
        ]);
    }
}
