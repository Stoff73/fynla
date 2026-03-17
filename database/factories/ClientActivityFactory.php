<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AdvisorClient;
use App\Models\ClientActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClientActivityFactory extends Factory
{
    protected $model = ClientActivity::class;

    public function definition(): array
    {
        return [
            'advisor_client_id' => AdvisorClient::factory(),
            'advisor_id' => User::factory(),
            'client_id' => User::factory(),
            'activity_type' => fake()->randomElement(['email', 'phone', 'meeting', 'note']),
            'summary' => fake()->sentence(),
            'details' => null,
            'activity_date' => fake()->dateTimeBetween('-3 months', 'now'),
            'follow_up_date' => null,
            'follow_up_completed' => false,
        ];
    }

    public function suitabilityReport(): static
    {
        return $this->state(fn () => [
            'activity_type' => 'suitability_report',
            'report_type' => fake()->randomElement(['protection_review', 'annual_review', 'pension_transfer']),
            'report_sent_date' => fake()->dateTimeBetween('-1 month', 'now'),
        ]);
    }
}
