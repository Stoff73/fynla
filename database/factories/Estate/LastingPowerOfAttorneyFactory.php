<?php

declare(strict_types=1);

namespace Database\Factories\Estate;

use App\Models\Estate\LastingPowerOfAttorney;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LastingPowerOfAttorney>
 */
class LastingPowerOfAttorneyFactory extends Factory
{
    protected $model = LastingPowerOfAttorney::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'lpa_type' => fake()->randomElement(['property_financial', 'health_welfare']),
            'status' => 'draft',
            'source' => 'created',
            'donor_full_name' => fake()->name(),
            'donor_date_of_birth' => fake()->dateTimeBetween('-80 years', '-18 years'),
            'donor_address_line_1' => fake()->streetAddress(),
            'donor_address_city' => fake()->city(),
            'donor_address_county' => fake()->city(),
            'donor_address_postcode' => fake()->postcode(),
            'attorney_decision_type' => 'jointly_and_severally',
            'preferences' => fake()->optional(0.5)->sentence(),
            'instructions' => fake()->optional(0.3)->sentence(),
            'certificate_provider_name' => fake()->name(),
            'certificate_provider_relationship' => fake()->randomElement(['Family friend', 'Solicitor', 'Doctor', 'Neighbour']),
            'certificate_provider_known_years' => fake()->numberBetween(2, 20),
        ];
    }

    /**
     * Health & Welfare type LPA.
     */
    public function healthWelfare(): static
    {
        return $this->state(fn (array $attributes) => [
            'lpa_type' => 'health_welfare',
            'when_attorneys_can_act' => null,
            'life_sustaining_treatment' => fake()->randomElement(['can_consent', 'cannot_consent']),
        ]);
    }

    /**
     * Property & Financial Affairs type LPA.
     */
    public function propertyFinancial(): static
    {
        return $this->state(fn (array $attributes) => [
            'lpa_type' => 'property_financial',
            'when_attorneys_can_act' => fake()->randomElement(['while_has_capacity', 'only_when_lost_capacity']),
            'life_sustaining_treatment' => null,
        ]);
    }

    /**
     * Draft status.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
            'completed_at' => null,
            'is_registered_with_opg' => false,
        ]);
    }

    /**
     * Registered with the Office of the Public Guardian.
     */
    public function registered(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'registered',
            'completed_at' => fake()->dateTimeBetween('-2 years', '-1 month'),
            'is_registered_with_opg' => true,
            'registration_date' => fake()->dateTimeBetween('-2 years', '-1 month'),
            'opg_reference' => 'OPG-'.fake()->numerify('#######'),
        ]);
    }

    /**
     * Uploaded LPA (not created via wizard).
     */
    public function uploaded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'uploaded',
            'source' => 'uploaded',
        ]);
    }

    /**
     * With multiple primary attorneys (jointly and severally).
     */
    public function withMultipleAttorneys(): static
    {
        return $this->state(fn (array $attributes) => [
            'attorney_decision_type' => 'jointly_and_severally',
        ]);
    }
}
