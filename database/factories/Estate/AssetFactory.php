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
        // W-0481 — these must be the values the COLUMN accepts, which are
        // `property`, `pension`, `investment`, `business`, `other`.
        //
        // The list used to carry eight, four of which the enum rejects outright
        // — `cash`, `business_interest`, `personal_possession`, `life_insurance`
        // — while `business`, which it does accept, was never generated. So a
        // factory call without an explicit `asset_type` failed roughly half the
        // time, at random, and the failure looked like a flaky test rather than a
        // factory that could not produce a valid row.
        //
        // Kept as a literal list rather than read from the schema: a factory that
        // derives its values from the column can never disagree with it, and so
        // can never fail when the column changes — which is the warning a test
        // suite is supposed to give.
        $assetType = fake()->randomElement([
            'property',
            'pension',
            'investment',
            'business',
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
            // W-0481, second instance of the same fault in this file. The column
            // is enum('individual','joint','trust'); `tenants_in_common` is not
            // in it and never could be — Rule 4 makes that value PROPERTY-only,
            // and this is the estate `assets` table. `trust` was missing instead.
            'ownership_type' => fake()->randomElement(['individual', 'joint', 'trust']),
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
            // W-0481 — `business`, the value the column actually accepts. The
            // arm here used to be `business_interest`, which it does not, so it
            // was unreachable AND invalid at the same time.
            'business' => fake()->company().' Shares',
            // The `cash`, `personal_possession` and `life_insurance` arms were
            // removed with the types themselves. Cash lives in the savings
            // tables and policies in the protection ones; neither is an `assets`
            // row, which is why the enum never had them.
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
