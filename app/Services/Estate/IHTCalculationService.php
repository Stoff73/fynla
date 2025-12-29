<?php

declare(strict_types=1);

namespace App\Services\Estate;

use App\Models\Estate\IHTCalculation;
use App\Models\User;
use App\Services\TaxConfigService;
use Illuminate\Support\Collection;

/**
 * Simplified IHT Calculation Service
 *
 * Handles all IHT calculations with caching and clear explanatory messages
 */
class IHTCalculationService
{
    public function __construct(
        private EstateAssetAggregatorService $aggregator,
        private TaxConfigService $taxConfig
    ) {}

    /**
     * Calculate IHT liability with caching
     *
     * @param  User  $user  The primary user
     * @param  User|null  $spouse  The spouse (if married and linked)
     * @param  bool  $dataSharingEnabled  Whether spouse data sharing is enabled
     * @return array IHT calculation results with all breakdown values
     */
    public function calculate(User $user, ?User $spouse = null, bool $dataSharingEnabled = false): array
    {
        // 1. Check cache first
        $cached = $this->getCachedCalculation($user, $spouse, $dataSharingEnabled);
        if ($cached) {
            return $cached;
        }

        // 2. Get tax config
        $ihtConfig = $this->taxConfig->getInheritanceTax();
        $isMarried = in_array($user->marital_status, ['married']) && $spouse !== null;

        // 3. Fetch and sum assets (exclude IHT-exempt assets like pensions)
        $userAssets = $this->aggregator->gatherUserAssets($user);
        $spouseAssets = ($isMarried && $dataSharingEnabled)
            ? $this->aggregator->gatherUserAssets($spouse)
            : collect();

        // Filter out IHT-exempt assets (DC pensions, etc.)
        $userTaxableAssets = $userAssets->reject(fn ($asset) => $asset->is_iht_exempt ?? false);
        $spouseTaxableAssets = $spouseAssets->reject(fn ($asset) => $asset->is_iht_exempt ?? false);

        $userGrossAssets = $userTaxableAssets->sum('current_value');
        $spouseGrossAssets = $spouseTaxableAssets->sum('current_value');
        $totalGrossAssets = $userGrossAssets + $spouseGrossAssets;

        // 4. Fetch and sum liabilities
        $userLiabilities = $this->aggregator->calculateUserLiabilities($user);
        $spouseLiabilities = ($isMarried && $dataSharingEnabled)
            ? $this->aggregator->calculateUserLiabilities($spouse)
            : 0;
        $totalLiabilities = $userLiabilities + $spouseLiabilities;

        // 5. Calculate net estate
        $userNetEstate = $userGrossAssets - $userLiabilities;
        $spouseNetEstate = $spouseGrossAssets - $spouseLiabilities;
        $totalNetEstate = $totalGrossAssets - $totalLiabilities;

        // 6. Calculate NRB with message
        $nrbSingle = $ihtConfig['nil_rate_band']; // £325,000
        $nrbAvailable = $isMarried ? ($nrbSingle * 2) : $nrbSingle;

        $nrbMessage = $isMarried
            ? 'Combined Nil Rate Band of £'.number_format($nrbAvailable).' available (£'.number_format($nrbSingle).' each). Transfers between spouses are exempt from IHT on first death.'
            : 'Nil Rate Band of £'.number_format($nrbAvailable).' available for single person.';

        // 7. Calculate RNRB with message (ALWAYS calculate, even if £0)
        $rnrbData = $this->calculateRNRB($totalNetEstate, $user, $spouse, $ihtConfig, $isMarried);

        // 8. Calculate taxable estate and IHT (CURRENT values)
        $totalAllowances = $nrbAvailable + $rnrbData['rnrb_available'];
        $taxableEstate = max(0, $totalNetEstate - $totalAllowances);
        $ihtRate = $ihtConfig['standard_rate']; // 0.40 (40%)
        $ihtLiability = $taxableEstate * $ihtRate;
        $effectiveRate = $totalNetEstate > 0 ? ($ihtLiability / $totalNetEstate * 100) : 0;

        // 9. Calculate PROJECTED values at death using actuarial tables and 4.7% growth
        $projectedData = $this->calculateProjectedValues(
            $user,
            $spouse,
            $totalGrossAssets,
            $totalLiabilities,
            $nrbAvailable,
            $rnrbData,
            $isMarried,
            $ihtRate
        );

        // 10. Build result array with CURRENT and PROJECTED values
        $result = [
            // Current values
            'user_gross_assets' => round($userGrossAssets, 2),
            'spouse_gross_assets' => round($spouseGrossAssets, 2),
            'total_gross_assets' => round($totalGrossAssets, 2),

            'user_total_liabilities' => round($userLiabilities, 2),
            'spouse_total_liabilities' => round($spouseLiabilities, 2),
            'total_liabilities' => round($totalLiabilities, 2),

            'user_net_estate' => round($userNetEstate, 2),
            'spouse_net_estate' => round($spouseNetEstate, 2),
            'total_net_estate' => round($totalNetEstate, 2),

            'nrb_available' => round($nrbAvailable, 2),
            'nrb_message' => $nrbMessage,

            'rnrb_available' => round($rnrbData['rnrb_available'], 2),
            'rnrb_status' => $rnrbData['rnrb_status'],
            'rnrb_message' => $rnrbData['rnrb_message'],

            'total_allowances' => round($totalAllowances, 2),
            'taxable_estate' => round($taxableEstate, 2),
            'iht_liability' => round($ihtLiability, 2),
            'effective_rate' => round($effectiveRate, 2),

            // Projected values at death
            'projected_gross_assets' => $projectedData['projected_gross_assets'],
            'projected_liabilities' => $projectedData['projected_liabilities'],
            'projected_net_estate' => $projectedData['projected_net_estate'],
            'projected_taxable_estate' => $projectedData['projected_taxable_estate'],
            'projected_iht_liability' => $projectedData['projected_iht_liability'],
            'years_to_death' => $projectedData['years_to_death'],
            'estimated_age_at_death' => $projectedData['estimated_age_at_death'],

            'is_married' => $isMarried,
            'data_sharing_enabled' => $dataSharingEnabled,
        ];

        // 10. Save to database
        $this->saveCalculation($user, $result, $userAssets, $spouseAssets, $userLiabilities, $spouseLiabilities);

        return $result;
    }

    /**
     * Calculate projected values at death using actuarial tables and 4.7% growth
     *
     * For married couples, projects to SECOND DEATH (whoever lives longer)
     * to accurately calculate combined IHT liability
     */
    private function calculateProjectedValues(
        User $user,
        ?User $spouse,
        float $currentGrossAssets,
        float $currentLiabilities,
        float $nrbAvailable,
        array $rnrbData,
        bool $isMarried,
        float $ihtRate = 0.40
    ): array {
        // Annual growth rate for estate projection (4.7%)
        $annualGrowthRatePercent = 4.7;

        // For married couples, calculate BOTH life expectancies and use the longer one (second death)
        if ($isMarried && $spouse && $spouse->date_of_birth && $spouse->gender) {
            $userYearsUntilDeath = $this->calculateLifeExpectancy($user);
            $spouseYearsUntilDeath = $this->calculateLifeExpectancy($spouse);

            // Use the LONGER life expectancy (second death scenario)
            $yearsUntilDeath = max($userYearsUntilDeath, $spouseYearsUntilDeath);

            // Determine who dies second and use their estimated age
            if ($spouseYearsUntilDeath > $userYearsUntilDeath) {
                $estimatedAgeAtDeath = \Carbon\Carbon::parse($spouse->date_of_birth)->age + $spouseYearsUntilDeath;
            } else {
                $estimatedAgeAtDeath = \Carbon\Carbon::parse($user->date_of_birth)->age + $userYearsUntilDeath;
            }
        } elseif (! $user->date_of_birth || ! $user->gender) {
            // No DOB/gender - assume 25 years
            $yearsUntilDeath = 25;
            $estimatedAgeAtDeath = $user->date_of_birth
                ? \Carbon\Carbon::parse($user->date_of_birth)->age + $yearsUntilDeath
                : 80;
        } else {
            // Single person - use their own life expectancy
            $yearsUntilDeath = $this->calculateLifeExpectancy($user);
            $estimatedAgeAtDeath = \Carbon\Carbon::parse($user->date_of_birth)->age + $yearsUntilDeath;
        }

        // Calculate projected assets using compound growth: FV = PV × (1 + r)^n
        $growthMultiplier = pow(1 + ($annualGrowthRatePercent / 100), $yearsUntilDeath);
        $projectedGrossAssets = $currentGrossAssets * $growthMultiplier;

        // Liabilities assumed constant (conservative)
        $projectedLiabilities = $currentLiabilities;

        // Projected net estate
        $projectedNetEstate = $projectedGrossAssets - $projectedLiabilities;

        // Calculate projected IHT using same allowances
        $totalAllowances = $nrbAvailable + $rnrbData['rnrb_available'];
        $projectedTaxableEstate = max(0, $projectedNetEstate - $totalAllowances);
        $projectedIHTLiability = $projectedTaxableEstate * $ihtRate;

        return [
            'projected_gross_assets' => round($projectedGrossAssets, 2),
            'projected_liabilities' => round($projectedLiabilities, 2),
            'projected_net_estate' => round($projectedNetEstate, 2),
            'projected_taxable_estate' => round($projectedTaxableEstate, 2),
            'projected_iht_liability' => round($projectedIHTLiability, 2),
            'years_to_death' => $yearsUntilDeath,
            'estimated_age_at_death' => $estimatedAgeAtDeath,
        ];
    }

    /**
     * Calculate RNRB with full explanation message
     *
     * ALWAYS returns a value (even £0) with explanatory message
     */
    private function calculateRNRB(
        float $totalNetEstate,
        User $user,
        ?User $spouse,
        array $ihtConfig,
        bool $isMarried
    ): array {
        $rnrbSingle = $ihtConfig['residence_nil_rate_band']; // £175,000
        $taperThreshold = $ihtConfig['rnrb_taper_threshold']; // £2,000,000
        $taperRate = $ihtConfig['rnrb_taper_rate']; // 0.5 (£1 lost per £2 over threshold)

        // Check eligibility: must own main residence
        $hasMainResidence = $this->hasMainResidence($user, $spouse);

        if (! $hasMainResidence) {
            return [
                'rnrb_available' => 0,
                'rnrb_status' => 'none',
                'rnrb_message' => 'Residence Nil Rate Band not available. You need to own a main residence and leave it to direct descendants to qualify for RNRB of up to £'.number_format($isMarried ? $rnrbSingle * 2 : $rnrbSingle).'.',
            ];
        }

        // Calculate full RNRB (single or married)
        $fullRNRB = $isMarried ? ($rnrbSingle * 2) : $rnrbSingle;

        // Check for taper
        if ($totalNetEstate <= $taperThreshold) {
            return [
                'rnrb_available' => $fullRNRB,
                'rnrb_status' => 'full',
                'rnrb_message' => 'Full Residence Nil Rate Band of £'.number_format($fullRNRB).' available'.($isMarried ? ' (£'.number_format($rnrbSingle).' each)' : '').'. Your combined estate is below the £'.number_format($taperThreshold).' taper threshold.',
            ];
        }

        // Apply taper
        $excess = $totalNetEstate - $taperThreshold;
        $reduction = $excess * $taperRate;
        $rnrbAvailable = max(0, $fullRNRB - $reduction);

        if ($rnrbAvailable > 0) {
            return [
                'rnrb_available' => $rnrbAvailable,
                'rnrb_status' => 'tapered',
                'rnrb_message' => 'Residence Nil Rate Band reduced to £'.number_format($rnrbAvailable).' due to estate taper. Your estate of £'.number_format($totalNetEstate).' exceeds £'.number_format($taperThreshold).' by £'.number_format($excess).', reducing RNRB by £'.number_format($reduction).' (£1 reduction per £2 over threshold).',
            ];
        }

        // Fully tapered away
        return [
            'rnrb_available' => 0,
            'rnrb_status' => 'tapered',
            'rnrb_message' => 'Residence Nil Rate Band fully tapered away. Your estate of £'.number_format($totalNetEstate).' exceeds the taper threshold of £'.number_format($taperThreshold).' by £'.number_format($excess).', eliminating all RNRB of £'.number_format($fullRNRB).'.',
        ];
    }

    /**
     * Calculate life expectancy for a user using actuarial tables
     */
    private function calculateLifeExpectancy(User $user): int
    {
        if (! $user->date_of_birth || ! $user->gender) {
            return 25; // Default fallback
        }

        $currentAge = \Carbon\Carbon::parse($user->date_of_birth)->age;

        // Query actuarial table for life expectancy
        $lifeExpectancy = \DB::table('actuarial_life_tables')
            ->where('age', '<=', $currentAge)
            ->where('gender', $user->gender)
            ->where('table_year', '2020-2022')
            ->orderBy('age', 'desc')
            ->first();

        if ($lifeExpectancy) {
            return (int) round((float) $lifeExpectancy->life_expectancy_years);
        }

        // Fallback if no actuarial data
        return max(1, 85 - $currentAge);
    }

    /**
     * Check if user or spouse has main residence
     */
    private function hasMainResidence(User $user, ?User $spouse): bool
    {
        $userHasMainRes = \App\Models\Property::where('user_id', $user->id)
            ->where('property_type', 'main_residence')
            ->exists();

        if ($userHasMainRes) {
            return true;
        }

        if ($spouse) {
            return \App\Models\Property::where('user_id', $spouse->id)
                ->where('property_type', 'main_residence')
                ->exists();
        }

        return false;
    }

    /**
     * Get cached calculation if valid
     */
    private function getCachedCalculation(User $user, ?User $spouse, bool $dataSharingEnabled): ?array
    {
        // Get latest calculation for this user
        $cached = IHTCalculation::where('user_id', $user->id)
            ->where('is_married', $spouse !== null)
            ->where('data_sharing_enabled', $dataSharingEnabled)
            ->latest('calculation_date')
            ->first();

        if (! $cached) {
            return null;
        }

        // Generate current hashes
        $currentHashes = $this->generateHashes($user, $spouse, $dataSharingEnabled);

        // Check if hashes match (data hasn't changed)
        if ($cached->assets_hash === $currentHashes['assets_hash'] &&
            $cached->liabilities_hash === $currentHashes['liabilities_hash']) {
            // Return cached data as array
            return $cached->toArray();
        }

        return null;
    }

    /**
     * Generate hashes for cache invalidation
     */
    private function generateHashes(User $user, ?User $spouse, bool $dataSharingEnabled): array
    {
        $userAssets = $this->aggregator->gatherUserAssets($user);
        $spouseAssets = ($spouse && $dataSharingEnabled) ? $this->aggregator->gatherUserAssets($spouse) : collect();

        $assetsString = $userAssets->pluck('current_value')->join(',').'|'.$spouseAssets->pluck('current_value')->join(',');
        $assetsHash = hash('sha256', $assetsString);

        $userLiabilities = $this->aggregator->calculateUserLiabilities($user);
        $spouseLiabilities = ($spouse && $dataSharingEnabled) ? $this->aggregator->calculateUserLiabilities($spouse) : 0;

        $liabilitiesString = $userLiabilities.'|'.$spouseLiabilities;
        $liabilitiesHash = hash('sha256', $liabilitiesString);

        return [
            'assets_hash' => $assetsHash,
            'liabilities_hash' => $liabilitiesHash,
        ];
    }

    /**
     * Save calculation to database
     */
    private function saveCalculation(
        User $user,
        array $result,
        Collection $userAssets,
        Collection $spouseAssets,
        float $userLiabilities,
        float $spouseLiabilities
    ): void {
        // Generate hashes
        $assetsString = $userAssets->pluck('current_value')->join(',').'|'.$spouseAssets->pluck('current_value')->join(',');
        $liabilitiesString = $userLiabilities.'|'.$spouseLiabilities;

        IHTCalculation::create([
            'user_id' => $user->id,
            'user_gross_assets' => $result['user_gross_assets'],
            'spouse_gross_assets' => $result['spouse_gross_assets'],
            'total_gross_assets' => $result['total_gross_assets'],
            'user_total_liabilities' => $result['user_total_liabilities'],
            'spouse_total_liabilities' => $result['spouse_total_liabilities'],
            'total_liabilities' => $result['total_liabilities'],
            'user_net_estate' => $result['user_net_estate'],
            'spouse_net_estate' => $result['spouse_net_estate'],
            'total_net_estate' => $result['total_net_estate'],
            'nrb_available' => $result['nrb_available'],
            'nrb_message' => $result['nrb_message'],
            'rnrb_available' => $result['rnrb_available'],
            'rnrb_status' => $result['rnrb_status'],
            'rnrb_message' => $result['rnrb_message'],
            'total_allowances' => $result['total_allowances'],
            'taxable_estate' => $result['taxable_estate'],
            'iht_liability' => $result['iht_liability'],
            'effective_rate' => $result['effective_rate'],
            'projected_gross_assets' => $result['projected_gross_assets'],
            'projected_liabilities' => $result['projected_liabilities'],
            'projected_net_estate' => $result['projected_net_estate'],
            'projected_taxable_estate' => $result['projected_taxable_estate'],
            'projected_iht_liability' => $result['projected_iht_liability'],
            'years_to_death' => $result['years_to_death'],
            'estimated_age_at_death' => $result['estimated_age_at_death'],
            'calculation_date' => now(),
            'is_married' => $result['is_married'],
            'data_sharing_enabled' => $result['data_sharing_enabled'],
            'assets_hash' => hash('sha256', $assetsString),
            'liabilities_hash' => hash('sha256', $liabilitiesString),
        ]);
    }

    /**
     * Invalidate cache when assets or liabilities change
     */
    public function invalidateCache(User $user): void
    {
        IHTCalculation::where('user_id', $user->id)->delete();
    }
}
