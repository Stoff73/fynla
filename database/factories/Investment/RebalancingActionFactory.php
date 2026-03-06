<?php

declare(strict_types=1);

namespace Database\Factories\Investment;

use App\Models\Investment\Holding;
use App\Models\Investment\InvestmentAccount;
use App\Models\Investment\RebalancingAction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RebalancingAction>
 */
class RebalancingActionFactory extends Factory
{
    protected $model = RebalancingAction::class;

    public function definition(): array
    {
        $actionType = fake()->randomElement(['buy', 'sell', 'hold']);
        $currentPrice = fake()->randomFloat(4, 10, 500);
        $sharesToTrade = $actionType !== 'hold' ? fake()->randomFloat(6, 1, 100) : 0;
        $tradeValue = round($sharesToTrade * $currentPrice, 2);
        $currentHolding = fake()->randomFloat(2, 5000, 100000);
        $targetValue = $actionType === 'buy'
            ? $currentHolding + $tradeValue
            : ($actionType === 'sell' ? $currentHolding - $tradeValue : $currentHolding);

        return [
            'user_id' => User::factory(),
            'holding_id' => Holding::factory(),
            'investment_account_id' => InvestmentAccount::factory(),
            'action_type' => $actionType,
            'security_name' => fake()->randomElement([
                'Vanguard FTSE Global All Cap Index Fund',
                'iShares Core FTSE 100 ETF',
                'Vanguard LifeStrategy 60% Equity Fund',
                'Legal & General UK Index Trust',
                'HSBC FTSE All-World Index Fund',
                'Royal London Short Term Money Market Fund',
            ]),
            'ticker' => strtoupper(fake()->bothify('???')),
            'isin' => strtoupper(fake()->bothify('GB##########')),
            'shares_to_trade' => $sharesToTrade,
            'trade_value' => $tradeValue,
            'current_price' => $currentPrice,
            'current_holding' => $currentHolding,
            'target_value' => $targetValue,
            'target_weight' => fake()->randomFloat(4, 1, 50),
            'priority' => fake()->numberBetween(1, 10),
            'rationale' => fake()->randomElement([
                'Rebalance to target allocation',
                'Overweight - reduce to target weight',
                'Underweight - increase to target weight',
                'Tax-loss harvesting opportunity',
                'Consolidate holdings',
            ]),
            'cgt_cost_basis' => fake()->randomFloat(2, 5000, 80000),
            'cgt_gain_or_loss' => fake()->randomFloat(2, -5000, 20000),
            'cgt_liability' => max(0, fake()->randomFloat(2, 0, 4000)),
            'status' => 'pending',
            'executed_at' => null,
            'executed_price' => null,
            'executed_shares' => null,
            'notes' => fake()->optional(0.3)->sentence(),
        ];
    }

    /**
     * A buy action.
     */
    public function buy(): static
    {
        return $this->state(fn (array $attributes) => [
            'action_type' => 'buy',
        ]);
    }

    /**
     * A sell action.
     */
    public function sell(): static
    {
        return $this->state(fn (array $attributes) => [
            'action_type' => 'sell',
        ]);
    }

    /**
     * A hold action (no trade needed).
     */
    public function hold(): static
    {
        return $this->state(fn (array $attributes) => [
            'action_type' => 'hold',
            'shares_to_trade' => 0,
            'trade_value' => 0,
            'cgt_gain_or_loss' => 0,
            'cgt_liability' => 0,
        ]);
    }

    /**
     * An executed action.
     */
    public function executed(): static
    {
        return $this->state(function (array $attributes) {
            $price = $attributes['current_price'] ?? fake()->randomFloat(4, 10, 500);
            $shares = $attributes['shares_to_trade'] ?? fake()->randomFloat(6, 1, 100);

            return [
                'status' => 'executed',
                'executed_at' => fake()->dateTimeBetween('-1 month', 'now'),
                'executed_price' => $price * fake()->randomFloat(4, 0.98, 1.02),
                'executed_shares' => $shares,
            ];
        });
    }

    /**
     * A skipped action.
     */
    public function skipped(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'skipped',
            'notes' => 'Skipped by user',
        ]);
    }

    /**
     * A high-priority action.
     */
    public function highPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => fake()->numberBetween(1, 3),
        ]);
    }
}
