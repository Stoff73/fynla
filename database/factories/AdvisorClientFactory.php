<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AdvisorClient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AdvisorClientFactory extends Factory
{
    protected $model = AdvisorClient::class;

    public function definition(): array
    {
        return [
            'advisor_id' => User::factory(),
            'client_id' => User::factory(),
            'status' => 'active',
            'assigned_date' => fake()->dateTimeBetween('-1 year', 'now'),
            'last_review_date' => fake()->dateTimeBetween('-6 months', 'now'),
            'next_review_due' => fake()->dateTimeBetween('now', '+6 months'),
            'review_frequency_months' => 12,
            'notes' => null,
        ];
    }

    public function overdue(): static
    {
        return $this->state(fn () => [
            'next_review_due' => fake()->dateTimeBetween('-3 months', '-1 day'),
        ]);
    }
}
