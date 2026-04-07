<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Estate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Estate\CalculateIntestacyRequest;
use App\Http\Requests\Estate\StoreBequestRequest;
use App\Http\Requests\Estate\StoreWillRequest;
use App\Http\Requests\Estate\UpdateBequestRequest;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\Estate\Bequest;
use App\Models\Estate\Trust;
use App\Models\Estate\Will;
use App\Services\Cache\CacheInvalidationService;
use App\Services\Estate\IntestacyCalculator;
use App\Services\Trust\IHTPeriodicChargeCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WillController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private IHTPeriodicChargeCalculator $periodicChargeCalculator,
        private IntestacyCalculator $intestacyCalculator,
        private readonly CacheInvalidationService $cacheInvalidation
    ) {}

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

    // ============ WILL & BEQUEST CRUD ============

    /**
     * Get user's will
     */
    public function getWill(Request $request): JsonResponse
    {
        $user = $request->user();
        $will = Will::where('user_id', $user->id)->with('bequests')->first();

        // If no will exists, create default
        if (! $will) {
            $isMarried = in_array($user->marital_status, ['married']) && $user->spouse_id !== null;
            $will = Will::create([
                'user_id' => $user->id,
                'spouse_primary_beneficiary' => $isMarried,
                'spouse_bequest_percentage' => $isMarried ? 100.00 : 0.00,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $will,
        ]);
    }

    /**
     * Create or update will
     */
    public function storeOrUpdateWill(StoreWillRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $validated['user_id'] = $user->id;

        $will = Will::updateOrCreate(
            ['user_id' => $user->id],
            $validated
        );

        // Invalidate IHT cache
        $this->cacheInvalidation->invalidateForUser($user->id);

        return response()->json([
            'success' => true,
            'message' => 'Will saved successfully',
            'data' => $will->fresh()->load('bequests'),
        ]);
    }

    /**
     * Get all bequests for user's will
     */
    public function getBequests(Request $request): JsonResponse
    {
        $user = $request->user();
        $will = Will::where('user_id', $user->id)->first();

        if (! $will) {
            return response()->json([
                'success' => true,
                'data' => [],
            ]);
        }

        $bequests = Bequest::where('will_id', $will->id)->orderBy('priority_order')->get();

        return response()->json([
            'success' => true,
            'data' => $bequests,
        ]);
    }

    /**
     * Create a bequest
     */
    public function storeBequest(StoreBequestRequest $request): JsonResponse
    {
        $user = $request->user();

        // Get or create will first
        $will = Will::firstOrCreate(
            ['user_id' => $user->id],
            [
                'spouse_primary_beneficiary' => false,
                'spouse_bequest_percentage' => 0.00,
            ]
        );

        $validated = $request->validated();

        $validated['will_id'] = $will->id;
        $validated['user_id'] = $user->id;

        // Auto-set priority order if not provided
        if (! isset($validated['priority_order'])) {
            $maxPriority = Bequest::where('will_id', $will->id)->max('priority_order') ?? 0;
            $validated['priority_order'] = $maxPriority + 1;
        }

        $bequest = Bequest::create($validated);

        // Invalidate cache
        $this->cacheInvalidation->invalidateForUser($user->id);

        return response()->json([
            'success' => true,
            'message' => 'Bequest created successfully',
            'data' => $bequest,
        ], 201);
    }

    /**
     * Update a bequest
     */
    public function updateBequest(UpdateBequestRequest $request, int $id): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $bequest = Bequest::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $bequest->update($validated);

        // Invalidate cache
        $this->cacheInvalidation->invalidateForUser($user->id);

        return response()->json([
            'success' => true,
            'message' => 'Bequest updated successfully',
            'data' => $bequest->fresh(),
        ]);
    }

    /**
     * Delete a bequest
     */
    public function deleteBequest(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $bequest = Bequest::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $bequest->delete();

        // Invalidate cache
        $this->cacheInvalidation->invalidateForUser($user->id);

        return response()->noContent();
    }

    /**
     * Calculate IHT for surviving spouse scenario
     *
     * This endpoint calculates IHT as if the user is a surviving spouse,
     * projecting their estate to expected death date and including
     * transferred NRB from deceased spouse.
     */

    /**
     * Calculate intestacy distribution
     *
     * Returns how the user's estate would be distributed under UK intestacy rules
     * if they die without a valid will.
     */
    public function calculateIntestacy(CalculateIntestacyRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        // Use the estate value provided by the frontend (which calls calculate-iht first)
        $estateValue = $validated['estate_value'] ?? 0;

        try {
            $distribution = $this->intestacyCalculator->calculateDistribution($user->id, $estateValue);

            return response()->json([
                'success' => true,
                'data' => $distribution,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Calculating intestacy distribution');
        }
    }
}
