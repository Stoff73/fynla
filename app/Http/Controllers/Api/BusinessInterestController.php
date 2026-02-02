<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BusinessInterest\StoreBusinessInterestRequest;
use App\Http\Requests\BusinessInterest\UpdateBusinessInterestRequest;
use App\Models\BusinessInterest;
use App\Services\Business\BusinessInterestService;
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
        private BusinessInterestService $businessService
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
        $businesses = BusinessInterest::where('user_id', $user->id)
            ->orWhere('joint_owner_id', $user->id)
            ->orderBy('current_valuation', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        // Add calculated fields for each business
        $businesses = $businesses->map(function ($business) use ($user) {
            $data = $business->toArray();

            // Calculate user's share from full value
            $data['user_share'] = $this->businessService->calculateUserShare($business, $user->id);
            $data['full_value'] = (float) $business->current_valuation;
            $data['is_primary_owner'] = $this->isPrimaryOwner($business, $user->id);
            $data['is_shared'] = $this->isSharedOwnership($business);
            $data['business_type_label'] = $this->getBusinessTypeLabel($business->business_type);

            return $data;
        });

        return response()->json($businesses);
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

        // Add calculated fields to response
        $data = $business->toArray();
        $data['user_share'] = $this->businessService->calculateUserShare($business, $user->id);
        $data['full_value'] = (float) $business->current_valuation;
        $data['is_primary_owner'] = true;
        $data['is_shared'] = $this->isSharedOwnership($business);
        $data['business_type_label'] = $this->getBusinessTypeLabel($business->business_type);

        return response()->json($data, 201);
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

        // Add user share and ownership context
        $summary['user_share'] = $this->businessService->calculateUserShare($business, $user->id);
        $summary['full_value'] = (float) $business->current_valuation;
        $summary['is_primary_owner'] = $this->isPrimaryOwner($business, $user->id);
        $summary['is_shared'] = $this->isSharedOwnership($business);

        // Add flat fields for Vue component compatibility (matches index response)
        $summary['current_valuation'] = (float) $business->current_valuation;
        $summary['annual_revenue'] = (float) ($business->annual_revenue ?? 0);
        $summary['annual_profit'] = (float) ($business->annual_profit ?? 0);
        $summary['annual_dividend_income'] = (float) ($business->annual_dividend_income ?? 0);
        $summary['employee_count'] = $business->employee_count ?? 0;
        $summary['ownership_type'] = $business->ownership_type;
        $summary['ownership_percentage'] = (float) ($business->ownership_percentage ?? 100);
        $summary['trading_status'] = $business->trading_status ?? 'trading';
        $summary['vat_registered'] = $business->vat_registered ?? false;
        $summary['vat_number'] = $business->vat_number;
        $summary['utr_number'] = $business->utr_number;
        $summary['paye_reference'] = $business->paye_reference;
        $summary['tax_year_end'] = $business->tax_year_end?->format('Y-m-d');
        $summary['valuation_method'] = $business->valuation_method;
        $summary['bpr_eligible'] = $business->bpr_eligible ?? false;
        $summary['business_type_label'] = $this->getBusinessTypeLabel($business->business_type);

        return response()->json([
            'success' => true,
            'data' => [
                'business' => $summary,
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

        // Add calculated fields
        $summary['user_share'] = $this->businessService->calculateUserShare($business, $user->id);
        $summary['full_value'] = (float) $business->current_valuation;
        $summary['is_primary_owner'] = true;
        $summary['is_shared'] = $this->isSharedOwnership($business);

        // Add flat fields for Vue component compatibility (matches index response)
        $summary['current_valuation'] = (float) $business->current_valuation;
        $summary['annual_revenue'] = (float) ($business->annual_revenue ?? 0);
        $summary['annual_profit'] = (float) ($business->annual_profit ?? 0);
        $summary['annual_dividend_income'] = (float) ($business->annual_dividend_income ?? 0);
        $summary['employee_count'] = $business->employee_count ?? 0;
        $summary['ownership_type'] = $business->ownership_type;
        $summary['ownership_percentage'] = (float) ($business->ownership_percentage ?? 100);
        $summary['trading_status'] = $business->trading_status ?? 'trading';
        $summary['vat_registered'] = $business->vat_registered ?? false;
        $summary['vat_number'] = $business->vat_number;
        $summary['utr_number'] = $business->utr_number;
        $summary['paye_reference'] = $business->paye_reference;
        $summary['tax_year_end'] = $business->tax_year_end?->format('Y-m-d');
        $summary['valuation_method'] = $business->valuation_method;
        $summary['bpr_eligible'] = $business->bpr_eligible ?? false;
        $summary['business_type_label'] = $this->getBusinessTypeLabel($business->business_type);

        return response()->json([
            'success' => true,
            'message' => 'Business interest updated successfully',
            'data' => [
                'business' => $summary,
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

        $business->delete();

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
