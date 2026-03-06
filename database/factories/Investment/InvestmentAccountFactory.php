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

        return [
            'user_id' => User::factory(),
            'account_type' => fake()->randomElement(['isa', 'gia', 'onshore_bond', 'offshore_bond', 'vct', 'eis']),
            'provider' => fake()->randomElement(['Vanguard', 'Hargreaves Lansdown', 'Interactive Investor', 'AJ Bell', 'Fidelity']),
            'account_number' => strtoupper(fake()->bothify('???######')),
            'platform' => fake()->randomElement(['Vanguard Investor', 'HL Platform', 'ii Platform', 'AJ Bell Youinvest']),
            'current_value' => fake()->randomFloat(2, 10000, 200000),
            'contributions_ytd' => fake()->randomFloat(2, 0, 20000),
            'tax_year' => fake()->randomElement(['2024/25', '2025/26']),
            'platform_fee_percent' => fake()->randomFloat(4, 0.10, 0.45),
            'ownership_type' => $ownershipType,
            'ownership_percentage' => $ownershipType === 'joint' ? 50.00 : 100.00,
        ];
    }

    public function isa(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_type' => 'isa',
        ]);
    }

    public function gia(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_type' => 'gia',
        ]);
    }
}
