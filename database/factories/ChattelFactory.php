<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Chattel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Chattel>
 */
class ChattelFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $chattelType = fake()->randomElement(['vehicle', 'art', 'antique', 'jewelry', 'collectible', 'other']);

        return [
            'user_id' => User::factory(),
            'chattel_type' => $chattelType,
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'ownership_type' => 'individual',
            'ownership_percentage' => 100.00,
            'purchase_price' => fake()->optional()->randomFloat(2, 1000, 100000),
            'purchase_date' => fake()->optional()->dateTimeBetween('-10 years', '-1 year'),
            'current_value' => fake()->randomFloat(2, 1000, 100000),
            'valuation_date' => fake()->dateTimeBetween('-1 year', 'now'),
            'make' => $chattelType === 'vehicle' ? fake()->randomElement(['BMW', 'Mercedes', 'Audi', 'Tesla']) : null,
            'model' => $chattelType === 'vehicle' ? fake()->word() : null,
            'year' => $chattelType === 'vehicle' ? fake()->numberBetween(2015, 2024) : null,
            'registration_number' => $chattelType === 'vehicle' ? fake()->regexify('[A-Z]{2}[0-9]{2} [A-Z]{3}') : null,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function joint(?User $partner = null): static
    {
        return $this->state(fn (array $attrs) => [
            'ownership_type' => 'joint',
            'joint_owner_id' => $partner?->id ?? User::factory(),
            'ownership_percentage' => 50.00,
        ]);
    }
}
