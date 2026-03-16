<?php

declare(strict_types=1);

namespace Database\Factories\Estate;

use App\Models\Estate\LastingPowerOfAttorney;
use App\Models\Estate\LpaAttorney;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LpaAttorney>
 */
class LpaAttorneyFactory extends Factory
{
    protected $model = LpaAttorney::class;

    public function definition(): array
    {
        return [
            'lasting_power_of_attorney_id' => LastingPowerOfAttorney::factory(),
            'attorney_type' => 'primary',
            'full_name' => fake()->name(),
            'date_of_birth' => fake()->dateTimeBetween('-70 years', '-18 years'),
            'address_line_1' => fake()->streetAddress(),
            'address_city' => fake()->city(),
            'address_county' => fake()->city(),
            'address_postcode' => fake()->postcode(),
            'relationship_to_donor' => fake()->randomElement(['Spouse', 'Son', 'Daughter', 'Sibling', 'Friend', 'Solicitor']),
            'sort_order' => 0,
        ];
    }

    /**
     * Replacement attorney.
     */
    public function replacement(): static
    {
        return $this->state(fn (array $attributes) => [
            'attorney_type' => 'replacement',
        ]);
    }
}
