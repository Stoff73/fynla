<?php

declare(strict_types=1);

namespace Database\Factories\Estate;

use App\Models\Estate\Asset;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    protected $model = Asset::class;

    public function definition(): array
    {
        $assetType = fake()->randomElement([
            'property',
            'investment',
            'pension',
            'cash',
            'business_interest',
            'personal_possession',
            'life_insurance',
            'other',
        ]);

        $isMainResidence = $assetType === 'property' && fake()->boolean(40);

        return [
            'user_id' => User::factory(),
            'asset_type' => $assetType,
            'asset_name' => $this->generateAssetName($assetType),
            'current_value' => fake()->randomFloat(2, 5000, 500000),
            'liquidity' => fake()->randomElement(['liquid', 'semi_liquid', 'illiquid']),
            'is_giftable' => $assetType !== 'pension',
            'not_giftable_reason' => $assetType === 'pension' ? 'Pension funds cannot be gifted during lifetime' : null,
            'is_main_residence' => $isMainResidence,
            'ownership_type' => fake()->randomElement(['individual', 'joint', 'tenants_in_common']),
            'beneficiary_designation' => fake()->optional(0.3)->name(),
            'is_iht_exempt' => false,
            'exemption_reason' => null,
            'valuation_date' => fake()->dateTimeBetween('-6 months', 'now'),
        ];
    }

    /**
     * Generate a realistic asset name based on type.
     */
    private function generateAssetName(string $assetType): string
    {
        return match ($assetType) {
            'property' => fake()->randomElement([
                'Family Home',
                'Buy-to-Let Property',
                'Holiday Cottage',
                'London Flat',
            ]),
            'investment' => fake()->randomElement([
                'Stocks & Shares ISA',
                'General Investment Account',
                'Vanguard Portfolio',
                'Hargreaves Lansdown ISA',
            ]),
            'pension' => fake()->randomElement([
                'Workplace Pension',
                'Self-Invested Personal Pension',
                'NHS Pension',
                'Teachers\' Pension',
            ]),
            'cash' => fake()->randomElement([
                'Current Account',
                'Cash ISA',
                'Premium Bonds',
                'Savings Account',
            ]),
            'business_interest' => fake()->company().' Shares',
            'personal_possession' => fake()->randomElement([
                'Jewellery Collection',
                'Art Collection',
                'Classic Car',
                'Antique Furniture',
            ]),
            'life_insurance' => fake()->randomElement([
                'Term Life Policy',
                'Whole of Life Policy',
                'Death in Service Benefit',
            ]),
            default => fake()->words(3, true),
        };
    }

    /**
     * Indicate that the asset is the main residence.
     */
    public function mainResidence(): static
    {
        return $this->state(fn (array $attributes) => [
            'asset_type' => 'property',
            'asset_name' => 'Family Home',
            'is_main_residence' => true,
            'current_value' => fake()->randomFloat(2, 200000, 800000),
            'liquidity' => 'illiquid',
        ]);
    }

    /**
     * Indicate that the asset is IHT exempt.
     */
    public function ihtExempt(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_iht_exempt' => true,
            'exemption_reason' => fake()->randomElement([
                'Business Property Relief',
                'Agricultural Property Relief',
                'Spouse/civil partner exemption',
                'Charity exemption',
            ]),
        ]);
    }

    /**
     * Indicate that the asset is jointly owned.
     */
    public function joint(): static
    {
        return $this->state(fn (array $attributes) => [
            'ownership_type' => 'joint',
        ]);
    }

    /**
     * Indicate that the asset is a cash holding.
     */
    public function cash(): static
    {
        return $this->state(fn (array $attributes) => [
            'asset_type' => 'cash',
            'asset_name' => 'Savings Account',
            'current_value' => fake()->randomFloat(2, 1000, 100000),
            'liquidity' => 'liquid',
            'is_giftable' => true,
        ]);
    }

    /**
     * Indicate that the asset is an investment.
     */
    public function investment(): static
    {
        return $this->state(fn (array $attributes) => [
            'asset_type' => 'investment',
            'asset_name' => 'Stocks & Shares ISA',
            'current_value' => fake()->randomFloat(2, 10000, 300000),
            'liquidity' => 'semi_liquid',
            'is_giftable' => true,
        ]);
    }
}
