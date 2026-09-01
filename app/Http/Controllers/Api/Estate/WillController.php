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
use App\Services\Estate\WillDocumentService;
use App\Services\Trust\IHTPeriodicChargeCalculator;
use App\Support\HouseholdPooling;
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
            // W-0508 — a civil partnership is spousal for every Inheritance Tax
            // purpose (IHTA 1984 s18 as extended by the Civil Partnership Act
            // 2004). Reading `['married']` alone showed a civil partner a
            // single-person will position.
            $isMarried = HouseholdPooling::hasSpousalStatus($user) && $user->spouse_id !== null;
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
     * Record what kind of beneficiary this gift names, where the caller has not said.
     *
     * W-0394. `beneficiary_type` reached neither request class, so `validated()`
     * dropped it and every bequest this controller wrote took the schema default
     * `individual` — both of the peak_earners household's charitable legacies
     * among them. The classification is not invented here: it is
     * Bequest::inferBeneficiaryType(), the same judgement Bequest::isCharitable()
     * reaches on read, so the stored row and the derived answer cannot disagree
     * (Rule 20).
     *
     * An explicit type from the caller always wins — this only fills a silence,
     * and only when the name is being written, so an unrelated update to an
     * amount or a condition never reclassifies a beneficiary behind the user's
     * back.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function classifyBeneficiary(array $validated): array
    {
        if (isset($validated['beneficiary_type'])) {
            return $validated;
        }

        $name = trim((string) ($validated['beneficiary_name'] ?? ''));

        if ($name === '') {
            return $validated;
        }

        $validated['beneficiary_type'] = Bequest::inferBeneficiaryType($name);

        return $validated;
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
                'residuary_note' => WillDocumentService::BEQUESTS_EXCLUDE_RESIDUARY_NOTE,
            ]);
        }

        $bequests = Bequest::where('will_id', $will->id)->orderBy('priority_order')->get();

        return response()->json([
            'success' => true,
            'data' => $bequests,
            // W-0398. This table holds SPECIFIC gifts only — the residuary is
            // deliberately document-only, for the reason at
            // `WillDocumentService::BEQUESTS_EXCLUDE_RESIDUARY_NOTE`. Without saying so,
            // a count of these rows reads as the whole of the will, and a household
            // whose children inherit the residue reads as though they are unprovided
            // for. Served here so web and `/m` say the same thing from one source.
            'residuary_note' => WillDocumentService::BEQUESTS_EXCLUDE_RESIDUARY_NOTE,
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
        $validated = $this->classifyBeneficiary($validated);

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

        $bequest->update($this->classifyBeneficiary($validated));

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

        // The house convention for a delete is 200 with a success body (see
        // SavingsController::destroyAccount, PropertyController::destroy and
        // app/Http/CLAUDE.md). This returned noContent() against the declared
        // : JsonResponse type, so every delete removed the row and THEN threw a
        // TypeError — the user was shown an error for an action that had already
        // succeeded, and would reasonably retry (W-0041, second instance).
        return response()->json([
            'success' => true,
            'message' => 'Bequest deleted successfully',
        ]);
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
