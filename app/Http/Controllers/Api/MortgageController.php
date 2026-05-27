<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMortgageRequest;
use App\Http\Requests\UpdateMortgageRequest;
use App\Http\Resources\MortgageResource;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\JointAccountLog;
use App\Models\Mortgage;
use App\Models\Property;
use App\Models\User;
use App\Services\Property\MortgageService;
use App\Services\Stores\Exceptions\StoreValidationException;
use App\Services\Stores\IngestSource;
use App\Services\Stores\MortgageStore;
use App\Services\Stores\Normalisers\MortgageNormaliser;
use App\Traits\CalculatesOwnershipShare;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mortgage Controller
 *
 * Single-Record Architecture:
 * - ONE database record stores the FULL mortgage balance in outstanding_balance
 * - user_id = primary owner (can edit/delete)
 * - joint_owner_id = secondary owner (view access)
 * - ownership_percentage = primary owner's share (default 50 for joint)
 *
 * SP1 Pass 5 PR 2: store/update/destroy route through MortgageStore (FORM ingest source).
 */
class MortgageController extends Controller
{
    use CalculatesOwnershipShare;
    use SanitizedErrorResponse;

    public function __construct(
        private readonly MortgageService $mortgageService,
        private readonly MortgageStore $mortgageStore,
    ) {}

    /**
     * Get all mortgages for a property
     *
     * Allows access if user is owner OR joint_owner of the property.
     *
     * GET /api/properties/{propertyId}/mortgages
     */
    public function index(Request $request, int $propertyId): JsonResponse
    {
        $user = $request->user();

        // Single-record pattern: Allow access if user is owner OR joint_owner
        $property = Property::where('id', $propertyId)
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere('joint_owner_id', $user->id);
            })
            ->firstOrFail();

        $mortgages = Mortgage::where('property_id', $propertyId)
            ->orderBy('start_date', 'desc')
            ->get();

        // Add calculated fields for each mortgage
        $mortgages = $mortgages->map(function ($mortgage) use ($user) {
            return (new MortgageResource($mortgage))->additional([
                'user_share' => $this->calculateUserMortgageShare($mortgage, $user->id),
                'full_balance' => (float) $mortgage->outstanding_balance,
                'is_primary_owner' => $this->isPrimaryOwner($mortgage, $user->id),
            ]);
        });

        return response()->json([
            'success' => true,
            'data' => [
                'mortgages' => $mortgages,
                'count' => $mortgages->count(),
            ],
        ]);
    }

    /**
     * Store a new mortgage for a property
     *
     * SP1 Pass 5 PR 2: routed through MortgageStore::create (FORM ingest source).
     *
     * POST /api/properties/{propertyId}/mortgages
     */
    public function store(StoreMortgageRequest $request, int $propertyId): JsonResponse
    {
        $user = $request->user();

        // Only primary owner can add mortgages
        $property = Property::where('id', $propertyId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $validated = $request->validated();

        // Apply form-layer defaults before normalisation.
        $validated['lender_name'] = $validated['lender_name'] ?? config('mortgage.default_lender_name', 'To be completed');
        $validated['mortgage_type'] = $validated['mortgage_type'] ?? config('mortgage.default_mortgage_type', 'repayment');
        $validated['interest_rate'] = $validated['interest_rate'] ?? config('mortgage.default_interest_rate', 0.0000);
        $validated['rate_type'] = $validated['rate_type'] ?? config('mortgage.default_rate_type', 'fixed');
        $validated['start_date'] = $validated['start_date'] ?? now()->toDateString();

        if (! isset($validated['maturity_date'])) {
            $validated['maturity_date'] = now()->addYears(config('mortgage.default_term_years', 25))->toDateString();
        }

        if (! isset($validated['remaining_term_months'])) {
            $validated['remaining_term_months'] = config('mortgage.default_term_months', 300);
        }

        // Copy joint ownership from property if applicable
        if (in_array($property->ownership_type, ['joint', 'tenants_in_common']) && $property->joint_owner_id) {
            $validated['joint_owner_id'] = $property->joint_owner_id;
            $jointOwner = User::find($property->joint_owner_id);
            $validated['joint_owner_name'] = $jointOwner ? $jointOwner->name : null;
        }

        // Default country
        if (! isset($validated['country']) || $validated['country'] === null) {
            $validated['country'] = 'United Kingdom';
        }

        $canonical = MortgageNormaliser::fromForm(
            array_merge($validated, ['property_id' => $property->id]),
            $user
        );

        try {
            $mortgage = $this->mortgageStore->create($canonical, $user, IngestSource::FORM);
        } catch (StoreValidationException $e) {
            return $this->validationErrorResponse('Validation failed', $e->errors);
        }

        $mortgageResource = (new MortgageResource($mortgage->refresh()))->additional([
            'user_share' => $this->calculateUserMortgageShare($mortgage, $user->id),
            'full_balance' => (float) $mortgage->outstanding_balance,
            'is_primary_owner' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Mortgage added successfully',
            'data' => [
                'mortgage' => $mortgageResource,
            ],
        ], 201);
    }

    /**
     * Get a single mortgage
     *
     * Allows access if user is owner OR joint_owner.
     *
     * GET /api/mortgages/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        // Single-record pattern: Allow access if user is owner OR joint_owner
        $mortgage = Mortgage::where('id', $id)
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere('joint_owner_id', $user->id);
            })
            ->with('property')
            ->firstOrFail();

        $mortgageResource = (new MortgageResource($mortgage))->additional([
            'user_share' => $this->calculateUserMortgageShare($mortgage, $user->id),
            'full_balance' => (float) $mortgage->outstanding_balance,
            'is_primary_owner' => $this->isPrimaryOwner($mortgage, $user->id),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'mortgage' => $mortgageResource,
            ],
        ]);
    }

    /**
     * Update a mortgage
     *
     * Only primary owner (user_id) can update.
     * SP1 Pass 5 PR 2: routed through MortgageStore::update (FORM ingest source).
     *
     * PUT /api/mortgages/{id}
     * PUT /api/properties/{propertyId}/mortgages/{mortgageId}
     */
    public function update(UpdateMortgageRequest $request, ?int $propertyId = null, ?int $mortgageId = null): JsonResponse
    {
        $user = $request->user();

        // Handle both route patterns
        $id = $mortgageId ?? $propertyId;

        if (! $id) {
            return response()->json([
                'success' => false,
                'message' => 'Mortgage ID is required',
            ], 400);
        }

        // Authorisation check — only primary owner may update.
        // MortgageStore::update enforces this too via firstOrFail, but we surface a
        // cleaner 404 here before building the canonical payload.
        $mortgage = $this->mortgageStore->find($id, $user);
        if ($mortgage === null || $mortgage->user_id !== $user->id) {
            return $this->notFoundResponse('Mortgage');
        }

        $validated = $request->validated();

        // Log joint mortgage update before changes are applied.
        if ($this->isSharedOwnership($mortgage) && $mortgage->joint_owner_id && isset($validated['outstanding_balance'])) {
            $this->logJointMortgageUpdate($user, $mortgage, $validated);
        }

        $canonical = MortgageNormaliser::fromForm(
            array_merge($validated, ['property_id' => $mortgage->property_id]),
            $user
        );

        try {
            $fresh = $this->mortgageStore->update($id, $canonical, $user, IngestSource::FORM);
        } catch (StoreValidationException $e) {
            return $this->validationErrorResponse('Validation failed', $e->errors);
        }

        $mortgageResource = (new MortgageResource($fresh))->additional([
            'user_share' => $this->calculateUserMortgageShare($fresh, $user->id),
            'full_balance' => (float) $fresh->outstanding_balance,
            'is_primary_owner' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Mortgage updated successfully',
            'data' => [
                'mortgage' => $mortgageResource,
            ],
        ]);
    }

    /**
     * Delete a mortgage
     *
     * Only primary owner (user_id) can delete.
     * SP1 Pass 5 PR 2: routed through MortgageStore::delete (FORM ingest source).
     *
     * DELETE /api/mortgages/{id}
     * DELETE /api/properties/{propertyId}/mortgages/{mortgageId}
     */
    public function destroy(Request $request, ?int $propertyId = null, ?int $mortgageId = null): JsonResponse
    {
        $user = $request->user();

        // Handle both route patterns
        $id = $mortgageId ?? $propertyId;

        if ($id === null) {
            return response()->json([
                'success' => false,
                'message' => 'Mortgage ID is required',
            ], 400);
        }

        $mortgage = $this->mortgageStore->find($id, $user);
        if ($mortgage === null || $mortgage->user_id !== $user->id) {
            return $this->notFoundResponse('Mortgage');
        }

        $this->mortgageStore->delete($id, $user, IngestSource::FORM);

        return response()->json([
            'success' => true,
            'message' => 'Mortgage deleted successfully',
        ]);
    }

    /**
     * Generate amortization schedule for a mortgage
     *
     * GET /api/mortgages/{id}/amortization-schedule
     */
    public function amortizationSchedule(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        // Allow access if user is owner OR joint_owner
        $mortgage = Mortgage::where('id', $id)
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere('joint_owner_id', $user->id);
            })
            ->firstOrFail();

        $schedule = $this->mortgageService->generateAmortizationSchedule($mortgage);

        return response()->json([
            'success' => true,
            'data' => $schedule,
        ]);
    }

    /**
     * Calculate monthly payment for mortgage parameters
     *
     * POST /api/mortgages/calculate-payment
     */
    public function calculatePayment(Request $request): JsonResponse
    {
        $request->validate([
            'loan_amount' => 'required|numeric|min:0',
            'annual_interest_rate' => 'required|numeric|min:0|max:100',
            'term_months' => 'required|integer|min:1',
            'mortgage_type' => 'required|in:repayment,interest_only,mixed',
        ]);

        $monthlyPayment = $this->mortgageService->calculateMonthlyPayment(
            $request->input('loan_amount'),
            $request->input('annual_interest_rate'),
            $request->input('term_months'),
            $request->input('mortgage_type')
        );

        return response()->json([
            'success' => true,
            'data' => [
                'loan_amount' => $request->input('loan_amount'),
                'annual_interest_rate' => $request->input('annual_interest_rate'),
                'term_months' => $request->input('term_months'),
                'term_years' => round($request->input('term_months') / 12, 1),
                'mortgage_type' => $request->input('mortgage_type'),
                'monthly_payment' => $monthlyPayment,
                'annual_payment' => $monthlyPayment * 12,
                'total_repayment' => $monthlyPayment * $request->input('term_months'),
            ],
        ]);
    }

    /**
     * Log joint mortgage update for audit trail
     */
    private function logJointMortgageUpdate(User $user, Mortgage $mortgage, array $validated): void
    {
        $beforeValues = [
            'outstanding_balance' => [
                'full_balance' => $mortgage->outstanding_balance,
                'user_share' => $this->calculateUserMortgageShare($mortgage, $user->id),
            ],
        ];

        $afterValues = [
            'outstanding_balance' => [
                'full_balance' => $validated['outstanding_balance'],
                'user_share' => $validated['outstanding_balance'] * (($mortgage->ownership_percentage ?? 100) / 100),
            ],
        ];

        JointAccountLog::logEdit(
            $user->id,
            $mortgage->joint_owner_id,
            $mortgage,
            [
                'before' => $beforeValues,
                'after' => $afterValues,
                'fields_changed' => ['outstanding_balance'],
            ],
            'update'
        );
    }
}
