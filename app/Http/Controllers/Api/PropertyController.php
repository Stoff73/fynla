<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePropertyRequest;
use App\Http\Requests\UpdatePropertyRequest;
use App\Http\Resources\PropertyResource;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\JointAccountLog;
use App\Models\Property;
use App\Models\User;
use App\Services\Property\MortgageService;
use App\Services\Property\PropertyService;
use App\Services\Property\PropertyTaxService;
use App\Services\Stores\Exceptions\StoreValidationException;
use App\Services\Stores\Exceptions\TierLimitExceededException;
use App\Services\Stores\IngestSource;
use App\Services\Stores\MortgageStore;
use App\Services\Stores\Normalisers\PropertyNormaliser;
use App\Services\Stores\PropertyStore;
use App\Traits\CalculatesOwnershipShare;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Property Controller
 *
 * Single-Record Architecture:
 * - ONE database record stores the FULL property value in current_value
 * - user_id = primary owner (can edit/delete)
 * - joint_owner_id = secondary owner (view access, sees in their dashboard)
 * - ownership_percentage = primary owner's share (default 50 for joint)
 * - Query pattern: where('user_id', $id)->orWhere('joint_owner_id', $id)
 */
class PropertyController extends Controller
{
    use CalculatesOwnershipShare;
    use SanitizedErrorResponse;

    public function __construct(
        private readonly PropertyService $propertyService,
        private readonly PropertyTaxService $propertyTaxService,
        private readonly MortgageService $mortgageService,
        private readonly PropertyStore $propertyStore,
        private readonly PropertyNormaliser $propertyNormaliser,
        private readonly MortgageStore $mortgageStore,
    ) {}

    /**
     * Get all properties for the authenticated user
     *
     * Returns properties where user is either primary owner (user_id) or
     * joint owner (joint_owner_id). Each property includes user_share calculated
     * from the full value and ownership percentage.
     *
     * GET /api/properties
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Single-record pattern: Get properties where user is owner OR joint_owner
        // PR 5 will route this read through PropertyStore::forUser.
        $properties = Property::forUserOrJoint($user->id)
            ->with(['mortgages', 'user', 'jointOwner'])
            ->orderBy('property_type')
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();

        // Add calculated fields for each property
        $properties = $properties->map(function ($property) use ($user) {
            $propertyData = (new PropertyResource($property))->toArray(request());

            // Calculate user's share from full value
            $propertyData['user_share'] = $this->calculateUserShare($property, $user->id);
            $propertyData['full_value'] = (float) $property->current_value;
            $propertyData['is_primary_owner'] = $this->isPrimaryOwner($property, $user->id);
            $propertyData['is_shared'] = $this->isSharedOwnership($property);

            // Add owner names for joint/TiC properties
            $owner = $property->user;
            $jointOwner = $property->jointOwner;
            $propertyData['owner_name'] = $owner ? trim(($owner->first_name ?? '').' '.($owner->surname ?? '')) : null;
            $propertyData['joint_owner_name'] = $jointOwner ? trim(($jointOwner->first_name ?? '').' '.($jointOwner->surname ?? '')) : ($property->joint_owner_name ?? null);

            // Calculate user's mortgage share if mortgages exist
            if ($property->mortgages && $property->mortgages->count() > 0) {
                $mortgage = $property->mortgages->first();
                $propertyData['mortgage_user_share'] = $this->calculateUserMortgageShare($mortgage, $user->id);
                $propertyData['mortgage_full_balance'] = (float) $mortgage->outstanding_balance;
            }

            return $propertyData;
        });

        return response()->json([
            'success' => true,
            'data' => [
                'properties' => $properties,
            ],
        ]);
    }

    /**
     * Store a new property
     *
     * SP1 Pass 4 PR 2: Property create is routed through PropertyStore::create.
     * The mortgage_* fields below remain a direct Mortgage create via MortgageService —
     * Pass 5 will route those through MortgageStore.
     *
     * POST /api/properties
     */
    public function store(StorePropertyRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        // Resolve controller-level defaults before handing off to the normaliser.
        // These are form-layer concerns (household_id from session, valuation_date default,
        // address shorthand, postcode NOT NULL constraint, rental_income alias).
        $validated['household_id'] = $validated['household_id'] ?? $user->household_id;
        $validated['valuation_date'] = $validated['valuation_date'] ?? now();

        if (isset($validated['address']) && ! isset($validated['address_line_1'])) {
            $validated['address_line_1'] = $validated['address'];
        }

        if (! isset($validated['postcode']) || $validated['postcode'] === null) {
            $validated['postcode'] = '';
        }

        if (isset($validated['rental_income']) && ! isset($validated['monthly_rental_income'])) {
            $validated['monthly_rental_income'] = $validated['rental_income'];
        }

        // Default country to United Kingdom if absent/null/empty — the form layer's
        // historical default. The DB has DEFAULT 'United Kingdom' too, but we set it
        // explicitly so the response payload reflects the canonical value.
        if (! isset($validated['country']) || in_array($validated['country'], [null, ''], true)) {
            $validated['country'] = 'United Kingdom';
        }

        // For joint/tenants_in_common, default to 50/50 split if user left ownership_percentage at 100.
        $ownershipType = $validated['ownership_type'] ?? 'individual';
        if (in_array($ownershipType, ['joint', 'tenants_in_common'], true) && ($validated['ownership_percentage'] ?? 100.00) == 100.00) {
            $validated['ownership_percentage'] = 50.00;
        }

        // Normalise and route through PropertyStore (SP1 Pass 4 PR 2).
        $canonical = $this->propertyNormaliser->fromForm($validated);

        try {
            $property = $this->propertyStore->create($canonical, $user, IngestSource::FORM);
        } catch (StoreValidationException $e) {
            return $this->validationErrorResponse('Validation failed', $e->errors);
        } catch (TierLimitExceededException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Property limit reached for your current plan.',
                'error' => [
                    'entity_key' => $e->entityKey,
                    'current_count' => $e->currentCount,
                    'hard_limit' => $e->hardLimit,
                ],
            ], 403);
        }

        // Pass 4 routes Property creates through PropertyStore. The mortgage_*
        // fields below remain a direct Mortgage create via MortgageService —
        // Pass 5 will route those through MortgageStore.
        $this->mortgageService->createFromPropertyData($property, $validated, $user);

        // Sync rental income to user table
        $this->syncUserRentalIncome($user);

        // Load mortgages relationship before returning
        $property->load('mortgages');

        // Add calculated fields to response
        $propertyData = (new PropertyResource($property))->toArray(request());
        $propertyData['user_share'] = $this->calculateUserShare($property, $user->id);
        $propertyData['full_value'] = (float) $property->current_value;
        $propertyData['is_primary_owner'] = true;
        $propertyData['is_shared'] = $this->isSharedOwnership($property);

        return response()->json([
            'success' => true,
            'message' => 'Property created successfully',
            'data' => ['property' => $propertyData],
        ], 201);
    }

    /**
     * Get a single property
     *
     * Returns property if user is primary owner OR joint owner.
     * Read path stays direct until PR 5 routes reads through PropertyStore::find.
     *
     * GET /api/properties/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        // Single-record pattern: Allow access if user is owner OR joint_owner.
        // PR 5 will route this through PropertyStore::find.
        $property = Property::where('id', $id)
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere('joint_owner_id', $user->id);
            })
            ->with(['mortgages', 'household', 'trust', 'user', 'jointOwner'])
            ->firstOrFail();

        $summary = $this->propertyService->getPropertySummary($property);
        $propertyData = (new PropertyResource($property))->toArray(request());

        // Merge property resource data with summary
        $propertyData = array_merge($propertyData, $summary);

        // Add user share and ownership context
        $propertyData['user_share'] = $this->calculateUserShare($property, $user->id);
        $propertyData['full_value'] = (float) $property->current_value;
        $propertyData['is_primary_owner'] = $this->isPrimaryOwner($property, $user->id);
        $propertyData['is_shared'] = $this->isSharedOwnership($property);

        // Add owner names for joint/TiC properties
        $owner = $property->user;
        $jointOwner = $property->jointOwner;
        $propertyData['owner_name'] = $owner ? trim(($owner->first_name ?? '').' '.($owner->surname ?? '')) : null;
        $propertyData['joint_owner_name'] = $jointOwner ? trim(($jointOwner->first_name ?? '').' '.($jointOwner->surname ?? '')) : ($property->joint_owner_name ?? null);

        // Calculate user's mortgage share if mortgages exist
        if ($property->mortgages && $property->mortgages->count() > 0) {
            $mortgage = $property->mortgages->first();
            $propertyData['mortgage_user_share'] = $this->calculateUserMortgageShare($mortgage, $user->id);
            $propertyData['mortgage_full_balance'] = (float) $mortgage->outstanding_balance;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'property' => $propertyData,
            ],
        ]);
    }

    /**
     * Update a property
     *
     * Only primary owner (user_id) can update.
     * SP1 Pass 4 PR 2: Property update is routed through PropertyStore::update.
     *
     * PUT /api/properties/{id}
     */
    public function update(UpdatePropertyRequest $request, int $id): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        // Resolve ownership defaults before passing to the normaliser.
        // PropertyStore::update fetches the existing record itself (owner-only guard).
        // We need the current state to apply the 50/50 default logic.
        $existingProperty = Property::where('id', $id)->where('user_id', $user->id)->firstOrFail();

        $ownershipType = $validated['ownership_type'] ?? $existingProperty->ownership_type;
        $jointOwnerId = $validated['joint_owner_id'] ?? $existingProperty->joint_owner_id;

        if (in_array($ownershipType, ['joint', 'tenants_in_common'], true) && $jointOwnerId) {
            if (! isset($validated['ownership_percentage'])) {
                $validated['ownership_percentage'] = 50.00;
            }
        } elseif ($ownershipType === 'individual') {
            $validated['ownership_percentage'] = 100.00;
            $validated['joint_owner_id'] = null;
        }

        // Log joint property update if applicable
        if ($this->isSharedOwnership($existingProperty) && $existingProperty->joint_owner_id) {
            $this->logJointPropertyUpdate($user, $existingProperty, $validated);
        }

        // Normalise and route through PropertyStore (SP1 Pass 4 PR 2).
        $canonical = $this->propertyNormaliser->fromForm($validated);

        try {
            $property = $this->propertyStore->update($id, $canonical, $user, IngestSource::FORM);
        } catch (StoreValidationException $e) {
            return $this->validationErrorResponse('Validation failed', $e->errors);
        }

        $property->load(['mortgages', 'household', 'trust']);

        // Sync rental income to user table
        $this->syncUserRentalIncome($user);

        $summary = $this->propertyService->getPropertySummary($property);
        $propertyData = (new PropertyResource($property))->toArray(request());

        // Merge property resource data with summary
        $propertyData = array_merge($propertyData, $summary);

        // Add calculated fields
        $propertyData['user_share'] = $this->calculateUserShare($property, $user->id);
        $propertyData['full_value'] = (float) $property->current_value;
        $propertyData['is_primary_owner'] = true;
        $propertyData['is_shared'] = $this->isSharedOwnership($property);

        return response()->json([
            'success' => true,
            'message' => 'Property updated successfully',
            'data' => [
                'property' => $propertyData,
            ],
        ]);
    }

    /**
     * Delete a property
     *
     * Only primary owner (user_id) can delete.
     * SP1 Pass 4 PR 2: Property delete is routed through PropertyStore::delete.
     *
     * DELETE /api/properties/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        // Soft-delete associated mortgages first (SQL CASCADE only fires on hard DELETE,
        // not soft-delete, so we must cascade manually). Each mortgage is routed
        // through MortgageStore::delete (SP1 Pass 5 PR 4) so audit + event semantics
        // mirror the per-record delete path.
        $property = Property::where('id', $id)->where('user_id', $user->id)->firstOrFail();

        // Atomic cascade: primary-only filter (joint owners are READ-ONLY in MortgageStore).
        // Wrapped in DB::transaction so the property never lingers if a mortgage delete
        // fails midway through the loop.
        \DB::transaction(function () use ($property, $user, $id) {
            $primaryMortgages = $this->mortgageStore->forProperty($property->id, $user)
                ->where('user_id', $user->id);
            foreach ($primaryMortgages as $mortgage) {
                $this->mortgageStore->delete($mortgage->id, $user, IngestSource::FORM);
            }

            // Route Property soft-delete through PropertyStore (SP1 Pass 4 PR 2).
            $this->propertyStore->delete($id, $user, 'user_requested');
        });

        // Sync rental income after deletion
        $this->syncUserRentalIncome($user);

        return response()->json([
            'success' => true,
            'message' => 'Property deleted successfully',
        ]);
    }

    /**
     * Calculate SDLT for a property purchase
     *
     * POST /api/properties/calculate-sdlt
     */
    public function calculateSDLT(Request $request): JsonResponse
    {
        $request->validate([
            'purchase_price' => 'required|numeric|min:0',
            'property_type' => 'required|in:main_residence,secondary_residence,buy_to_let',
            'is_first_home' => 'sometimes|boolean',
        ]);

        $sdlt = $this->propertyTaxService->calculateSDLT(
            $request->input('purchase_price'),
            $request->input('property_type'),
            $request->input('is_first_home', false)
        );

        return response()->json([
            'success' => true,
            'data' => $sdlt,
        ]);
    }

    /**
     * Calculate CGT for a property disposal
     *
     * POST /api/properties/{id}/calculate-cgt
     */
    public function calculateCGT(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'disposal_price' => 'required|numeric|min:0',
            'disposal_costs' => 'sometimes|numeric|min:0',
        ]);

        // Allow access if user is owner OR joint_owner.
        // PR 5 will route this through PropertyStore::find.
        $property = Property::where('id', $id)
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere('joint_owner_id', $user->id);
            })
            ->firstOrFail();

        $cgt = $this->propertyTaxService->calculateCGT(
            $property,
            $request->input('disposal_price'),
            $request->input('disposal_costs', 0),
            $user
        );

        return response()->json([
            'success' => true,
            'data' => $cgt,
        ]);
    }

    /**
     * Calculate rental income tax for a property
     *
     * POST /api/properties/{id}/rental-income-tax
     */
    public function calculateRentalIncomeTax(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        // Allow access if user is owner OR joint_owner.
        // PR 5 will route this through PropertyStore::find.
        $property = Property::where('id', $id)
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere('joint_owner_id', $user->id);
            })
            ->with('mortgages')
            ->firstOrFail();

        $rentalTax = $this->propertyTaxService->calculateRentalIncomeTax($property, $user);

        return response()->json([
            'success' => true,
            'data' => $rentalTax,
        ]);
    }

    /**
     * Log joint property update for audit trail
     */
    private function logJointPropertyUpdate(User $user, Property $property, array $validated): void
    {
        if (! isset($validated['current_value'])) {
            return;
        }

        $beforeValues = [
            'current_value' => [
                'full_value' => $property->current_value,
                'user_share' => $this->calculateUserShare($property, $user->id),
            ],
        ];

        $afterValues = [
            'current_value' => [
                'full_value' => $validated['current_value'],
                'user_share' => $validated['current_value'] * (($property->ownership_percentage ?? 100) / 100),
            ],
        ];

        JointAccountLog::logEdit(
            $user->id,
            $property->joint_owner_id,
            $property,
            [
                'before' => $beforeValues,
                'after' => $afterValues,
                'fields_changed' => ['current_value'],
            ],
            'update'
        );
    }

    /**
     * Sync rental income from properties to user table
     *
     * Single-record pattern: Apply ownership percentage when calculating
     * user's share of rental income.
     * PR 5 will route this read through PropertyStore::forUser.
     */
    private function syncUserRentalIncome(User $user): void
    {
        $properties = Property::forUserOrJoint($user->id)->get();

        $annualRentalIncome = $properties->sum(function ($property) use ($user) {
            $monthlyRental = $property->monthly_rental_income ?? 0;

            // Apply ownership percentage to get user's share
            $userShare = $this->calculateUserShare(
                (object) ['current_value' => $monthlyRental, 'user_id' => $property->user_id, 'joint_owner_id' => $property->joint_owner_id, 'ownership_type' => $property->ownership_type, 'ownership_percentage' => $property->ownership_percentage],
                $user->id
            );

            return $userShare * 12;
        });

        $user->update(['annual_rental_income' => $annualRentalIncome]);
    }
}
