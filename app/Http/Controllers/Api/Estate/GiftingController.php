<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Estate;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\Estate\Asset;
use App\Models\Estate\IHTProfile;
use App\Services\Estate\CashFlowProjector;
use App\Services\Estate\EstateAssetAggregatorService;
use App\Services\Estate\GiftingStrategyOptimizer;
use App\Services\Estate\PersonalizedGiftingStrategyService;
use App\Services\Estate\PersonalizedTrustStrategyService;
use App\Services\Estate\TrustService;
use App\Services\TaxConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GiftingController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly IHTController $ihtController,
        private readonly CashFlowProjector $cashFlowProjector,
        private readonly GiftingStrategyOptimizer $giftingOptimizer,
        private readonly PersonalizedGiftingStrategyService $personalizedGiftingStrategy,
        private readonly PersonalizedTrustStrategyService $personalizedTrustStrategy,
        private readonly EstateAssetAggregatorService $assetAggregator,
        private readonly TrustService $trustService,
        private readonly TaxConfigService $taxConfig
    ) {}

    /**
     * Get planned gifting strategy with PET cycles and annual exemptions
     */
    public function getPlannedGiftingStrategy(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            // Validate user has required data
            if (! $user->date_of_birth || ! $user->gender) {
                return response()->json([
                    'success' => false,
                    'message' => 'Date of birth and gender are required to calculate life expectancy',
                    'requires_profile_update' => true,
                ], 422);
            }

            // ========== USE EXISTING IHT PLANNING CALCULATION ==========
            // Call the IHTController method instead of duplicating logic
            $ihtPlanningResponse = $this->ihtController->calculateIHT($request);
            $ihtPlanningData = $ihtPlanningResponse->getData(true);

            if (! $ihtPlanningData['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to calculate IHT planning data',
                ], 500);
            }

            // Extract the IHT summary data (new unified structure)
            $ihtSummary = $ihtPlanningData['iht_summary'];
            $assetsBreakdown = $ihtPlanningData['assets_breakdown'];
            $liabilitiesBreakdown = $ihtPlanningData['liabilities_breakdown'];
            $yearsUntilDeath = (int) $ihtSummary['projected']['years_to_death'];

            // Current IHT liability
            $currentIHTLiability = $ihtSummary['current']['iht_liability'];

            // Projected IHT liability at death
            $projectedIHTLiability = $ihtSummary['projected']['iht_liability'];
            $projectedEstateValue = $ihtSummary['projected']['net_estate'];

            // Get IHT profile
            $ihtProfile = IHTProfile::where('user_id', $user->id)->first();
            if (! $ihtProfile) {
                $isMarried = in_array($user->marital_status, ['married']);
                $ihtConfig = $this->taxConfig->getInheritanceTax();
                $defaultSpouseNRB = $isMarried ? $ihtConfig['nil_rate_band'] : 0;

                $ihtProfile = new IHTProfile([
                    'user_id' => $user->id,
                    'marital_status' => $user->marital_status ?? 'single',
                    'own_home' => false,
                    'home_value' => 0,
                    'nrb_transferred_from_spouse' => $defaultSpouseNRB,
                    'charitable_giving_percent' => 0,
                ]);
            }

            // Get current tax year for cash flow
            $currentTaxYear = (int) date('Y');
            $cashFlow = $this->cashFlowProjector->createPersonalPL($user->id, (string) $currentTaxYear);
            $annualExpenditure = $cashFlow['total_expenses'] ?? 0;

            // Calculate total NRB available
            $ihtConfig = $this->taxConfig->getInheritanceTax();
            $giftingConfig = $this->taxConfig->getGiftingExemptions();

            // CRITICAL: For LIFETIME GIFTING, only the user's own NRB (£325k) applies
            // Spouse NRB transfer ONLY applies on death for IHT calculation, NOT for lifetime gifts
            $totalNRBAvailable = $ihtConfig['nil_rate_band']; // £325,000 - user's own NRB only

            // Calculate RNRB if own home
            $rnrbAvailable = 0;
            if ($ihtProfile->own_home) {
                $rnrbAvailable = $ihtConfig['residence_nil_rate_band'];
            }

            // Only calculate detailed strategy if there's actual projected IHT liability
            $strategy = null;
            if ($projectedIHTLiability > 0) {
                $strategy = $this->giftingOptimizer->calculateOptimalGiftingStrategy(
                    projectedEstateValue: $projectedEstateValue,
                    currentIHTLiability: $projectedIHTLiability,
                    yearsUntilDeath: $yearsUntilDeath,
                    user: $user,
                    totalNRBAvailable: $totalNRBAvailable,
                    rnrbAvailable: $rnrbAvailable,
                    annualExpenditure: $annualExpenditure
                );
            }

            // Get current age and life expectancy details (already fetched above)
            $currentAge = $user->date_of_birth->age;
            $estimatedAgeAtDeath = $currentAge + $yearsUntilDeath;
            $estimatedDateOfDeath = now()->addYears($yearsUntilDeath);

            // Calculate number of complete 7-year PET cycles available
            $complete7YearCycles = floor($yearsUntilDeath / 7);

            // Calculate annual exemption totals
            $annualExemption = $giftingConfig['annual_exemption'];
            $totalAnnualExemptionGifts = $annualExemption * $yearsUntilDeath;
            $annualExemptionIHTSaved = $totalAnnualExemptionGifts * $ihtConfig['standard_rate'];

            // Create a clear annual gifting schedule
            $annualGiftingSchedule = [];
            for ($year = 0; $year < $yearsUntilDeath; $year++) {
                $annualGiftingSchedule[] = [
                    'year' => $year,
                    'age' => $currentAge + $year,
                    'annual_exemption' => $annualExemption,
                    'date' => now()->addYears($year)->format('Y-m-d'),
                ];
            }

            // Create PET cycle framework (educational, not specific amounts)
            $petCycleFramework = [];
            for ($cycle = 0; $cycle < $complete7YearCycles; $cycle++) {
                $giftYear = $cycle * 7;
                $exemptYear = $giftYear + 7;
                $petCycleFramework[] = [
                    'cycle_number' => $cycle + 1,
                    'gift_year' => $giftYear,
                    'gift_age' => $currentAge + $giftYear,
                    'exempt_year' => $exemptYear,
                    'exempt_age' => $currentAge + $exemptYear,
                    'description' => 'PET Cycle '.($cycle + 1).': Gift at age '.($currentAge + $giftYear).', becomes IHT-free at age '.($currentAge + $exemptYear),
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'life_expectancy_analysis' => [
                        'current_age' => $currentAge,
                        'life_expectancy_years' => $yearsUntilDeath,
                        'estimated_age_at_death' => $estimatedAgeAtDeath,
                        'estimated_date_of_death' => $estimatedDateOfDeath->format('Y-m-d'),
                        'years_until_expected_death' => $yearsUntilDeath,
                        'complete_7_year_pet_cycles' => $complete7YearCycles,
                    ],
                    'current_estate' => [
                        'total_assets' => round($assetsBreakdown['user']['total'] + ($assetsBreakdown['spouse']['total'] ?? 0), 2),
                        'total_liabilities' => round($liabilitiesBreakdown['user']['total'] + ($liabilitiesBreakdown['spouse']['total'] ?? 0), 2),
                        'net_worth' => round($ihtSummary['current']['net_estate'], 2),
                        'iht_liability' => round($currentIHTLiability, 2),
                    ],
                    'projected_estate_at_death' => [
                        'total_assets' => round($projectedEstateValue + ($currentIHTLiability / 0.40), 2), // Approximate gross estate
                        'total_liabilities' => round($liabilitiesBreakdown['user']['total'] + ($liabilitiesBreakdown['spouse']['total'] ?? 0), 2),
                        'net_estate' => round($projectedEstateValue, 2),
                        'iht_liability' => round($projectedIHTLiability, 2),
                        'years_from_now' => $yearsUntilDeath,
                    ],
                    'annual_exemption_plan' => [
                        'annual_amount' => $annualExemption,
                        'years_available' => $yearsUntilDeath,
                        'total_over_lifetime' => round($totalAnnualExemptionGifts, 2),
                        'total_iht_saved' => round($annualExemptionIHTSaved, 2),
                        'schedule' => array_slice($annualGiftingSchedule, 0, 10), // First 10 years for display
                        'total_entries' => count($annualGiftingSchedule),
                    ],
                    'pet_cycle_framework' => [
                        'cycles_available' => $complete7YearCycles,
                        'nil_rate_band' => $totalNRBAvailable,
                        'maximum_per_cycle' => $totalNRBAvailable, // Can gift up to NRB per cycle
                        'total_potential' => $totalNRBAvailable * $complete7YearCycles,
                        'cycles' => $petCycleFramework,
                        'has_iht_liability' => $projectedIHTLiability > 0,
                    ],
                    'gifting_strategy' => $strategy,
                ],
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Calculating planned gifting strategy');
        }
    }

    /**
     * Get personalized asset-based gifting strategy
     *
     * This replaces the generic gifting strategy with a personalized approach
     * that analyzes the user's actual assets and their liquidity to provide
     * tailored gifting recommendations.
     */
    public function getPersonalizedGiftingStrategy(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            // Get user's assets from ALL sources (properties, investments, savings, etc.)
            $aggregatedAssets = $this->assetAggregator->gatherUserAssets($user);

            // Convert aggregated assets to Asset models for liquidity analysis
            $assets = $aggregatedAssets->map(function ($asset) use ($user) {
                return new Asset([
                    'user_id' => $user->id,
                    'asset_type' => $asset->asset_type,
                    'asset_name' => $asset->asset_name,
                    'current_value' => $asset->current_value,
                    'is_iht_exempt' => $asset->is_iht_exempt ?? false,
                ]);
            });

            if ($assets->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No assets found. Please add assets to generate a personalized gifting strategy.',
                    'requires_assets' => true,
                ], 422);
            }

            // Get IHT planning data to determine current liability
            $ihtPlanningResponse = $this->ihtController->calculateIHT($request);
            $ihtPlanningData = $ihtPlanningResponse->getData(true);

            if (! $ihtPlanningData['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to calculate IHT planning data',
                ], 500);
            }

            // Extract IHT summary data (new unified structure)
            $ihtSummary = $ihtPlanningData['iht_summary'];
            $yearsUntilDeath = (int) $ihtSummary['projected']['years_to_death'];

            // Use CURRENT IHT liability and gross estate value (not projected)
            $currentIHTLiability = $ihtSummary['current']['iht_liability'];
            // Get gross estate value from calculation (not iht_summary)
            $currentGrossEstateValue = $ihtPlanningData['calculation']['total_gross_assets'] ?? null;

            // Get IHT profile
            $ihtProfile = IHTProfile::where('user_id', $user->id)->first();
            if (! $ihtProfile) {
                $isMarried = in_array($user->marital_status, ['married']);
                $ihtConfig = $this->taxConfig->getInheritanceTax();
                $defaultSpouseNRB = $isMarried ? $ihtConfig['nil_rate_band'] : 0;

                $ihtProfile = new IHTProfile([
                    'user_id' => $user->id,
                    'marital_status' => $user->marital_status ?? 'single',
                    'own_home' => false,
                    'home_value' => 0,
                    'nrb_transferred_from_spouse' => $defaultSpouseNRB,
                    'charitable_giving_percent' => 0,
                ]);
            }

            // Generate personalized strategy
            $personalizedStrategy = $this->personalizedGiftingStrategy->generatePersonalizedStrategy(
                assets: $assets,
                currentIHTLiability: $currentIHTLiability,
                profile: $ihtProfile,
                user: $user,
                yearsUntilDeath: $yearsUntilDeath
            );

            // Override liquidity analysis total_value with accurate gross estate value from IHT calculation
            if ($currentGrossEstateValue !== null) {
                $personalizedStrategy['liquidity_analysis']['summary']['total_value'] = round($currentGrossEstateValue, 2);
            }

            return response()->json([
                'success' => true,
                'data' => $personalizedStrategy,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Generating personalised gifting strategy');
        }
    }

    /**
     * Get personalized trust planning strategy with CLT taxation
     *
     * This provides trust planning strategies accounting for:
     * - Chargeable Lifetime Transfers (CLTs) to relevant property trusts
     * - 20% lifetime charge on amounts exceeding NRB (£325,000)
     * - Additional charge to 40% if death within 7 years (with taper relief)
     * - 7-year rolling window for cumulative CLTs
     */
    public function getPersonalizedTrustStrategy(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            // Get user's assets from ALL sources
            $aggregatedAssets = $this->assetAggregator->gatherUserAssets($user);

            // Convert aggregated assets to Asset models for liquidity analysis
            $assets = $aggregatedAssets->map(function ($asset) use ($user) {
                return new Asset([
                    'user_id' => $user->id,
                    'asset_type' => $asset->asset_type,
                    'asset_name' => $asset->asset_name,
                    'current_value' => $asset->current_value,
                    'is_iht_exempt' => $asset->is_iht_exempt ?? false,
                ]);
            });

            if ($assets->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No assets found. Please add assets to generate a personalized trust strategy.',
                    'requires_assets' => true,
                ], 422);
            }

            // Get IHT planning data to determine current liability
            $ihtPlanningResponse = $this->ihtController->calculateIHT($request);
            $ihtPlanningData = $ihtPlanningResponse->getData(true);

            if (! $ihtPlanningData['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to calculate IHT planning data for trust strategy',
                ], 500);
            }

            // Extract IHT summary data (new unified structure)
            $ihtSummary = $ihtPlanningData['iht_summary'];
            $yearsUntilDeath = (int) $ihtSummary['projected']['years_to_death'];
            $currentIHTLiability = $ihtSummary['current']['iht_liability'];
            // Get gross estate value from calculation (not iht_summary)
            $currentGrossEstateValue = $ihtPlanningData['calculation']['total_gross_assets'] ?? null;

            // Get or create IHT profile
            $ihtProfile = IHTProfile::where('user_id', $user->id)->first();
            if (! $ihtProfile) {
                $ihtConfig = $this->taxConfig->getInheritanceTax();
                $ihtProfile = new IHTProfile([
                    'user_id' => $user->id,
                    'marital_status' => $user->marital_status ?? 'single',
                    'own_home' => false,
                    'home_value' => 0,
                    'available_nrb' => $ihtConfig['nil_rate_band'], // £325,000
                    'charitable_giving_percent' => 0,
                ]);
            }

            // Generate personalized trust strategy
            $trustStrategy = $this->personalizedTrustStrategy->generatePersonalizedTrustStrategy(
                assets: $assets,
                currentIHTLiability: $currentIHTLiability,
                profile: $ihtProfile,
                user: $user,
                yearsUntilDeath: $yearsUntilDeath
            );

            // Override liquidity analysis total_value with accurate gross estate value from IHT calculation
            if ($currentGrossEstateValue !== null) {
                $trustStrategy['liquidity_analysis']['summary']['total_value'] = round($currentGrossEstateValue, 2);
            }

            return response()->json([
                'success' => true,
                'data' => $trustStrategy,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Generating personalised trust strategy');
        }
    }

    /**
     * Calculate discounted gift trust discount estimate
     */
    public function calculateDiscountedGiftDiscount(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'age' => 'required|integer|min:18|max:100',
            'gender' => 'sometimes|in:male,female',
            'gift_value' => 'required|numeric|min:1',
            'annual_income' => 'required|numeric|min:0',
        ]);

        $estimate = $this->trustService->estimateDiscountedGiftDiscount(
            $validated['age'],
            $validated['gift_value'],
            $validated['annual_income'],
            $validated['gender'] ?? null
        );

        return response()->json([
            'success' => true,
            'data' => $estimate,
        ]);
    }
}
