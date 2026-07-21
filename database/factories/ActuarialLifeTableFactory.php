<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ActuarialLifeTable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActuarialLifeTable>
 */
class ActuarialLifeTableFactory extends Factory
{
    protected $model = ActuarialLifeTable::class;

    public function definition(): array
    {
        $age = $this->faker->numberBetween(0, 100);

        return [
            'age' => $age,
            'gender' => $this->faker->randomElement(['male', 'female']),
            'life_expectancy_years' => max(1.0, round(85.0 - $age + $this->faker->randomFloat(2, -2, 2), 2)),
            'probability_of_death' => round($this->faker->randomFloat(5, 0.0001, 0.3), 5),
            'table_year' => '2020-2022',
            'table_source' => 'UK ONS National Life Tables',
        ];
    }
}
