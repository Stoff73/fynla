<?php

declare(strict_types=1);

namespace Database\Factories\Estate;

use App\Models\Estate\LastingPowerOfAttorney;
use App\Models\Estate\LpaNotificationPerson;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LpaNotificationPerson>
 */
class LpaNotificationPersonFactory extends Factory
{
    protected $model = LpaNotificationPerson::class;

    public function definition(): array
    {
        return [
            'lasting_power_of_attorney_id' => LastingPowerOfAttorney::factory(),
            'full_name' => fake()->name(),
            'address_line_1' => fake()->streetAddress(),
            'address_city' => fake()->city(),
            'address_county' => fake()->city(),
            'address_postcode' => fake()->postcode(),
            'sort_order' => 0,
        ];
    }
}
