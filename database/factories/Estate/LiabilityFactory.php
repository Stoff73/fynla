<?php

namespace Database\Factories\Estate;

use App\Models\Estate\Liability;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LiabilityFactory extends Factory
{
    protected $model = Liability::class;

    public function definition(): array
    {
        $liabilityTypes = [
            'mortgage',
            'secured_loan',
            'personal_loan',
            'credit_card',
            'overdraft',
            'hire_purchase',
            'student_loan',
            'business_loan',
            'other',
        ];

        return [
            'user_id' => User::factory(),
            'ownership_type' => 'individual',
            'joint_owner_id' => null,
            'trust_id' => null,
            'liability_type' => $this->faker->randomElement($liabilityTypes),
            'country' => 'UK',
            'liability_name' => $this->faker->words(3, true),
            'current_balance' => $this->faker->randomFloat(2, 1000, 100000),
            'monthly_payment' => $this->faker->randomFloat(2, 50, 1000),
            'interest_rate' => $this->faker->randomFloat(4, 0.0199, 0.2499),
            'maturity_date' => $this->faker->optional()->dateTimeBetween('+1 year', '+30 years'),
            'secured_against' => null,
            'is_priority_debt' => false,
            'mortgage_type' => null,
            'fixed_until' => null,
            'notes' => null,
        ];
    }

    /**
     * Indicate that the liability is a mortgage.
     */
    public function mortgage(): static
    {
        return $this->state(fn (array $attributes) => [
            'liability_type' => 'mortgage',
            'secured_against' => 'Property',
            'is_priority_debt' => true,
            'mortgage_type' => $this->faker->randomElement(['repayment', 'interest_only', 'mixed']),
            'current_balance' => $this->faker->randomFloat(2, 50000, 500000),
        ]);
    }

    /**
     * Indicate that the liability is a personal loan.
     */
    public function personalLoan(): static
    {
        return $this->state(fn (array $attributes) => [
            'liability_type' => 'personal_loan',
            'current_balance' => $this->faker->randomFloat(2, 1000, 25000),
        ]);
    }

    /**
     * Indicate that the liability is a credit card.
     */
    public function creditCard(): static
    {
        return $this->state(fn (array $attributes) => [
            'liability_type' => 'credit_card',
            'interest_rate' => $this->faker->randomFloat(4, 0.15, 0.30),
            'current_balance' => $this->faker->randomFloat(2, 100, 10000),
            'maturity_date' => null,
        ]);
    }

    /**
     * Indicate that the liability is a student loan.
     */
    public function studentLoan(): static
    {
        return $this->state(fn (array $attributes) => [
            'liability_type' => 'student_loan',
            'interest_rate' => $this->faker->randomFloat(4, 0.01, 0.07),
            'current_balance' => $this->faker->randomFloat(2, 10000, 50000),
        ]);
    }

    /**
     * Indicate that the liability is jointly owned.
     */
    public function joint(): static
    {
        return $this->state(fn (array $attributes) => [
            'ownership_type' => 'joint',
            'joint_owner_id' => User::factory(),
        ]);
    }

    /**
     * Indicate that the liability is held in trust.
     */
    public function inTrust(): static
    {
        return $this->state(fn (array $attributes) => [
            'ownership_type' => 'trust',
        ]);
    }
}
