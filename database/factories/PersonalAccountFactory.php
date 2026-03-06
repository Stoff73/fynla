<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PersonalAccount>
 */
class PersonalAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_type' => fake()->randomElement(['profit_and_loss', 'cashflow', 'balance_sheet']),
            'period_start' => now()->startOfYear(),
            'period_end' => now()->endOfYear(),
            'line_item' => fake()->words(3, true),
            'category' => fake()->randomElement(['income', 'expense', 'asset', 'liability', 'equity', 'cash_inflow', 'cash_outflow']),
            'amount' => fake()->randomFloat(2, 100, 50000),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
