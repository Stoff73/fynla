<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Mortgage>
 */
class MortgageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $mortgageType = fake()->randomElement(['repayment', 'interest_only', 'mixed']);
        $originalAmount = fake()->numberBetween(100000, 500000);
        $outstandingBalance = $originalAmount * fake()->randomFloat(2, 0.6, 0.95);
        $interestRate = fake()->randomFloat(4, 2.5, 6.5);
        $startDate = fake()->dateTimeBetween('-10 years', '-1 year');
        $termYears = fake()->numberBetween(15, 30);
        $maturityDate = (clone $startDate)->modify("+{$termYears} years");
        $now = new \DateTime;
        $remainingMonths = max(0, ($maturityDate->getTimestamp() - $now->getTimestamp()) / (30 * 24 * 60 * 60));

        return [
            'property_id' => \App\Models\Property::factory(),
            'lender_name' => fake()->company().' Bank',
            'mortgage_account_number' => fake()->optional()->numerify('MG########'),
            'mortgage_type' => $mortgageType,
            'original_loan_amount' => $originalAmount,
            'outstanding_balance' => $outstandingBalance,
            'interest_rate' => $interestRate,
            'rate_type' => fake()->randomElement(['fixed', 'variable', 'tracker', 'discount']),
            'rate_fix_end_date' => fake()->optional()->dateTimeBetween('now', '+5 years'),
            'monthly_payment' => $outstandingBalance * ($interestRate / 100 / 12),
            'start_date' => $startDate,
            'maturity_date' => $maturityDate,
            'remaining_term_months' => (int) $remainingMonths,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
