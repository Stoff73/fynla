<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateDomicileInfoRequest;
use App\Http\Requests\UpdateIncomeOccupationRequest;
use App\Http\Requests\UpdatePersonalInfoRequest;
use App\Http\Resources\UserResource;
use App\Http\Traits\SanitizedErrorResponse;
use App\Services\Cache\CacheInvalidationService;
use App\Services\UserProfile\UserProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserProfileController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly UserProfileService $userProfileService,
        private readonly CacheInvalidationService $cacheInvalidation
    ) {}

    /**
     * Get the authenticated user's complete profile
     *
     * GET /api/user/profile
     */
    public function getProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $profile = $this->userProfileService->getCompleteProfile($user);

        return response()->json([
            'success' => true,
            'data' => $profile,
        ]);
    }

    /**
     * Update personal information
     *
     * PUT /api/user/profile/personal
     */
    public function updatePersonalInfo(UpdatePersonalInfoRequest $request): JsonResponse
    {
        $user = $request->user();

        $updatedUser = $this->userProfileService->updatePersonalInfo(
            $user,
            $request->validated()
        );

        $this->cacheInvalidation->invalidateForUserAndSpouse($user->id, $user->spouse_id);

        return response()->json([
            'success' => true,
            'message' => 'Personal information updated successfully',
            'data' => [
                'user' => $updatedUser,
            ],
        ]);
    }

    /**
     * Update income and occupation information
     *
     * PUT /api/user/profile/income-occupation
     */
    public function updateIncomeOccupation(UpdateIncomeOccupationRequest $request): JsonResponse
    {
        $user = $request->user();

        $updatedUser = $this->userProfileService->updateIncomeOccupation(
            $user,
            $request->validated()
        );

        $this->cacheInvalidation->invalidateForUserAndSpouse($user->id, $user->spouse_id);

        return response()->json([
            'success' => true,
            'message' => 'Income and occupation information updated successfully',
            'data' => [
                'user' => $updatedUser,
            ],
        ]);
    }

    /**
     * Update expenditure information
     */
    public function updateExpenditure(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'monthly_expenditure' => 'nullable|numeric|min:0',
            'annual_expenditure' => 'nullable|numeric|min:0',
            'use_simple_entry' => 'nullable|boolean',
            'use_separate_expenditure' => 'nullable|boolean',
            'food_groceries' => 'nullable|numeric|min:0',
            'transport_fuel' => 'nullable|numeric|min:0',
            'healthcare_medical' => 'nullable|numeric|min:0',
            'insurance' => 'nullable|numeric|min:0',
            'mobile_phones' => 'nullable|numeric|min:0',
            'internet_tv' => 'nullable|numeric|min:0',
            'subscriptions' => 'nullable|numeric|min:0',
            'clothing_personal_care' => 'nullable|numeric|min:0',
            'entertainment_dining' => 'nullable|numeric|min:0',
            'holidays_travel' => 'nullable|numeric|min:0',
            'pets' => 'nullable|numeric|min:0',
            'childcare' => 'nullable|numeric|min:0',
            'school_fees' => 'nullable|numeric|min:0',
            'school_lunches' => 'nullable|numeric|min:0',
            'school_extras' => 'nullable|numeric|min:0',
            'university_fees' => 'nullable|numeric|min:0',
            'children_activities' => 'nullable|numeric|min:0',
            'gifts_charity' => 'nullable|numeric|min:0',
            'regular_savings' => 'nullable|numeric|min:0',
            'other_expenditure' => 'nullable|numeric|min:0',
            'retired_budget_overrides' => 'nullable|array',
            'widowed_budget_overrides' => 'nullable|array',
        ]);

        // Map frontend field names to database column names
        // expenditure_entry_mode: enum('simple', 'category')
        // expenditure_sharing_mode: enum('joint', 'separate')
        $updateData = $validated;
        if (isset($validated['use_simple_entry'])) {
            $updateData['expenditure_entry_mode'] = $validated['use_simple_entry'] ? 'simple' : 'category';
            unset($updateData['use_simple_entry']);
        }
        if (isset($validated['use_separate_expenditure'])) {
            $updateData['expenditure_sharing_mode'] = $validated['use_separate_expenditure'] ? 'separate' : 'joint';
            unset($updateData['use_separate_expenditure']);
        }

        // Ensure annual_expenditure is set when monthly_expenditure is provided
        if (isset($updateData['monthly_expenditure']) && ! isset($updateData['annual_expenditure'])) {
            $updateData['annual_expenditure'] = (float) $updateData['monthly_expenditure'] * 12;
        }

        $user->update($updateData);

        // Create/update expenditure profile with the total
        if ($validated['monthly_expenditure'] ?? null) {
            $monthly = $validated['monthly_expenditure'];

            \App\Models\ExpenditureProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'monthly_housing' => 0,
                    'monthly_food' => 0,
                    'monthly_utilities' => 0,
                    'monthly_transport' => 0,
                    'monthly_insurance' => 0,
                    'monthly_loans' => 0,
                    'monthly_discretionary' => 0,
                    'total_monthly_expenditure' => $monthly,
                ]
            );
        }

        $this->cacheInvalidation->invalidateForUserAndSpouse($user->id, $user->spouse_id);

        return response()->json([
            'success' => true,
            'message' => 'Expenditure information updated successfully',
            'data' => [
                'user' => new UserResource($user->fresh()),
            ],
        ]);
    }

    /**
     * Update domicile information
     *
     * PUT /api/user/profile/domicile
     */
    public function updateDomicileInfo(UpdateDomicileInfoRequest $request): JsonResponse
    {
        $user = $request->user();

        $updatedUser = $this->userProfileService->updateDomicileInfo(
            $user,
            $request->validated()
        );

        $this->cacheInvalidation->invalidateForUserAndSpouse($user->id, $user->spouse_id);

        return response()->json([
            'success' => true,
            'message' => 'Domicile information updated successfully',
            'data' => [
                'user' => $updatedUser,
                'domicile_info' => $updatedUser->getDomicileInfo(),
            ],
        ]);
    }

    /**
     * Get user by ID (for spouse data access)
     *
     * GET /api/users/{userId}
     */
    public function getUserById(Request $request, int $userId): JsonResponse
    {
        $currentUser = $request->user();

        // Only allow access to spouse data
        if ($currentUser->spouse_id !== $userId) {
            \Illuminate\Support\Facades\Log::warning('Unauthorized user data access attempt', [
                'requesting_user_id' => $currentUser->id,
                'target_user_id' => $userId,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to user data',
            ], 403);
        }

        $user = \App\Models\User::findOrFail($userId);

        return response()->json([
            'success' => true,
            'data' => [
                'user' => new UserResource($user),
            ],
        ]);
    }

    /**
     * Get financial commitments for expenditure tracking
     *
     * GET /api/user/financial-commitments
     */
    public function getFinancialCommitments(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            // Optional filter: 'all' (default), 'joint_only', 'individual_only'
            $ownershipFilter = $request->query('ownership_filter', 'all');

            $commitments = $this->userProfileService->getFinancialCommitments($user, $ownershipFilter);

            return response()->json([
                'success' => true,
                'data' => $commitments,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Fetching financial commitments');
        }
    }

    /**
     * Get spouse's financial commitments for expenditure tracking
     *
     * GET /api/user/spouse/financial-commitments
     */
    public function getSpouseFinancialCommitments(Request $request): JsonResponse
    {
        $user = $request->user();
        $spouse = $user->spouse;

        if (! $spouse) {
            return response()->json([
                'success' => false,
                'message' => 'No spouse found',
            ], 404);
        }

        try {
            $ownershipFilter = $request->query('ownership_filter', 'all');
            $commitments = $this->userProfileService->getFinancialCommitments($spouse, $ownershipFilter);

            return response()->json([
                'success' => true,
                'data' => $commitments,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Fetching spouse financial commitments');
        }
    }

    /**
     * Update dashboard widget order
     *
     * PUT /api/user/dashboard-widget-order
     */
    public function updateDashboardWidgetOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'widget_order' => 'required|array',
            'widget_order.*' => 'string|in:net_worth,affordability,retirement,investment,tax,estate,protection,trusts,admin_taxes',
        ]);

        $request->user()->update([
            'dashboard_widget_order' => $validated['widget_order'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Dashboard widget order updated successfully',
        ]);
    }

    /**
     * Update spouse expenditure information
     *
     * PUT /api/users/{userId}/expenditure
     */
    public function updateSpouseExpenditure(Request $request, int $userId): JsonResponse
    {
        $currentUser = $request->user();

        // Only allow updating spouse's expenditure
        if ($currentUser->spouse_id !== $userId) {
            \Illuminate\Support\Facades\Log::warning('Unauthorized user data access attempt', [
                'requesting_user_id' => $currentUser->id,
                'target_user_id' => $userId,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to update this user\'s expenditure',
            ], 403);
        }

        $spouse = \App\Models\User::findOrFail($userId);

        $validated = $request->validate([
            'monthly_expenditure' => 'nullable|numeric|min:0',
            'annual_expenditure' => 'nullable|numeric|min:0',
            'use_simple_entry' => 'nullable|boolean',
            'food_groceries' => 'nullable|numeric|min:0',
            'transport_fuel' => 'nullable|numeric|min:0',
            'healthcare_medical' => 'nullable|numeric|min:0',
            'insurance' => 'nullable|numeric|min:0',
            'mobile_phones' => 'nullable|numeric|min:0',
            'internet_tv' => 'nullable|numeric|min:0',
            'subscriptions' => 'nullable|numeric|min:0',
            'clothing_personal_care' => 'nullable|numeric|min:0',
            'entertainment_dining' => 'nullable|numeric|min:0',
            'holidays_travel' => 'nullable|numeric|min:0',
            'pets' => 'nullable|numeric|min:0',
            'childcare' => 'nullable|numeric|min:0',
            'school_fees' => 'nullable|numeric|min:0',
            'school_lunches' => 'nullable|numeric|min:0',
            'school_extras' => 'nullable|numeric|min:0',
            'university_fees' => 'nullable|numeric|min:0',
            'children_activities' => 'nullable|numeric|min:0',
            'gifts_charity' => 'nullable|numeric|min:0',
            'regular_savings' => 'nullable|numeric|min:0',
            'other_expenditure' => 'nullable|numeric|min:0',
        ]);

        // Map frontend field names to database column names
        // expenditure_entry_mode: enum('simple', 'category')
        $updateData = $validated;
        if (isset($validated['use_simple_entry'])) {
            $updateData['expenditure_entry_mode'] = $validated['use_simple_entry'] ? 'simple' : 'category';
            unset($updateData['use_simple_entry']);
        }

        // Ensure annual_expenditure is set when monthly_expenditure is provided
        if (isset($updateData['monthly_expenditure']) && ! isset($updateData['annual_expenditure'])) {
            $updateData['annual_expenditure'] = (float) $updateData['monthly_expenditure'] * 12;
        }

        $spouse->update($updateData);

        // Create/update expenditure profile with the total
        if ($validated['monthly_expenditure'] ?? null) {
            $monthly = $validated['monthly_expenditure'];

            \App\Models\ExpenditureProfile::updateOrCreate(
                ['user_id' => $spouse->id],
                [
                    'monthly_housing' => 0,
                    'monthly_food' => 0,
                    'monthly_utilities' => 0,
                    'monthly_transport' => 0,
                    'monthly_insurance' => 0,
                    'monthly_loans' => 0,
                    'monthly_discretionary' => 0,
                    'total_monthly_expenditure' => $monthly,
                ]
            );
        }

        $this->cacheInvalidation->invalidateForUserAndSpouse($currentUser->id, $spouse->id);

        return response()->json([
            'success' => true,
            'message' => 'Spouse expenditure information updated successfully',
            'data' => [
                'user' => new UserResource($spouse->fresh()),
            ],
        ]);
    }
}
