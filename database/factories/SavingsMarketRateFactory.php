<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SavingsMarketRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavingsMarketRate>
 */
class SavingsMarketRateFactory extends Factory
{
    protected $model = SavingsMarketRate::class;

    public function definition(): array
    {
        $base = $this->faker->randomElement([
            'easy_access', 'notice', 'fixed_1_year', 'fixed_2_year', 'fixed_3_year',
        ]);

        return [
            'rate_key' => $base.'_'.$this->faker->unique()->randomNumber(5),
            'label' => $this->faker->randomElement([
                'Easy Access', 'Notice', '1 Year Fixed', '2 Year Fixed', '3 Year Fixed',
            ]),
            'rate' => round($this->faker->randomFloat(4, 0.01, 0.07), 4),
            'tax_year' => '2026/27',
            'effective_from' => '2026-04-06',
        ];
    }
}
