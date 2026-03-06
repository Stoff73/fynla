<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StatePension>
 */
class StatePensionFactory extends Factory
{
    protected $model = \App\Models\StatePension::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $niYearsRequired = 35;
        $niYearsCompleted = fake()->numberBetween(20, $niYearsRequired);
        $fullStatePension = 11502.40; // 2024/25 full State Pension annual amount

        // Calculate forecast based on NI years (proportional)
        $statePensionForecast = ($niYearsCompleted / $niYearsRequired) * $fullStatePension;

        // Generate NI gaps as JSON array
        $gapsCount = fake()->numberBetween(0, min(5, $niYearsRequired - $niYearsCompleted));
        $niGaps = [];
        for ($i = 0; $i < $gapsCount; $i++) {
            $niGaps[] = [
                'tax_year' => '20'.fake()->numberBetween(10, 23).'-'.(fake()->numberBetween(11, 24)),
                'cost_to_fill' => fake()->randomFloat(2, 500, 800),
            ];
        }

        return [
            'user_id' => \App\Models\User::factory(),
            'ni_years_completed' => $niYearsCompleted,
            'ni_years_required' => $niYearsRequired,
            'state_pension_forecast_annual' => round($statePensionForecast, 2),
            'state_pension_age' => fake()->randomElement([66, 67, 68]),
            'ni_gaps' => ! empty($niGaps) ? $niGaps : null,
            'gap_fill_cost' => ! empty($niGaps) ? array_sum(array_column($niGaps, 'cost_to_fill')) : null,
        ];
    }
}
