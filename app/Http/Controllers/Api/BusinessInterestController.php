<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BusinessInterest\StoreBusinessInterestRequest;
use App\Http\Requests\BusinessInterest\UpdateBusinessInterestRequest;
use App\Http\Resources\BusinessInterestResource;
use App\Models\BusinessInterest;
use App\Services\Business\BusinessInterestService;
use App\Services\NetWorth\NetWorthService;
use App\Traits\CalculatesOwnershipShare;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Business Interest Controller
 *
 * Single-Record Architecture:
 * - ONE database record stores the FULL business valuation in current_valuation
 * - user_id = primary owner (can edit/delete)
 * - joint_owner_id = secondary owner (view access, sees in their dashboard)
 * - ownership_percentage = primary owner's share (default 100 for sole)
 * - Query pattern: where('user_id', $id)->orWhere('joint_owner_id', $id)
 */
class BusinessInterestController extends Controller
{
    use CalculatesOwnershipShare;

    public function __construct(
        private readonly BusinessInterestService $businessService,
        private readonly NetWorthService $netWorthService
    ) {}

    /**
     * Get all business interests for the authenticated user.
     *
     * Returns businesses where user is either primary owner (user_id) or
     * joint owner (joint_owner_id). Each business includes user_share calculated
     * from the full value and ownership percentage.
     *
     * GET /api/business-interests
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Single-record pattern: Get businesses where user is owner OR joint_owner
        $businesses = BusinessInterest::forUserOrJoint($user->id)
            ->orderBy('current_valuation', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        // Transform with resource and add calculated fields for each business
        $transformed = $businesses->map(function ($business) use ($user, $request) {
            $resource = (new BusinessInterestResource($business))->toArray($request);

            // Add calculated fields for each business
            $resource['user_share'] = $this->businessService->calculateUserShare($business, $user->id);
            $resource['full_value'] = (float) $business->current_valuation;
            $resource['is_primary_owner'] = $this->isPrimaryOwner($business, $user->id);
            // For business interests, is_shared is true when ownership < 100% (partial shareholding)
            $resource['is_shared'] = ((float) ($business->ownership_percentage ?? 100)) < 100;
            $resource['business_type_label'] = $this->getBusinessTypeLabel($business->business_type);

            return $resource;
        });

        return response()->json($transformed);
    }

    /**
     * Store a new business interest.
     *
     * Single-record pattern: Store FULL value directly, no splitting.
     * Joint owner is linked via joint_owner_id field.
     *
     * POST /api/business-interests
     */
    public function store(StoreBusinessInterestRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        // Set defaults for required fields
        $validated['user_id'] = $user->id;
        $validated['household_id'] = $user->household_id;
        $validated['ownership_type'] = $validated['ownership_type'] ?? 'individual';
        $validated['ownership_percentage'] = $validated['ownership_percentage'] ?? 100.00;
        $validated['trading_status'] = $validated['trading_status'] ?? 'trading';
        $validated['country'] = $validated['country'] ?? 'United Kingdom';

        // For joint ownership, default to specified percentage or 50/50
        if ($validated['ownership_type'] === 'joint' && $validated['ownership_percentage'] == 100.00) {
            $validated['ownership_percentage'] = 50.00;
        }

        // Single-record pattern: Store FULL value directly (no splitting)
        $business = BusinessInterest::create($validated);

        // Transform with resource and add calculated fields
        $resource = (new BusinessInterestResource($business))->toArray($request);
        $resource['user_share'] = $this->businessService->calculateUserShare($business, $user->id);
        $resource['full_value'] = (float) $business->current_valuation;
        $resource['is_primary_owner'] = true;
        // For business interests, is_shared is true when ownership < 100% (partial shareholding)
        $resource['is_shared'] = ((float) ($business->ownership_percentage ?? 100)) < 100;
        $resource['business_type_label'] = $this->getBusinessTypeLabel($business->business_type);

        // Invalidate net worth cache
        $this->netWorthService->invalidateCache($user->id);
        if ($business->joint_owner_id) {
            $this->netWorthService->invalidateCache($business->joint_owner_id);
        }

        return response()->json($resource, 201);
    }

    /**
     * Get a single business interest.
     *
     * Returns business if user is primary owner OR joint owner.
     *
     * GET /api/business-interests/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        // Single-record pattern: Allow access if user is owner OR joint_owner
        $business = BusinessInterest::where('id', $id)
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere('joint_owner_id', $user->id);
            })
            ->with(['household', 'trust', 'jointOwner'])
            ->firstOrFail();

        $summary = $this->businessService->getBusinessSummary($business);

        // Start with resource data as base
        $resource = (new BusinessInterestResource($business))->toArray($request);

        // Merge summary data with resource data (summary takes precedence for calculated fields)
        $businessData = array_merge($resource, $summary);

        // Add user share and ownership context
        $businessData['user_share'] = $this->businessService->calculateUserShare($business, $user->id);
        $businessData['full_value'] = (float) $business->current_valuation;
        $businessData['is_primary_owner'] = $this->isPrimaryOwner($business, $user->id);
        // For business interests, is_shared is true when ownership < 100% (partial shareholding)
        $businessData['is_shared'] = ((float) ($business->ownership_percentage ?? 100)) < 100;

        // Add flat fields for Vue component compatibility (matches index response)
        $businessData['current_valuation'] = (float) $business->current_valuation;
        $businessData['annual_revenue'] = (float) ($business->annual_revenue ?? 0);
        $businessData['annual_profit'] = (float) ($business->annual_profit ?? 0);
        $businessData['annual_dividend_income'] = (float) ($business->annual_dividend_income ?? 0);
        $businessData['employee_count'] = $business->employee_count ?? 0;
        $businessData['ownership_type'] = $business->ownership_type;
        $businessData['ownership_percentage'] = (float) ($business->ownership_percentage ?? 100);
        $businessData['trading_status'] = $business->trading_status ?? 'trading';
        $businessData['vat_registered'] = $business->vat_registered ?? false;
        $businessData['vat_number'] = $business->vat_number;
        $businessData['utr_number'] = $business->utr_number;
        $businessData['paye_reference'] = $business->paye_reference;
        $businessData['tax_year_end'] = $business->tax_year_end?->format('Y-m-d');
        $businessData['valuation_method'] = $business->valuation_method;
        $businessData['bpr_eligible'] = $business->bpr_eligible ?? false;
        $businessData['business_type_label'] = $this->getBusinessTypeLabel($business->business_type);

        return response()->json([
            'success' => true,
            'data' => [
                'business' => $businessData,
            ],
        ]);
    }

    /**
     * Update a business interest.
     *
     * Only primary owner (user_id) can update.
     * Single-record pattern: Update the single record directly.
     *
     * PUT /api/business-interests/{id}
     */
    public function update(UpdateBusinessInterestRequest $request, int $id): JsonResponse
    {
        $user = $request->user();

        // Only primary owner can update
        $business = BusinessInterest::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $validated = $request->validated();

        // Single-record pattern: Handle ownership percentage when changing to/from joint
        $ownershipType = $validated['ownership_type'] ?? $business->ownership_type;
        $jointOwnerId = $validated['joint_owner_id'] ?? $business->joint_owner_id;

        if ($ownershipType === 'joint' && $jointOwnerId) {
            // Switching to joint or already joint - default to 50% if not specified
            if (! isset($validated['ownership_percentage'])) {
                $validated['ownership_percentage'] = 50.00;
            }
        } elseif ($ownershipType === 'individual') {
            // Switching to individual - reset to 100%
            $validated['ownership_percentage'] = 100.00;
            $validated['joint_owner_id'] = null;
        }

        // Single-record pattern: Update directly
        $business->update($validated);
        $business->load(['household', 'trust', 'jointOwner']);

        $summary = $this->businessService->getBusinessSummary($business);

        // Start with resource data as base
        $resource = (new BusinessInterestResource($business))->toArray($request);

        // Merge summary data with resource data (summary takes precedence for calculated fields)
        $businessData = array_merge($resource, $summary);

        // Add calculated fields
        $businessData['user_share'] = $this->businessService->calculateUserShare($business, $user->id);
        $businessData['full_value'] = (float) $business->current_valuation;
        $businessData['is_primary_owner'] = true;
        // For business interests, is_shared is true when ownership < 100% (partial shareholding)
        $businessData['is_shared'] = ((float) ($business->ownership_percentage ?? 100)) < 100;

        // Add flat fields for Vue component compatibility (matches index response)
        $businessData['current_valuation'] = (float) $business->current_valuation;
        $businessData['annual_revenue'] = (float) ($business->annual_revenue ?? 0);
        $businessData['annual_profit'] = (float) ($business->annual_profit ?? 0);
        $businessData['annual_dividend_income'] = (float) ($business->annual_dividend_income ?? 0);
        $businessData['employee_count'] = $business->employee_count ?? 0;
        $businessData['ownership_type'] = $business->ownership_type;
        $businessData['ownership_percentage'] = (float) ($business->ownership_percentage ?? 100);
        $businessData['trading_status'] = $business->trading_status ?? 'trading';
        $businessData['vat_registered'] = $business->vat_registered ?? false;
        $businessData['vat_number'] = $business->vat_number;
        $businessData['utr_number'] = $business->utr_number;
        $businessData['paye_reference'] = $business->paye_reference;
        $businessData['tax_year_end'] = $business->tax_year_end?->format('Y-m-d');
        $businessData['valuation_method'] = $business->valuation_method;
        $businessData['bpr_eligible'] = $business->bpr_eligible ?? false;
        $businessData['business_type_label'] = $this->getBusinessTypeLabel($business->business_type);

        // Invalidate net worth cache
        $this->netWorthService->invalidateCache($user->id);
        if ($business->joint_owner_id) {
            $this->netWorthService->invalidateCache($business->joint_owner_id);
        }

        return response()->json([
            'success' => true,
            'message' => 'Business interest updated successfully',
            'data' => [
                'business' => $businessData,
            ],
        ]);
    }

    /**
     * Delete a business interest.
     *
     * Only primary owner (user_id) can delete.
     * Single-record pattern: Delete the single record.
     *
     * DELETE /api/business-interests/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        // Only primary owner can delete
        $business = BusinessInterest::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        // Capture joint owner before delete
        $jointOwnerId = $business->joint_owner_id;

        $business->delete();

        // Invalidate net worth cache
        $this->netWorthService->invalidateCache($user->id);
        if ($jointOwnerId) {
            $this->netWorthService->invalidateCache($jointOwnerId);
        }

        return response()->json([
            'success' => true,
            'message' => 'Business interest deleted successfully',
        ]);
    }

    /**
     * Get tax deadlines for a business interest.
     *
     * Returns relevant tax deadlines based on business type and registration status.
     *
     * GET /api/business-interests/{id}/tax-deadlines
     */
    public function taxDeadlines(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        // Allow access if user is owner OR joint_owner
        $business = BusinessInterest::where('id', $id)
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere('joint_owner_id', $user->id);
            })
            ->firstOrFail();

        $deadlines = $this->businessService->getTaxDeadlines($business);

        return response()->json([
            'success' => true,
            'data' => [
                'business_name' => $business->business_name,
                'business_type' => $business->business_type,
                'business_type_label' => $this->getBusinessTypeLabel($business->business_type),
                'deadlines' => $deadlines,
            ],
        ]);
    }

    /**
     * Get exit/sale calculation for a business interest.
     *
     * Returns CGT calculation with BADR eligibility and post-tax proceeds.
     *
     * GET /api/business-interests/{id}/exit-calculation
     */
    public function exitCalculation(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        // Allow access if user is owner OR joint_owner
        $business = BusinessInterest::where('id', $id)
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere('joint_owner_id', $user->id);
            })
            ->firstOrFail();

        $exitScenario = $this->businessService->calculateExitScenario($business, $user);

        return response()->json([
            'success' => true,
            'data' => [
                'business_name' => $business->business_name,
                'business_type' => $business->business_type,
                'business_type_label' => $this->getBusinessTypeLabel($business->business_type),
                'exit_calculation' => $exitScenario,
            ],
        ]);
    }

    /**
     * Get label for business type.
     */
    private function getBusinessTypeLabel(string $type): string
    {
        return match ($type) {
            'sole_trader' => 'Sole Trader',
            'partnership' => 'Partnership',
            'limited_company' => 'Limited Company',
            'llp' => 'LLP',
            'other' => 'Other',
            default => ucfirst(str_replace('_', ' ', $type)),
        };
    }
}
