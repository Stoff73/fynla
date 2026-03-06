<?php

declare(strict_types=1);

namespace Database\Factories\Estate;

use App\Models\Estate\IHTCalculation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IHTCalculation>
 */
class IHTCalculationFactory extends Factory
{
    protected $model = IHTCalculation::class;

    public function definition(): array
    {
        $isMarried = fake()->boolean(60);
        $userGrossAssets = fake()->randomFloat(2, 200000, 2000000);
        $spouseGrossAssets = $isMarried ? fake()->randomFloat(2, 100000, 1500000) : 0;
        $totalGrossAssets = $userGrossAssets + $spouseGrossAssets;

        $userLiabilities = fake()->randomFloat(2, 0, $userGrossAssets * 0.3);
        $spouseLiabilities = $isMarried ? fake()->randomFloat(2, 0, $spouseGrossAssets * 0.3) : 0;
        $totalLiabilities = $userLiabilities + $spouseLiabilities;

        $userNetEstate = $userGrossAssets - $userLiabilities;
        $spouseNetEstate = $spouseGrossAssets - $spouseLiabilities;
        $totalNetEstate = $userNetEstate + $spouseNetEstate;

        $nrbAvailable = $isMarried ? 650000 : 325000;
        $rnrbAvailable = fake()->randomElement([0, 175000, 350000]);
        $totalAllowances = $nrbAvailable + $rnrbAvailable;

        $taxableEstate = max(0, $totalNetEstate - $totalAllowances);
        $ihtLiability = $taxableEstate * 0.40;
        $effectiveRate = $totalNetEstate > 0 ? ($ihtLiability / $totalNetEstate) * 100 : 0;

        return [
            'user_id' => User::factory(),
            'user_gross_assets' => $userGrossAssets,
            'spouse_gross_assets' => $spouseGrossAssets,
            'total_gross_assets' => $totalGrossAssets,
            'user_total_liabilities' => $userLiabilities,
            'spouse_total_liabilities' => $spouseLiabilities,
            'total_liabilities' => $totalLiabilities,
            'user_net_estate' => $userNetEstate,
            'spouse_net_estate' => $spouseNetEstate,
            'total_net_estate' => $totalNetEstate,
            'nrb_available' => $nrbAvailable,
            'nrb_message' => $isMarried ? 'Full nil-rate band available plus transferred spouse allowance' : 'Full nil-rate band available',
            'rnrb_available' => $rnrbAvailable,
            'rnrb_status' => $rnrbAvailable > 0 ? 'available' : 'not_applicable',
            'rnrb_message' => $rnrbAvailable > 0 ? 'Residence nil-rate band available' : 'No qualifying residential property',
            'total_allowances' => $totalAllowances,
            'taxable_estate' => $taxableEstate,
            'iht_liability' => $ihtLiability,
            'effective_rate' => round($effectiveRate, 2),
            'projected_gross_assets' => $totalGrossAssets * fake()->randomFloat(2, 1.0, 1.5),
            'projected_liabilities' => $totalLiabilities * fake()->randomFloat(2, 0.3, 1.0),
            'projected_net_estate' => null,
            'projected_taxable_estate' => null,
            'projected_iht_liability' => null,
            'projected_cash' => fake()->randomFloat(2, 10000, 200000),
            'projected_investments' => fake()->randomFloat(2, 50000, 500000),
            'projected_properties' => fake()->randomFloat(2, 200000, 800000),
            'retirement_age' => fake()->numberBetween(60, 70),
            'result_json' => null,
            'years_to_death' => fake()->numberBetween(10, 40),
            'estimated_age_at_death' => fake()->numberBetween(75, 95),
            'calculation_date' => now(),
            'is_married' => $isMarried,
            'data_sharing_enabled' => $isMarried ? fake()->boolean(80) : false,
            'assets_hash' => md5((string) $userGrossAssets),
            'liabilities_hash' => md5((string) $userLiabilities),
        ];
    }

    /**
     * A married individual's IHT calculation.
     */
    public function married(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_married' => true,
            'nrb_available' => 650000,
            'nrb_message' => 'Full nil-rate band available plus transferred spouse allowance',
            'data_sharing_enabled' => true,
        ]);
    }

    /**
     * A single individual's IHT calculation.
     */
    public function single(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_married' => false,
            'spouse_gross_assets' => 0,
            'spouse_total_liabilities' => 0,
            'spouse_net_estate' => 0,
            'nrb_available' => 325000,
            'nrb_message' => 'Full nil-rate band available',
            'data_sharing_enabled' => false,
        ]);
    }

    /**
     * A calculation with no IHT liability (estate below thresholds).
     */
    public function belowThreshold(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_gross_assets' => 250000,
            'spouse_gross_assets' => 0,
            'total_gross_assets' => 250000,
            'user_total_liabilities' => 0,
            'spouse_total_liabilities' => 0,
            'total_liabilities' => 0,
            'user_net_estate' => 250000,
            'spouse_net_estate' => 0,
            'total_net_estate' => 250000,
            'taxable_estate' => 0,
            'iht_liability' => 0,
            'effective_rate' => 0,
            'is_married' => false,
        ]);
    }

    /**
     * A high-value estate with significant IHT liability.
     */
    public function highValue(): static
    {
        $netEstate = fake()->randomFloat(2, 1500000, 5000000);
        $taxable = $netEstate - 500000;
        $iht = $taxable * 0.40;

        return $this->state(fn (array $attributes) => [
            'user_gross_assets' => $netEstate,
            'total_gross_assets' => $netEstate,
            'user_total_liabilities' => 0,
            'total_liabilities' => 0,
            'user_net_estate' => $netEstate,
            'total_net_estate' => $netEstate,
            'taxable_estate' => $taxable,
            'iht_liability' => $iht,
            'effective_rate' => round(($iht / $netEstate) * 100, 2),
        ]);
    }
}
