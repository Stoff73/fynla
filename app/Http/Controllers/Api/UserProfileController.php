<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateDomicileInfoRequest;
use App\Http\Requests\UpdateIncomeOccupationRequest;
use App\Http\Requests\UpdatePersonalInfoRequest;
use App\Http\Resources\UserResource;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\ExpenditureProfile;
use App\Models\User;
use App\Services\Cache\CacheInvalidationService;
use App\Services\Tiers\TeaserGate;
use App\Services\UserProfile\UserProfileService;
use App\Support\SharedExpenditure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UserProfileController extends Controller
{
    use SanitizedErrorResponse;

    /**
     * The per-category breakdown — the only Premium part of expenditure.
     * `expenditure_detailed` is `none` on Free and `full` on Premium
     * (TierConfigurationSeeder); plain `expenditure` is `full` on both, so the
     * monthly total is never gated.
     *
     * The entry-mode flags `use_simple_entry` / `use_separate_expenditure` used
     * to be listed here. `use_simple_entry` is the very flag that says "no
     * categories in this payload", so gating on it made a Simple View save
     * indistinguishable from a detailed one (W-0011).
     */
    private const DETAILED_EXPENDITURE_FIELDS = [
        'food_groceries',
        'transport_fuel',
        'healthcare_medical',
        'insurance',
        'mobile_phones',
        'internet_tv',
        'subscriptions',
        'clothing_personal_care',
        'entertainment_dining',
        'holidays_travel',
        'pets',
        'childcare',
        'school_fees',
        'school_lunches',
        'school_extras',
        'university_fees',
        'children_activities',
        'gifts_charity',
        'charitable_donations',
        'regular_savings',
        'other_expenditure',
        'retired_budget_overrides',
        'widowed_budget_overrides',
    ];

    public function __construct(
        private readonly UserProfileService $userProfileService,
        private readonly CacheInvalidationService $cacheInvalidation,
        private readonly TeaserGate $teaserGate,
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
        if (! $this->canUseDetailedExpenditure($user)) {
            unset($profile['expenditure']['categories']);
            $profile['expenditure']['presentation']['detail_available'] = false;
            if (($profile['expenditure']['presentation']['entry_mode'] ?? null) === 'category') {
                $profile['expenditure']['presentation']['summary_only_reason'] =
                    'Category details are not available on your current plan.';
            }
        }

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

    private function canUseDetailedExpenditure(User $user): bool
    {
        return $this->teaserGate->allows($user, 'expenditure_detailed');
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

        if ($denial = $this->guardDetailedExpenditure($request, $user)) {
            return $denial;
        }

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
            'charitable_donations' => 'nullable|numeric|min:0',
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
            $updateData['expenditure_sharing_mode'] = $validated['use_separate_expenditure']
                ? SharedExpenditure::MODE_SEPARATE
                : SharedExpenditure::MODE_JOINT;
            unset($updateData['use_separate_expenditure']);
        }

        // Ensure annual_expenditure is set when monthly_expenditure is provided
        if (isset($updateData['monthly_expenditure']) && ! isset($updateData['annual_expenditure'])) {
            $updateData['annual_expenditure'] = (float) $updateData['monthly_expenditure'] * 12;
        }

        // Apply the household's declared sharing rule, which this path never did.
        // The form sends what the household spends; each account stores ITS SHARE.
        // Storing the whole of it here is what let the expenditure table announce
        // "Joint (50/50) expenditure" and then charge one spouse £2,450 and the
        // other £0, beside a financial-commitments row that IS split (W-0190).
        // Same home as the onboarding path — App\Support\SharedExpenditure.
        $householdMode = $updateData['expenditure_sharing_mode'] ?? $user->expenditure_sharing_mode;
        $sharesWithSpouse = $user->spouse_id !== null && SharedExpenditure::isShared($householdMode);
        $updateData = SharedExpenditure::shareOf($updateData, $sharesWithSpouse);

        $user->update($updateData);

        // The sharing mode is a fact about the HOUSEHOLD, not about one row. Left
        // on one account it can drift: the spouse's row would keep saying `joint`
        // while this one says `separate`, and the two halves of a single save would
        // then be divided by different rules. The onboarding path has always written
        // it to both; this one now does too.
        if (isset($updateData['expenditure_sharing_mode']) && $user->liveSpouseId() !== null) {
            $user->spouse?->update(['expenditure_sharing_mode' => $updateData['expenditure_sharing_mode']]);
        }

        // Create/update expenditure profile with the total
        if ($updateData['monthly_expenditure'] ?? null) {
            $monthly = $updateData['monthly_expenditure'];

            ExpenditureProfile::updateOrCreate(
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

        // Only allow access to a LIVE spouse's data. The link survives the
        // partner deleting their account — retention — but this endpoint returns
        // their profile, so it must stop answering once they are gone (D5).
        if ($currentUser->liveSpouseId() !== $userId) {
            Log::warning('Unauthorized user data access attempt', [
                'requesting_user_id' => $currentUser->id,
                'target_user_id' => $userId,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to user data',
            ], 403);
        }

        $user = User::findOrFail($userId);

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

        if ($denial = $this->guardDetailedExpenditure($request, $currentUser)) {
            return $denial;
        }

        // Only allow updating a LIVE spouse's expenditure. A retained record
        // must not stay writable by someone who can no longer see it (D5).
        if ($currentUser->liveSpouseId() !== $userId) {
            Log::warning('Unauthorized user data access attempt', [
                'requesting_user_id' => $currentUser->id,
                'target_user_id' => $userId,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to update this user\'s expenditure',
            ], 403);
        }

        $spouse = User::findOrFail($userId);

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
            'charitable_donations' => 'nullable|numeric|min:0',
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

        // The spouse's account stores THEIR share, by the household's declared rule
        // — the same rule and the same home as the account beside it. Under a joint
        // mode the caller sends the household's figures and both halves are stored;
        // under separate the spouse's own figures are stored whole (W-0190).
        // The acting user's mode is the household's — they are the one who just
        // declared it. Reading it off the spouse's row would divide the two halves
        // of one save by two different rules if that row had not caught up yet.
        $updateData = SharedExpenditure::shareOf(
            $updateData,
            SharedExpenditure::isShared($currentUser->expenditure_sharing_mode)
        );

        $spouse->update($updateData);

        // Create/update expenditure profile with the total
        if ($updateData['monthly_expenditure'] ?? null) {
            $monthly = $updateData['monthly_expenditure'];

            ExpenditureProfile::updateOrCreate(
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

    /**
     * Gate the Premium category breakdown without gating the monthly total.
     *
     * Returns a denial for a genuine detailed-entry attempt, and null when the
     * request may proceed. For a simple-entry request from a user without the
     * capability the category keys are stripped in place: the Expenditure form
     * builds one payload for both modes, so Simple View arrives carrying all 22
     * categories as zeros, and denying on key presence locked Free users out of
     * recording any expenditure at all (W-0011).
     *
     * This matches what Fyn already does on /m and native — CoordinatingAgent's
     * update_profile writes a simple monthly total for any tier and only
     * set_expenditure (the category tool) checks `expenditure_detailed`.
     */
    private function guardDetailedExpenditure(Request $request, User $user): ?JsonResponse
    {
        if ($this->canUseDetailedExpenditure($user)) {
            return null;
        }

        $detailedKeys = array_intersect(array_keys($request->all()), self::DETAILED_EXPENDITURE_FIELDS);

        if ($detailedKeys === []) {
            return null;
        }

        if (! $this->isSimpleExpenditureEntry($request)) {
            return $this->detailedExpenditureDenial();
        }

        foreach ($detailedKeys as $key) {
            $request->request->remove($key);
            $request->query->remove($key);
        }
        $request->merge(['use_simple_entry' => true]);

        return null;
    }

    private function isSimpleExpenditureEntry(Request $request): bool
    {
        return $request->boolean('use_simple_entry')
            || $request->input('expenditure_entry_mode') === 'simple';
    }

    private function detailedExpenditureDenial(): JsonResponse
    {
        return response()->json([
            'error' => 'capability_denied',
            'capability' => 'expenditure_detailed',
            'required_tier' => 'premium',
            'message' => 'Detailed expenditure is part of Premium.',
        ], 403);
    }
}
