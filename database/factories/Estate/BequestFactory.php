<?php

declare(strict_types=1);

namespace Database\Factories\Estate;

use App\Models\Estate\Bequest;
use App\Models\Estate\Will;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bequest>
 */
class BequestFactory extends Factory
{
    protected $model = Bequest::class;

    public function definition(): array
    {
        $bequestType = $this->faker->randomElement(['percentage', 'specific_amount', 'specific_asset']);

        return [
            'will_id' => Will::factory(),
            'user_id' => User::factory(),
            'beneficiary_name' => $this->faker->name(),
            'beneficiary_user_id' => null,
            'beneficiary_type' => $this->faker->randomElement(['spouse', 'child', 'grandchild', 'sibling', 'charity', 'other']),
            'charity_registration_number' => null,
            'bequest_type' => $bequestType,
            'percentage_of_estate' => $bequestType === 'percentage' ? $this->faker->randomElement([10.00, 20.00, 25.00, 50.00]) : null,
            'specific_amount' => $bequestType === 'specific_amount' ? $this->faker->randomFloat(2, 1000, 100000) : null,
            'specific_asset_description' => $bequestType === 'specific_asset' ? $this->faker->randomElement([
                'Family home at ' . $this->faker->streetAddress(),
                'Jewellery collection',
                'Classic car',
                'Art collection',
            ]) : null,
            'asset_id' => null,
            'priority_order' => $this->faker->numberBetween(1, 10),
            'conditions' => $this->faker->optional(0.2)->sentence(),
            'notes' => $this->faker->optional(0.3)->sentence(),
        ];
    }

    /**
     * A percentage-based bequest.
     */
    public function percentage(float $percent = 25.00): static
    {
        return $this->state(fn (array $attributes) => [
            'bequest_type' => 'percentage',
            'percentage_of_estate' => $percent,
            'specific_amount' => null,
            'specific_asset_description' => null,
        ]);
    }

    /**
     * A specific amount bequest.
     */
    public function specificAmount(float $amount = 50000.00): static
    {
        return $this->state(fn (array $attributes) => [
            'bequest_type' => 'specific_amount',
            'specific_amount' => $amount,
            'percentage_of_estate' => null,
            'specific_asset_description' => null,
        ]);
    }

    /**
     * A charitable bequest.
     */
    public function charitable(): static
    {
        return $this->state(fn (array $attributes) => [
            'beneficiary_type' => 'charity',
            'beneficiary_name' => $this->faker->randomElement([
                'British Heart Foundation',
                'Macmillan Cancer Support',
                'RSPCA',
                'Oxfam',
                'Marie Curie',
                'Shelter',
            ]),
            'charity_registration_number' => (string) $this->faker->numberBetween(100000, 9999999),
        ]);
    }

    /**
     * A bequest to a spouse.
     */
    public function toSpouse(): static
    {
        return $this->state(fn (array $attributes) => [
            'beneficiary_type' => 'spouse',
            'priority_order' => 1,
        ]);
    }

    /**
     * A bequest to a child.
     */
    public function toChild(): static
    {
        return $this->state(fn (array $attributes) => [
            'beneficiary_type' => 'child',
        ]);
    }
}
