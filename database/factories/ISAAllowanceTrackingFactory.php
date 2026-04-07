<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ISAAllowanceTracking;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ISAAllowanceTracking>
 */
class ISAAllowanceTrackingFactory extends Factory
{
    protected $model = ISAAllowanceTracking::class;

    public function definition(): array
    {
        $cashIsaUsed = fake()->randomFloat(2, 0, 10000);
        $stocksSharesIsaUsed = fake()->randomFloat(2, 0, 10000);
        $lisaUsed = fake()->randomFloat(2, 0, 4000);
        $totalUsed = $cashIsaUsed + $stocksSharesIsaUsed + $lisaUsed;

        return [
            'user_id' => User::factory(),
            'tax_year' => fake()->randomElement(['2024/25', '2025/26']),
            'cash_isa_used' => $cashIsaUsed,
            'stocks_shares_isa_used' => $stocksSharesIsaUsed,
            'lisa_used' => $lisaUsed,
            'total_used' => $totalUsed,
            'total_allowance' => 20000.00,
        ];
    }

    /**
     * Allowance fully utilised.
     */
    public function fullyUsed(): static
    {
        return $this->state(fn (array $attributes) => [
            'cash_isa_used' => 8000.00,
            'stocks_shares_isa_used' => 8000.00,
            'lisa_used' => 4000.00,
            'total_used' => 20000.00,
            'total_allowance' => 20000.00,
        ]);
    }

    /**
     * No allowance used yet.
     */
    public function unused(): static
    {
        return $this->state(fn (array $attributes) => [
            'cash_isa_used' => 0,
            'stocks_shares_isa_used' => 0,
            'lisa_used' => 0,
            'total_used' => 0,
            'total_allowance' => 20000.00,
        ]);
    }

    /**
     * Only Cash ISA used.
     */
    public function cashIsaOnly(): static
    {
        $amount = fake()->randomFloat(2, 1000, 15000);

        return $this->state(fn (array $attributes) => [
            'cash_isa_used' => $amount,
            'stocks_shares_isa_used' => 0,
            'lisa_used' => 0,
            'total_used' => $amount,
        ]);
    }

    /**
     * Only Stocks & Shares ISA used.
     */
    public function stocksSharesOnly(): static
    {
        $amount = fake()->randomFloat(2, 1000, 15000);

        return $this->state(fn (array $attributes) => [
            'cash_isa_used' => 0,
            'stocks_shares_isa_used' => $amount,
            'lisa_used' => 0,
            'total_used' => $amount,
        ]);
    }

    /**
     * For the current tax year (2025/26).
     */
    public function currentYear(): static
    {
        return $this->state(fn (array $attributes) => [
            'tax_year' => '2025/26',
        ]);
    }

    /**
     * For the previous tax year (2024/25).
     */
    public function previousYear(): static
    {
        return $this->state(fn (array $attributes) => [
            'tax_year' => '2024/25',
        ]);
    }
}
