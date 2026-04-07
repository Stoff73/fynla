<?php

declare(strict_types=1);

namespace App\Services\Estate;

use App\Models\ActuarialLifeTable;
use App\Models\User;
use App\Services\TaxConfigService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class FutureValueCalculator
{
    public function __construct(
        private readonly TaxConfigService $taxConfig
    ) {}

    /**
     * Get life expectancy for user based on UK ONS actuarial tables
     *
     * @return array [years_remaining, death_age, death_year]
     */
    public function getLifeExpectancy(User $user): array
    {
        if (! $user->date_of_birth) {
            // Default to age 85 if no DOB
            return [
                'years_remaining' => 30,
                'death_age' => 85,
                'death_year' => now()->year + 30,
            ];
        }

        $currentAge = Carbon::parse($user->date_of_birth)->age;

        // User override takes precedence over actuarial lookup
        if ($user->life_expectancy_override !== null) {
            $yearsRemaining = max(1, $user->life_expectancy_override - $currentAge);

            return [
                'years_remaining' => (float) $yearsRemaining,
                'death_age' => $user->life_expectancy_override,
                'death_year' => now()->year + $yearsRemaining,
                'current_age' => $currentAge,
                'source' => 'user_override',
            ];
        }

        $gender = strtolower($user->gender ?? 'male');

        $lifeExpectancy = $this->lookupLifeExpectancy($currentAge, $gender);

        return [
            'years_remaining' => round($lifeExpectancy, 1),
            'death_age' => $currentAge + (int) round($lifeExpectancy),
            'death_year' => now()->year + (int) round($lifeExpectancy),
            'current_age' => $currentAge,
        ];
    }

    /**
     * Get life expectancy years remaining for a given age and gender.
     *
     * Public wrapper around lookupLifeExpectancy for use by other services
     * that need life expectancy data without a full User object.
     *
     * @param  int  $age  Current age
     * @param  string  $gender  'male' or 'female'
     * @return float Years remaining
     */
    public function getLifeExpectancyYears(int $age, string $gender): float
    {
        return $this->lookupLifeExpectancy($age, $gender);
    }

    /**
     * Lookup life expectancy from actuarial_life_tables database (UK ONS 2020-2022 data)
     *
     * @param  int  $age  Current age
     * @param  string  $gender  'male' or 'female'
     * @return float Years remaining
     */
    private function lookupLifeExpectancy(int $age, string $gender): float
    {
        // Normalize gender
        $gender = in_array($gender, ['male', 'female']) ? $gender : 'male';

        // Try exact match first
        $exactMatch = ActuarialLifeTable::where('age', $age)
            ->where('gender', $gender)
            ->where('table_year', '2020-2022')
            ->value('life_expectancy_years');

        if ($exactMatch !== null) {
            return (float) $exactMatch;
        }

        // Find surrounding ages for interpolation
        $lowerRecord = ActuarialLifeTable::where('age', '<', $age)
            ->where('gender', $gender)
            ->where('table_year', '2020-2022')
            ->orderBy('age', 'desc')
            ->first();

        $upperRecord = ActuarialLifeTable::where('age', '>', $age)
            ->where('gender', $gender)
            ->where('table_year', '2020-2022')
            ->orderBy('age', 'asc')
            ->first();

        // Handle edge cases
        if (! $lowerRecord && $upperRecord) {
            // Younger than table minimum - add years for age difference
            return (float) $upperRecord->life_expectancy_years + ($upperRecord->age - $age);
        }

        if ($lowerRecord && ! $upperRecord) {
            // Older than table maximum - reduce by age difference (minimum 1 year)
            return max(1.0, (float) $lowerRecord->life_expectancy_years - ($age - $lowerRecord->age));
        }

        if ($lowerRecord && $upperRecord) {
            // Linear interpolation between surrounding ages
            $lowerLE = (float) $lowerRecord->life_expectancy_years;
            $upperLE = (float) $upperRecord->life_expectancy_years;
            $fraction = ($age - $lowerRecord->age) / ($upperRecord->age - $lowerRecord->age);

            return $lowerLE + ($upperLE - $lowerLE) * $fraction;
        }

        // Fallback if no data found
        return max(1.0, 85.0 - $age);
    }

    /**
     * Project mortgage balance at future date
     *
     * Handles:
     * - Interest-only mortgages (balance stays the same)
     * - Repayment mortgages (amortization)
     * - Maturity dates (mortgage paid off if term ends)
     *
     * @param  string  $mortgageType  'interest_only' or 'repayment'
     * @param  float  $interestRate  Annual rate as percentage
     * @param  int  $years  Years to project
     * @return float Projected balance (0 if matured)
     */
    public function projectMortgageBalance(
        float $currentBalance,
        string $mortgageType,
        int $remainingTermMonths,
        float $interestRate,
        float $monthlyPayment,
        int $years
    ): float {
        // Convert years to months
        $monthsToProject = $years * 12;

        // Check if mortgage matures before projection date
        if ($remainingTermMonths <= $monthsToProject) {
            // Mortgage will be paid off by projection date
            return 0;
        }

        // Interest-only mortgage
        if ($mortgageType === 'interest_only') {
            // Balance stays the same (capital not repaid)
            return $currentBalance;
        }

        // Repayment mortgage - amortize
        if ($monthlyPayment > 0 && $interestRate > 0) {
            $monthlyRate = ($interestRate / 100) / 12;

            $remainingBalance = $currentBalance;
            for ($month = 1; $month <= $monthsToProject; $month++) {
                $interestPayment = $remainingBalance * $monthlyRate;
                $principalPayment = $monthlyPayment - $interestPayment;
                $remainingBalance -= $principalPayment;

                if ($remainingBalance <= 0) {
                    return 0;
                }
            }

            return max(0, $remainingBalance);
        }

        // Fallback: linear amortization
        if ($remainingTermMonths <= 0) {
            return max(0.0, $currentBalance);
        }
        $monthlyReduction = $currentBalance / $remainingTermMonths;
        $projectedBalance = $currentBalance - ($monthlyReduction * $monthsToProject);

        return max(0, $projectedBalance);
    }

    /**
     * Calculate future value of an asset given current value, growth rate, and years
     *
     * Formula: FV = PV * (1 + r)^n
     *
     * @param  float  $presentValue  Current value of asset
     * @param  float  $annualGrowthRate  Annual growth rate (as decimal, e.g., 0.05 for 5%)
     * @param  int  $years  Number of years into the future
     * @return float Future value
     */
    public function calculateFutureValue(float $presentValue, float $annualGrowthRate, int $years): float
    {
        if ($years <= 0) {
            return $presentValue;
        }

        return $presentValue * pow(1 + $annualGrowthRate, $years);
    }

    /**
     * Calculate future value of multiple assets
     *
     * @param  Collection  $assets  Collection of assets with current_value
     * @param  float  $annualGrowthRate  Annual growth rate
     * @param  int  $years  Years into future
     * @return array Future value breakdown by asset
     */
    public function calculatePortfolioFutureValue(Collection $assets, float $annualGrowthRate, int $years): array
    {
        $projections = [];
        $totalCurrentValue = 0;
        $totalFutureValue = 0;

        foreach ($assets as $asset) {
            $currentValue = $asset->current_value ?? 0;
            $futureValue = $this->calculateFutureValue($currentValue, $annualGrowthRate, $years);

            $totalCurrentValue += $currentValue;
            $totalFutureValue += $futureValue;

            $projections[] = [
                'asset_name' => $asset->asset_name ?? 'Unknown Asset',
                'asset_type' => $asset->asset_type ?? 'unknown',
                'current_value' => round($currentValue, 2),
                'future_value' => round($futureValue, 2),
                'growth_amount' => round($futureValue - $currentValue, 2),
                'growth_rate' => $annualGrowthRate,
                'years' => $years,
            ];
        }

        return [
            'total_current_value' => round($totalCurrentValue, 2),
            'total_future_value' => round($totalFutureValue, 2),
            'total_growth' => round($totalFutureValue - $totalCurrentValue, 2),
            'growth_rate' => $annualGrowthRate,
            'years' => $years,
            'asset_projections' => $projections,
        ];
    }

    /**
     * Calculate future value with different growth rates by asset type
     *
     * @param  Collection  $assets  Assets collection
     * @param  array  $growthRatesByType  Growth rates keyed by asset type
     * @param  int  $years  Years into future
     * @return array Future value breakdown
     */
    public function calculatePortfolioFutureValueByAssetType(Collection $assets, array $growthRatesByType, int $years): array
    {
        $projections = [];
        $totalCurrentValue = 0;
        $totalFutureValue = 0;

        foreach ($assets as $asset) {
            $currentValue = (float) ($asset->current_value ?? 0);
            $assetType = $asset->asset_type ?? 'other';

            // Get growth rate for this asset type, or use default
            $growthRate = $growthRatesByType[$assetType] ?? $growthRatesByType['default'] ?? 0.05;

            $futureValue = $this->calculateFutureValue($currentValue, $growthRate, $years);

            $totalCurrentValue += $currentValue;
            $totalFutureValue += $futureValue;

            $projections[] = [
                'asset_name' => $asset->asset_name ?? 'Unknown Asset',
                'asset_type' => $assetType,
                'current_value' => round($currentValue, 2),
                'future_value' => round($futureValue, 2),
                'growth_amount' => round($futureValue - $currentValue, 2),
                'growth_rate' => $growthRate,
                'years' => $years,
            ];
        }

        return [
            'total_current_value' => round($totalCurrentValue, 2),
            'total_future_value' => round($totalFutureValue, 2),
            'total_growth' => round($totalFutureValue - $totalCurrentValue, 2),
            'years' => $years,
            'asset_projections' => $projections,
        ];
    }

    /**
     * Get default growth rates by asset type (from UK tax config assumptions)
     *
     * @return array Growth rates by asset type
     */
    public function getDefaultGrowthRates(): array
    {
        $assumptions = $this->taxConfig->getAssumptions();

        $propertyGrowth = $assumptions['property_growth'] ?? 0.03;

        return [
            'property' => $propertyGrowth,
            'investment' => $assumptions['investment_growth_rate'] ?? 0.05, // 5%
            'cash' => $assumptions['cash_savings_rate'] ?? 0.04, // 4%
            'savings' => $assumptions['cash_savings_rate'] ?? 0.04, // 4%
            'pension' => $assumptions['investment_growth_rate'] ?? 0.05, // 5%
            'business' => 0.04, // 4% conservative business growth
            'other' => $propertyGrowth, // Match property growth as baseline
            'default' => $propertyGrowth, // Match property growth as fallback
        ];
    }

    /**
     * Calculate real future value (adjusted for inflation)
     *
     * @param  float  $presentValue  Current value
     * @param  float  $nominalGrowthRate  Nominal growth rate
     * @param  float  $inflationRate  Inflation rate
     * @param  int  $years  Years into future
     * @return float Real future value (inflation-adjusted)
     */
    public function calculateRealFutureValue(
        float $presentValue,
        float $nominalGrowthRate,
        float $inflationRate,
        int $years
    ): float {
        if ($years <= 0) {
            return $presentValue;
        }

        // Real growth rate = ((1 + nominal) / (1 + inflation)) - 1
        $realGrowthRate = ((1 + $nominalGrowthRate) / (1 + $inflationRate)) - 1;

        return $this->calculateFutureValue($presentValue, $realGrowthRate, $years);
    }

    /**
     * Project estate value at expected death date
     *
     * @param  Collection  $assets  Current assets
     * @param  int  $yearsUntilDeath  Years until expected death
     * @param  array|null  $growthRatesByType  Optional custom growth rates
     * @return array Estate projection
     */
    public function projectEstateAtDeath(Collection $assets, int $yearsUntilDeath, ?array $growthRatesByType = null): array
    {
        $growthRates = $growthRatesByType ?? $this->getDefaultGrowthRates();

        $projection = $this->calculatePortfolioFutureValueByAssetType($assets, $growthRates, $yearsUntilDeath);

        return [
            'current_estate_value' => $projection['total_current_value'],
            'projected_estate_value_at_death' => $projection['total_future_value'],
            'projected_growth' => $projection['total_growth'],
            'years_until_death' => $yearsUntilDeath,
            'growth_rates_used' => $growthRates,
            'asset_projections' => $projection['asset_projections'],
        ];
    }

    /**
     * Calculate compound annual growth rate (CAGR) needed to reach target value
     *
     * @param  float  $presentValue  Current value
     * @param  float  $targetValue  Target future value
     * @param  int  $years  Years to reach target
     * @return float Required CAGR
     */
    public function calculateRequiredGrowthRate(float $presentValue, float $targetValue, int $years): float
    {
        if ($years <= 0 || $presentValue <= 0) {
            return 0;
        }

        // CAGR = (FV / PV)^(1/n) - 1
        return pow(($targetValue / $presentValue), (1 / $years)) - 1;
    }
}
