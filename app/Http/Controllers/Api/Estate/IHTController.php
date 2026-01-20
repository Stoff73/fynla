<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Estate;

use App\Http\Controllers\Controller;
use App\Models\Estate\Liability;
use App\Models\Estate\Will;
use App\Models\LifeInsurancePolicy;
use App\Models\Mortgage;
use App\Models\User;
use App\Services\Estate\EstateAssetAggregatorService;
use App\Services\Estate\IHTCalculationService;
use App\Services\TaxConfigService;
use App\Traits\CalculatesOwnershipShare;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IHTController extends Controller
{
    use CalculatesOwnershipShare;
    public function __construct(
        private IHTCalculationService $ihtCalculationService,
        private EstateAssetAggregatorService $assetAggregator,
        private TaxConfigService $taxConfig
    ) {}

    /**
     * UNIFIED IHT Calculation - Handles all scenarios:
     * - Single users
     * - Married users without linked spouse
     * - Married users with linked spouse (second death scenario)
     */
    public function calculateIHT(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            // Determine user scenario
            $hasLinkedSpouse = $user->spouse_id !== null;
            $spouse = $hasLinkedSpouse ? User::find($user->spouse_id) : null;
            $dataSharingEnabled = $hasLinkedSpouse && $user->hasAcceptedSpousePermission();

            // Calculate IHT using the simplified service
            $calculation = $this->ihtCalculationService->calculate($user, $spouse, $dataSharingEnabled);

            // Format assets and liabilities breakdown
            $userAssets = $this->assetAggregator->gatherUserAssets($user);
            $spouseAssets = ($spouse && $dataSharingEnabled)
                ? $this->assetAggregator->gatherUserAssets($spouse)
                : collect();

            $assetsBreakdown = $this->formatAssetsBreakdown(
                $userAssets,
                $spouseAssets,
                $dataSharingEnabled,
                $user,
                $spouse
            );

            $liabilitiesBreakdown = $this->formatLiabilitiesBreakdown(
                $user,
                $spouse,
                $dataSharingEnabled
            );

            // Calculate total liabilities (current and projected)
            $totalLiabilities = $liabilitiesBreakdown['user']['total'];
            $projectedLiabilities = $liabilitiesBreakdown['user']['projected_total'];

            if ($dataSharingEnabled && isset($liabilitiesBreakdown['spouse'])) {
                $totalLiabilities += $liabilitiesBreakdown['spouse']['total'];
                $projectedLiabilities += $liabilitiesBreakdown['spouse']['projected_total'];
            }

            // Add liabilities to calculation object
            $calculation['total_liabilities'] = $totalLiabilities;
            $calculation['projected_liabilities'] = $projectedLiabilities;

            // Recalculate projected net estate using correct projected liabilities
            // (Service assumes liabilities stay constant, but mortgages are paid off by age 70)
            $calculation['projected_net_estate'] = $calculation['projected_gross_assets'] - $projectedLiabilities;

            // Recalculate projected taxable estate and IHT with corrected net estate
            $totalAllowances = $calculation['nrb_available'] + $calculation['rnrb_available'];
            $calculation['projected_taxable_estate'] = max(0, $calculation['projected_net_estate'] - $totalAllowances);
            $calculation['projected_iht_liability'] = $calculation['projected_taxable_estate'] * 0.40;

            // Format response for frontend compatibility
            $response = [
                'success' => true,
                'calculation' => $calculation,
                'assets_breakdown' => $assetsBreakdown,
                'liabilities_breakdown' => $liabilitiesBreakdown,
                'data_sharing_enabled' => $dataSharingEnabled, // Add to top level for frontend
            ];

            // Add formatted data for easy frontend consumption
            $response['iht_summary'] = [
                'current' => [
                    'net_estate' => $calculation['total_net_estate'],
                    'nrb_available' => $calculation['nrb_available'],
                    'nrb_message' => $calculation['nrb_message'],
                    'rnrb_available' => $calculation['rnrb_available'],
                    'rnrb_status' => $calculation['rnrb_status'],
                    'rnrb_message' => $calculation['rnrb_message'],
                    'total_allowances' => $calculation['total_allowances'],
                    'taxable_estate' => $calculation['taxable_estate'],
                    'iht_liability' => $calculation['iht_liability'],
                    'effective_rate' => $calculation['effective_rate'],
                ],
                'projected' => [
                    'net_estate' => $calculation['projected_net_estate'],
                    'taxable_estate' => $calculation['projected_taxable_estate'],
                    'iht_liability' => $calculation['projected_iht_liability'],
                    'years_to_death' => $calculation['years_to_death'],
                    'estimated_age_at_death' => $calculation['estimated_age_at_death'],
                ],
                'is_married' => $calculation['is_married'],
                'data_sharing_enabled' => $calculation['data_sharing_enabled'],
            ];

            // Add will information for estate planning status display
            $will = Will::where('user_id', $user->id)->first();
            $response['will_info'] = [
                'has_will' => $will?->has_will ?? false,
                'last_updated' => $will?->will_last_updated?->toIso8601String(),
                'executor_name' => $will?->executor_name,
            ];

            return response()->json($response);
        } catch (\Exception $e) {
            \Log::error('IHT Calculation Error:', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred calculating IHT: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Format assets breakdown for response
     *
     * For married couples, projects to SECOND DEATH (whoever lives longer)
     * to match IHTCalculationService logic
     */
    private function formatAssetsBreakdown($userAssets, $spouseAssets = null, bool $includeSpouse = false, ?User $user = null, ?User $spouse = null): array
    {
        $estateGrowthRate = 0.047; // Must match IHTCalculationService (4.7%)

        // For married couples, calculate projection years to SECOND DEATH
        // This ensures breakdown subtotals match the service calculation
        if ($includeSpouse && $spouse && $spouse->date_of_birth && $spouse->gender) {
            // Calculate both life expectancies using actuarial tables
            $userYearsToProject = $this->calculateLifeExpectancyForProjection($user);
            $spouseYearsToProject = $this->calculateLifeExpectancyForProjection($spouse);

            // Use the LONGER life expectancy (second death)
            $yearsToProject = max($userYearsToProject, $spouseYearsToProject);
        } else {
            // Single person - use their own life expectancy or fallback
            $yearsToProject = $user && $user->date_of_birth
                ? $this->calculateLifeExpectancyForProjection($user)
                : 25;
        }

        $userAssetsForIHT = [
            'investment' => [],
            'property' => [],
            'cash' => [],
            'business' => [],
            'chattel' => [],
        ];
        $userAssetsTotal = 0;
        $userAssetsProjectedTotal = 0;

        // Process user assets
        foreach ($userAssets as $asset) {
            if ($asset->is_iht_exempt || $asset->current_value <= 0) {
                continue;
            }

            if (in_array($asset->asset_type, ['investment', 'property', 'cash', 'business', 'chattel'])) {
                $isJoint = ($asset->ownership_type ?? 'individual') === 'joint';
                // Database already stores the user's share - do NOT divide by 2
                $displayValue = $asset->current_value;
                $projectedValue = $displayValue * pow(1 + $estateGrowthRate, $yearsToProject);

                $userAssetsForIHT[$asset->asset_type][] = [
                    'name' => $asset->asset_name,
                    'value' => $displayValue,
                    'projected_value' => $projectedValue,
                    'is_joint' => $isJoint,
                    'ownership_type' => $asset->ownership_type,
                ];
                $userAssetsTotal += $displayValue;
                $userAssetsProjectedTotal += $projectedValue;
            }
        }

        $userName = $user ? (trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: $user->name) : 'User';

        $breakdown = [
            'user' => [
                'name' => $userName,
                'assets' => $userAssetsForIHT,
                'total' => $userAssetsTotal,
                'projected_total' => $userAssetsProjectedTotal,
            ],
            'spouse' => null,
        ];

        // Add spouse assets if applicable
        if ($includeSpouse && $spouseAssets && $spouseAssets->isNotEmpty()) {
            // Use SAME projection years as user (second death scenario)
            // This ensures both spouses' assets project to the same future point

            $spouseAssetsForIHT = [
                'investment' => [],
                'property' => [],
                'cash' => [],
                'business' => [],
                'chattel' => [],
            ];
            $spouseAssetsTotal = 0;
            $spouseAssetsProjectedTotal = 0;

            foreach ($spouseAssets as $asset) {
                if ($asset->is_iht_exempt || $asset->current_value <= 0) {
                    continue;
                }

                if (in_array($asset->asset_type, ['investment', 'property', 'cash', 'business', 'chattel'])) {
                    $isJoint = ($asset->ownership_type ?? 'individual') === 'joint';
                    // Database already stores the spouse's share - do NOT divide by 2
                    $displayValue = $asset->current_value;
                    $projectedValue = $displayValue * pow(1 + $estateGrowthRate, $yearsToProject);

                    $spouseAssetsForIHT[$asset->asset_type][] = [
                        'name' => $asset->asset_name,
                        'value' => $displayValue,
                        'projected_value' => $projectedValue,
                        'is_joint' => $isJoint,
                        'ownership_type' => $asset->ownership_type,
                    ];
                    $spouseAssetsTotal += $displayValue;
                    $spouseAssetsProjectedTotal += $projectedValue;
                }
            }

            $spouseName = $spouse ? (trim(($spouse->first_name ?? '').' '.($spouse->last_name ?? '')) ?: $spouse->name) : 'Spouse';

            $breakdown['spouse'] = [
                'name' => $spouseName,
                'assets' => $spouseAssetsForIHT,
                'total' => $spouseAssetsTotal,
                'projected_total' => $spouseAssetsProjectedTotal,
            ];
        }

        return $breakdown;
    }

    /**
     * Format liabilities breakdown for response
     *
     * IMPORTANT: Mortgages are assumed to be paid off by age 70
     */
    private function formatLiabilitiesBreakdown(User $user, ?User $spouse = null, bool $includeSpouse = false): array
    {
        // Get mortgages where user is primary owner OR joint owner
        $userMortgages = Mortgage::where(function ($query) use ($user) {
            $query->where('user_id', $user->id)
                  ->orWhere('joint_owner_id', $user->id);
        })->with('property')->get();
        $userLiabilities = Liability::where('user_id', $user->id)->get();

        $userMortgagesFormatted = [];
        $userLiabilitiesFormatted = [];
        $userMortgagesTotal = 0;
        $userLiabilitiesTotal = 0;
        $userMortgagesProjectedTotal = 0;
        $userLiabilitiesProjectedTotal = 0;

        // Calculate user age at death for mortgage projections
        $userAge = $user->date_of_birth ? \Carbon\Carbon::parse($user->date_of_birth)->age : 50;
        $yearsToProjectedDeath = max(0, 85 - $userAge); // Assume life expectancy of 85
        $userAgeAtDeath = $userAge + $yearsToProjectedDeath;

        foreach ($userMortgages as $mortgage) {
            if ($mortgage->outstanding_balance > 0) {
                $propertyName = $mortgage->property ? $mortgage->property->address_line_1 : 'Unknown Property';
                $isJoint = ($mortgage->ownership_type ?? 'individual') === 'joint';

                // Calculate user's share of the mortgage using trait method
                $userShare = $this->calculateUserMortgageShare($mortgage, $user->id);

                // Skip if user has no share (shouldn't happen but safety check)
                if ($userShare <= 0) {
                    continue;
                }

                // Mortgages are assumed to be paid off by age 70
                $projectedBalance = ($userAgeAtDeath >= 70) ? 0 : $userShare;

                $userMortgagesFormatted[] = [
                    'property_address' => $propertyName,
                    'outstanding_balance' => $userShare,
                    'full_balance' => (float) $mortgage->outstanding_balance,
                    'projected_balance' => $projectedBalance,
                    'mortgage_type' => $mortgage->mortgage_type ?? 'repayment',
                    'is_joint' => $isJoint,
                    'ownership_percentage' => $isJoint ? ($mortgage->user_id === $user->id ? $mortgage->ownership_percentage : (100 - $mortgage->ownership_percentage)) : 100,
                ];
                $userMortgagesTotal += $userShare;
                $userMortgagesProjectedTotal += $projectedBalance;
            }
        }

        foreach ($userLiabilities as $liability) {
            if ($liability->current_balance > 0) {
                // Other liabilities persist at current value
                $userLiabilitiesFormatted[] = [
                    'type' => ucwords(str_replace('_', ' ', $liability->liability_type)),
                    'institution' => $liability->liability_name ?? ucwords(str_replace('_', ' ', $liability->liability_type)),
                    'current_balance' => $liability->current_balance,
                    'projected_balance' => $liability->current_balance,
                    'is_joint' => ($liability->ownership_type ?? 'individual') === 'joint',
                ];
                $userLiabilitiesTotal += $liability->current_balance;
                $userLiabilitiesProjectedTotal += $liability->current_balance;
            }
        }

        $breakdown = [
            'user' => [
                'name' => trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: $user->name,
                'liabilities' => [
                    'mortgages' => $userMortgagesFormatted,
                    'other_liabilities' => $userLiabilitiesFormatted,
                ],
                'mortgages_total' => $userMortgagesTotal,
                'liabilities_total' => $userLiabilitiesTotal,
                'total' => $userMortgagesTotal + $userLiabilitiesTotal,
                'projected_total' => $userMortgagesProjectedTotal + $userLiabilitiesProjectedTotal,
            ],
            'spouse' => null,
        ];

        if ($includeSpouse && $spouse) {
            // Get mortgages where spouse is primary owner OR joint owner
            $spouseMortgages = Mortgage::where(function ($query) use ($spouse) {
                $query->where('user_id', $spouse->id)
                      ->orWhere('joint_owner_id', $spouse->id);
            })->with('property')->get();
            $spouseLiabilities = Liability::where('user_id', $spouse->id)->get();

            $spouseMortgagesFormatted = [];
            $spouseLiabilitiesFormatted = [];
            $spouseMortgagesTotal = 0;
            $spouseLiabilitiesTotal = 0;
            $spouseMortgagesProjectedTotal = 0;
            $spouseLiabilitiesProjectedTotal = 0;

            // Calculate spouse age at death for mortgage projections
            $spouseAge = $spouse->date_of_birth ? \Carbon\Carbon::parse($spouse->date_of_birth)->age : 50;
            $spouseYearsToProjectedDeath = max(0, 85 - $spouseAge);
            $spouseAgeAtDeath = $spouseAge + $spouseYearsToProjectedDeath;

            foreach ($spouseMortgages as $mortgage) {
                if ($mortgage->outstanding_balance > 0) {
                    $propertyName = $mortgage->property ? $mortgage->property->address_line_1 : 'Unknown Property';
                    $isJoint = ($mortgage->ownership_type ?? 'individual') === 'joint';

                    // Calculate spouse's share of the mortgage using trait method
                    $spouseShare = $this->calculateUserMortgageShare($mortgage, $spouse->id);

                    // Skip if spouse has no share (shouldn't happen but safety check)
                    if ($spouseShare <= 0) {
                        continue;
                    }

                    // Mortgages are assumed to be paid off by age 70
                    $projectedBalance = ($spouseAgeAtDeath >= 70) ? 0 : $spouseShare;

                    $spouseMortgagesFormatted[] = [
                        'property_address' => $propertyName,
                        'outstanding_balance' => $spouseShare,
                        'full_balance' => (float) $mortgage->outstanding_balance,
                        'projected_balance' => $projectedBalance,
                        'mortgage_type' => $mortgage->mortgage_type ?? 'repayment',
                        'is_joint' => $isJoint,
                        'ownership_percentage' => $isJoint ? ($mortgage->user_id === $spouse->id ? $mortgage->ownership_percentage : (100 - $mortgage->ownership_percentage)) : 100,
                    ];
                    $spouseMortgagesTotal += $spouseShare;
                    $spouseMortgagesProjectedTotal += $projectedBalance;
                }
            }

            foreach ($spouseLiabilities as $liability) {
                if ($liability->current_balance > 0) {
                    // Other liabilities persist at current value
                    $spouseLiabilitiesFormatted[] = [
                        'type' => ucwords(str_replace('_', ' ', $liability->liability_type)),
                        'institution' => $liability->liability_name ?? ucwords(str_replace('_', ' ', $liability->liability_type)),
                        'current_balance' => $liability->current_balance,
                        'projected_balance' => $liability->current_balance,
                        'is_joint' => ($liability->ownership_type ?? 'individual') === 'joint',
                    ];
                    $spouseLiabilitiesTotal += $liability->current_balance;
                    $spouseLiabilitiesProjectedTotal += $liability->current_balance;
                }
            }

            $breakdown['spouse'] = [
                'name' => trim(($spouse->first_name ?? '').' '.($spouse->last_name ?? '')) ?: $spouse->name,
                'liabilities' => [
                    'mortgages' => $spouseMortgagesFormatted,
                    'other_liabilities' => $spouseLiabilitiesFormatted,
                ],
                'mortgages_total' => $spouseMortgagesTotal,
                'liabilities_total' => $spouseLiabilitiesTotal,
                'total' => $spouseMortgagesTotal + $spouseLiabilitiesTotal,
                'projected_total' => $spouseMortgagesProjectedTotal + $spouseLiabilitiesProjectedTotal,
            ];
        }

        return $breakdown;
    }

    /**
     * Get existing life cover for both spouses
     */
    private function getExistingLifeCover(User $user, ?User $spouse): array
    {
        $userLifeCover = LifeInsurancePolicy::where('user_id', $user->id)
            ->where('in_trust', true)
            ->sum('sum_assured');

        $spouseLifeCover = 0;
        if ($spouse) {
            $spouseLifeCover = LifeInsurancePolicy::where('user_id', $spouse->id)
                ->where('in_trust', true)
                ->sum('sum_assured');
        }

        return [
            'user' => $userLifeCover,
            'spouse' => $spouseLifeCover,
            'total' => $userLifeCover + $spouseLifeCover,
        ];
    }

    /**
     * Store or update IHT profile for the authenticated user
     */
    public function storeOrUpdateIHTProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'marital_status' => ['nullable', 'string', 'in:single,married,widowed,divorced'],
            'has_spouse' => ['nullable', 'boolean'],
            'own_home' => ['nullable', 'boolean'],
            'home_value' => ['nullable', 'numeric', 'min:0'],
            'nrb_transferred_from_spouse' => ['nullable', 'numeric', 'min:0'],
            'charitable_giving_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $profile = \App\Models\Estate\IHTProfile::updateOrCreate(
            ['user_id' => $user->id],
            $validated
        );

        // Invalidate cache as profile has changed
        $this->ihtCalculationService->invalidateCache($user);

        return response()->json([
            'success' => true,
            'message' => 'IHT profile updated successfully',
            'data' => $profile,
        ]);
    }

    /**
     * Invalidate IHT calculation cache
     */
    public function invalidateCache(Request $request): JsonResponse
    {
        $user = $request->user();

        $this->ihtCalculationService->invalidateCache($user);

        return response()->json([
            'success' => true,
            'message' => 'IHT calculation cache cleared',
        ]);
    }

    /**
     * Calculate life expectancy for projection using actuarial tables
     * Matches logic in IHTCalculationService
     */
    private function calculateLifeExpectancyForProjection(User $user): int
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
     * DEPRECATED: Backward compatibility alias
     * This method now just calls the unified calculateIHT()
     *
     * @deprecated Use calculateIHT() instead
     */
    public function calculateSecondDeathIHTPlanning(Request $request): JsonResponse
    {
        return $this->calculateIHT($request);
    }
}
