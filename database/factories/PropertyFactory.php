<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Property>
 */
class PropertyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $propertyType = fake()->randomElement(['main_residence', 'secondary_residence', 'buy_to_let']);
        $ownershipType = fake()->randomElement(['individual', 'joint']);
        $purchasePrice = fake()->numberBetween(150000, 800000);
        $currentValue = $purchasePrice * fake()->randomFloat(2, 1.0, 1.5);

        return [
            'property_type' => $propertyType,
            'ownership_type' => $ownershipType,
            'ownership_percentage' => $ownershipType === 'joint' ? fake()->randomElement([50.00, 100.00]) : 100.00,
            'address_line_1' => fake()->streetAddress(),
            'address_line_2' => fake()->optional()->secondaryAddress(),
            'city' => fake()->city(),
            'county' => fake()->randomElement(['Greater London', 'West Midlands', 'Greater Manchester', 'West Yorkshire', 'Merseyside', 'South Yorkshire', 'Tyne and Wear', 'Essex', 'Kent', 'Hampshire']),
            'postcode' => fake()->postcode(),
            'purchase_date' => fake()->dateTimeBetween('-15 years', '-1 year'),
            'purchase_price' => $purchasePrice,
            'current_value' => $currentValue,
            'valuation_date' => fake()->dateTimeBetween('-6 months', 'now'),
            'sdlt_paid' => $purchasePrice * 0.03, // Simplified SDLT calculation
            'monthly_rental_income' => $propertyType === 'buy_to_let' ? fake()->numberBetween(800, 2500) : null,
            'tenant_name' => $propertyType === 'buy_to_let' ? fake()->optional()->name() : null,
            'lease_start_date' => $propertyType === 'buy_to_let' ? fake()->dateTimeBetween('-2 years', 'now') : null,
            'lease_end_date' => $propertyType === 'buy_to_let' ? fake()->dateTimeBetween('now', '+2 years') : null,
            'annual_service_charge' => fake()->optional()->numberBetween(500, 3000),
            'annual_ground_rent' => fake()->optional()->numberBetween(100, 500),
            'annual_insurance' => fake()->numberBetween(200, 800),
            'annual_maintenance_reserve' => fake()->numberBetween(500, 2000),
            'other_annual_costs' => fake()->optional()->numberBetween(200, 1000),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
