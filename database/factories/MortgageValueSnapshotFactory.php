<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Mortgage;
use App\Models\MortgageValueSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MortgageValueSnapshot>
 */
class MortgageValueSnapshotFactory extends Factory
{
    protected $model = MortgageValueSnapshot::class;

    public function definition(): array
    {
        return [
            'mortgage_id' => Mortgage::factory(),
            'snapshot_type' => fake()->randomElement(['mortgageBalance', 'mortgageRate']),
            'value' => fake()->randomFloat(4, 50000, 500000),
            'snapshotted_at' => now(),
        ];
    }

    public function balance(): static
    {
        return $this->state(fn (array $attrs) => [
            'snapshot_type' => 'mortgageBalance',
            'value' => fake()->randomFloat(4, 50000, 500000),
        ]);
    }

    public function rate(): static
    {
        return $this->state(fn (array $attrs) => [
            'snapshot_type' => 'mortgageRate',
            'value' => fake()->randomFloat(4, 1.0, 8.0),
        ]);
    }
}
