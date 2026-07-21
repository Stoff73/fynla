<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CurrencyRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CurrencyRate>
 */
class CurrencyRateFactory extends Factory
{
    protected $model = CurrencyRate::class;

    public function definition(): array
    {
        return [
            'from_ccy' => 'GBP',
            'to_ccy' => $this->faker->randomElement(['EUR', 'USD']),
            'rate' => $this->faker->randomFloat(8, 0.5, 2.0),
            'effective_at' => now(),
            'source' => 'manual',
        ];
    }
}
