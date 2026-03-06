<?php

declare(strict_types=1);

namespace Database\Factories\Investment;

use App\Models\Investment\Holding;
use App\Models\Investment\InvestmentAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Holding>
 */
class HoldingFactory extends Factory
{
    protected $model = Holding::class;

    public function definition(): array
    {
        $quantity = fake()->randomFloat(6, 10, 500);
        $purchasePrice = fake()->randomFloat(4, 50, 500);
        $currentPrice = $purchasePrice * fake()->randomFloat(2, 0.8, 1.5);
        $costBasis = $quantity * $purchasePrice;
        $currentValue = $quantity * $currentPrice;

        return [
            // Polymorphic relationship - default to InvestmentAccount
            'holdable_id' => InvestmentAccount::factory(),
            'holdable_type' => InvestmentAccount::class,
            'asset_type' => fake()->randomElement(['equity', 'bond', 'fund', 'etf', 'alternative']),
            'security_name' => fake()->randomElement([
                'Vanguard S&P 500 ETF',
                'iShares Core FTSE 100 ETF',
                'Vanguard Global Bond Index Fund',
                'HSBC FTSE All-World Index Fund',
                'Legal & General UK Index Trust',
                'BlackRock Gold & General Fund',
            ]),
            'ticker' => strtoupper(fake()->bothify('???')),
            'isin' => strtoupper(fake()->bothify('GB##########')),
            'quantity' => $quantity,
            'purchase_price' => $purchasePrice,
            'purchase_date' => fake()->dateTimeBetween('-5 years', '-1 month'),
            'current_price' => $currentPrice,
            'current_value' => $currentValue,
            'cost_basis' => $costBasis,
            'dividend_yield' => fake()->randomFloat(4, 0, 5),
            'ocf_percent' => fake()->randomFloat(4, 0.05, 1.5),
        ];
    }

    /**
     * Assign holding to a specific investment account
     */
    public function forAccount(InvestmentAccount $account): static
    {
        return $this->state(fn (array $attributes) => [
            'holdable_id' => $account->id,
            'holdable_type' => InvestmentAccount::class,
        ]);
    }

    public function equity(): static
    {
        return $this->state(fn (array $attributes) => [
            'asset_type' => 'equity',
            'dividend_yield' => fake()->randomFloat(4, 1, 4),
        ]);
    }

    public function bond(): static
    {
        return $this->state(fn (array $attributes) => [
            'asset_type' => 'bond',
            'dividend_yield' => fake()->randomFloat(4, 3, 6),
        ]);
    }
}
