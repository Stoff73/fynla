<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Estate;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\Estate\Will;
use App\Models\LifeInsurancePolicy;
use App\Models\User;
use App\Services\Estate\EstateAssetAggregatorService;
use App\Services\Estate\IHTCalculationService;
use App\Services\Estate\IHTFormattingService;
use App\Services\TaxConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IHTController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly IHTCalculationService $ihtCalculationService,
        private readonly EstateAssetAggregatorService $assetAggregator,
        private readonly TaxConfigService $taxConfig,
        private readonly IHTFormattingService $formattingService
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

            $assetsBreakdown = $this->formattingService->formatAssetsBreakdown(
                $userAssets,
                $spouseAssets,
                $dataSharingEnabled,
                $user,
                $spouse,
                $calculation
            );

            $liabilitiesBreakdown = $this->formattingService->formatLiabilitiesBreakdown(
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

            // Let the service's projected_taxable_estate and projected_iht_liability stand
            // (they account for RNRB taper and charitable rate correctly)

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
                    'gross_assets' => $calculation['total_gross_assets'],
                    'liabilities' => $calculation['total_liabilities'],
                    'nrb_available' => $calculation['nrb_available'],
                    'nrb_individual' => $calculation['nrb_individual'],
                    'nrb_transferred' => $calculation['nrb_transferred'],
                    'nrb_message' => $calculation['nrb_message'],
                    'rnrb_available' => $calculation['rnrb_available'],
                    'rnrb_individual' => $calculation['rnrb_individual'],
                    'rnrb_transferred' => $calculation['rnrb_transferred'],
                    'rnrb_status' => $calculation['rnrb_status'],
                    'rnrb_message' => $calculation['rnrb_message'],
                    'total_allowances' => $calculation['total_allowances'],
                    'taxable_estate' => $calculation['taxable_estate'],
                    'iht_liability' => $calculation['iht_liability'],
                    'effective_rate' => $calculation['effective_rate'],
                ],
                'projected' => [
                    'net_estate' => $calculation['projected_net_estate'],
                    'gross_assets' => $calculation['projected_gross_assets'],
                    'liabilities' => $calculation['projected_liabilities'],
                    'taxable_estate' => $calculation['projected_taxable_estate'],
                    'iht_liability' => $calculation['projected_iht_liability'],
                    'years_to_death' => $calculation['years_to_death'],
                    'estimated_age_at_death' => $calculation['estimated_age_at_death'],
                    'retirement_age' => $calculation['retirement_age'] ?? null,
                    // Asset-specific projections (new methodology)
                    'cash' => $calculation['projected_cash'] ?? null,
                    'investments' => $calculation['projected_investments'] ?? null,
                    'properties' => $calculation['projected_properties'] ?? null,
                ],
                'is_married' => $calculation['is_married'],
                'is_widowed' => $calculation['is_widowed'] ?? false,
                'data_sharing_enabled' => $calculation['data_sharing_enabled'],
            ];

            // Add will information for estate planning status display
            $will = Will::where('user_id', $user->id)->first();
            $response['will_info'] = [
                'has_will' => $will?->has_will ?? false,
                'will_answered' => $will !== null,
                'last_updated' => $will?->will_last_updated?->toIso8601String(),
                'executor_name' => $will?->executor_name,
            ];

            // Add cash projection breakdown for transparency
            $response['cash_projection_breakdown'] = $this->formattingService->generateCashProjectionBreakdown(
                $user,
                $spouse,
                $dataSharingEnabled,
                $calculation
            );

            return response()->json($response);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'IHT calculation');
        }
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
}
