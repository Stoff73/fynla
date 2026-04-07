<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Estate;

use App\Http\Controllers\Controller;
use App\Http\Resources\Estate\TrustResource;
use App\Models\Estate\Asset;
use App\Models\Estate\Gift;
use App\Models\Estate\IHTProfile;
use App\Models\Estate\Liability;
use App\Models\Estate\Trust;
use App\Models\Estate\Will;
use App\Services\Cache\CacheInvalidationService;
use App\Services\Estate\IHTCalculationService;
use App\Services\Estate\TrustService;
use App\Services\TaxConfigService;
use App\Services\Trust\IHTPeriodicChargeCalculator;
use App\Services\Trust\TrustAssetAggregatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrustController extends Controller
{
    public function __construct(
        private readonly TrustService $trustService,
        private readonly TrustAssetAggregatorService $trustAssetAggregator,
        private readonly IHTPeriodicChargeCalculator $periodicChargeCalculator,
        private readonly IHTCalculationService $ihtCalculationService,
        private readonly TaxConfigService $taxConfig,
        private readonly CacheInvalidationService $cacheInvalidation
    ) {}

    public function getTrusts(Request $request): JsonResponse
    {
        $user = $request->user();
        $trusts = Trust::where('user_id', $user->id)->get();

        return response()->json([
            'success' => true,
            'data' => $trusts,
        ]);
    }

    /**
     * Create a new trust
     */
    public function createTrust(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'trust_name' => 'required|string|max:255',
            'trust_type' => 'required|in:bare,interest_in_possession,discretionary,accumulation_maintenance,life_insurance,discounted_gift,loan,mixed,settlor_interested',
            'trust_creation_date' => 'required|date',
            'initial_value' => 'required|numeric|min:0',
            'current_value' => 'required|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'retained_income_annual' => 'nullable|numeric|min:0',
            'loan_amount' => 'nullable|numeric|min:0',
            'loan_interest_bearing' => 'nullable|boolean',
            'loan_interest_rate' => 'nullable|numeric|min:0',
            'sum_assured' => 'nullable|numeric|min:0',
            'annual_premium' => 'nullable|numeric|min:0',
            'beneficiaries' => 'nullable|string',
            'trustees' => 'nullable|string',
            'settlor' => 'nullable|string|max:255',
            'purpose' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $validated['user_id'] = $request->user()->id;

        // Determine if it's a relevant property trust
        $validated['is_relevant_property_trust'] = in_array($validated['trust_type'], [
            'discretionary',
            'accumulation_maintenance',
        ]);

        $trust = Trust::create($validated);

        // Invalidate cache
        $this->cacheInvalidation->invalidateForUser($request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Trust created successfully',
            'data' => new TrustResource($trust),
        ], 201);
    }

    /**
     * Update an existing trust
     */
    public function updateTrust(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $trust = Trust::where('user_id', $user->id)->findOrFail($id);

        $validated = $request->validate([
            'trust_name' => 'sometimes|required|string|max:255',
            'trust_type' => 'sometimes|required|in:bare,interest_in_possession,discretionary,accumulation_maintenance,life_insurance,discounted_gift,loan,mixed,settlor_interested',
            'trust_creation_date' => 'sometimes|required|date',
            'initial_value' => 'sometimes|required|numeric|min:0',
            'current_value' => 'sometimes|required|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'retained_income_annual' => 'nullable|numeric|min:0',
            'loan_amount' => 'nullable|numeric|min:0',
            'loan_interest_bearing' => 'nullable|boolean',
            'loan_interest_rate' => 'nullable|numeric|min:0',
            'sum_assured' => 'nullable|numeric|min:0',
            'annual_premium' => 'nullable|numeric|min:0',
            'beneficiaries' => 'nullable|string',
            'trustees' => 'nullable|string',
            'settlor' => 'nullable|string|max:255',
            'purpose' => 'nullable|string',
            'notes' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $trust->update($validated);

        // Invalidate cache
        $this->cacheInvalidation->invalidateForUser($user->id);

        return response()->json([
            'success' => true,
            'message' => 'Trust updated successfully',
            'data' => new TrustResource($trust->fresh()),
        ]);
    }

    /**
     * Delete a trust
     */
    public function deleteTrust(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $trust = Trust::where('user_id', $user->id)->findOrFail($id);

        $trust->delete();

        // Invalidate cache
        $this->cacheInvalidation->invalidateForUser($user->id);

        return response()->json([
            'success' => true,
            'message' => 'Trust deleted successfully',
        ]);
    }

    /**
     * Get trust analysis and efficiency metrics
     */
    public function analyzeTrust(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $trust = Trust::where('user_id', $user->id)->findOrFail($id);

        $analysis = $this->trustService->analyzeTrustEfficiency($trust);

        return response()->json([
            'success' => true,
            'data' => $analysis,
        ]);
    }

    /**
     * Get trust recommendations based on user's estate
     */
    public function getTrustRecommendations(Request $request): JsonResponse
    {
        $user = $request->user();

        // Get estate value and IHT liability
        $assets = Asset::where('user_id', $user->id)->get();
        $liabilities = Liability::where('user_id', $user->id)->get();
        $gifts = Gift::where('user_id', $user->id)->get();
        $trusts = Trust::where('user_id', $user->id)->where('is_active', true)->get();
        $will = Will::where('user_id', $user->id)->first();
        $ihtProfile = IHTProfile::where('user_id', $user->id)->first();

        // Create default profile if missing
        if (! $ihtProfile) {
            // For married users, default to full spouse NRB (£325,000)
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

        // Use the simplified IHT calculation service
        $spouse = ($user->marital_status === 'married' && $user->spouse_id) ? \App\Models\User::find($user->spouse_id) : null;
        $dataSharingEnabled = $spouse && $user->hasAcceptedSpousePermission();

        $ihtCalculation = $this->ihtCalculationService->calculate($user, $spouse, $dataSharingEnabled);

        $circumstances = [
            'has_children' => $request->input('has_children', false),
            'needs_flexibility' => $request->input('needs_flexibility', false),
        ];

        $recommendations = $this->trustService->getTrustRecommendations(
            $ihtCalculation['total_gross_assets'], // gross estate value
            $ihtCalculation['iht_liability'],
            $circumstances
        );

        return response()->json([
            'success' => true,
            'data' => [
                'estate_value' => $ihtCalculation['total_gross_assets'],
                'iht_liability' => $ihtCalculation['iht_liability'],
                'recommendations' => $recommendations,
            ],
        ]);
    }

    /**
     * Calculate discounted gift trust discount estimate
     */
    public function calculateDiscountedGiftDiscount(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'age' => 'required|integer|min:18|max:100',
            'gift_value' => 'required|numeric|min:1',
            'annual_income' => 'required|numeric|min:0',
        ]);

        $estimate = $this->trustService->estimateDiscountedGiftDiscount(
            $validated['age'],
            $validated['gift_value'],
            $validated['annual_income']
        );

        return response()->json([
            'success' => true,
            'data' => $estimate,
        ]);
    }

    /**
     * Get all assets held in a specific trust
     */
    public function getTrustAssets(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $trust = Trust::where('user_id', $user->id)->findOrFail($id);

        $aggregation = $this->trustAssetAggregator->aggregateAssetsForTrust($trust);

        return response()->json([
            'success' => true,
            'data' => $aggregation,
        ]);
    }

    /**
     * Calculate IHT periodic charges for a trust
     */
    public function calculateTrustIHTImpact(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $trust = Trust::where('user_id', $user->id)->findOrFail($id);

        // Get aggregated asset value
        $aggregation = $this->trustAssetAggregator->aggregateAssetsForTrust($trust);

        // Update trust's total asset value
        $trust->update(['total_asset_value' => $aggregation['total_value']]);

        // Calculate periodic charge
        $periodicCharge = $this->periodicChargeCalculator->calculatePeriodicCharge($trust);

        // Calculate next tax return due date
        $taxReturn = $this->periodicChargeCalculator->calculateTaxReturnDueDates($trust);

        return response()->json([
            'success' => true,
            'data' => [
                'trust' => $trust->fresh(),
                'total_asset_value' => $aggregation['total_value'],
                'periodic_charge' => $periodicCharge,
                'tax_return' => $taxReturn,
                'is_relevant_property_trust' => $trust->isRelevantPropertyTrust(),
            ],
        ]);
    }

    /**
     * Get upcoming tax returns for all user's trusts
     */
    public function getUpcomingTaxReturns(Request $request): JsonResponse
    {
        $user = $request->user();
        $monthsAhead = $request->input('months_ahead', 12);

        // Get upcoming periodic charges
        $upcomingCharges = $this->periodicChargeCalculator->getUpcomingCharges($user->id, $monthsAhead);

        // Get all active trusts with tax return due dates
        $trusts = Trust::where('user_id', $user->id)
            ->where('is_active', true)
            ->get();

        $taxReturns = $trusts->map(function ($trust) {
            $taxReturn = $this->periodicChargeCalculator->calculateTaxReturnDueDates($trust);

            return [
                'trust_id' => $trust->id,
                'trust_name' => $trust->trust_name,
                'trust_type' => $trust->trust_type,
                'tax_year_end' => $taxReturn['tax_year_end'],
                'return_due_date' => $taxReturn['return_due_date'],
                'days_until_due' => $taxReturn['days_until_due'],
                'is_overdue' => $taxReturn['is_overdue'],
            ];
        })->sortBy('return_due_date');

        return response()->json([
            'success' => true,
            'data' => [
                'upcoming_periodic_charges' => $upcomingCharges,
                'tax_returns' => $taxReturns->values(),
            ],
        ]);
    }
}
