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
use App\Services\Expenditure\HouseholdExpenditureWriter;
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
        private readonly HouseholdExpenditureWriter $expenditureWriter,
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

            // W-0202 (CSJ, 2026-08-24). **This is the only place a sharing mode
            // becomes a DECLARATION**, because it is the only place a person has
            // been shown the choice and made it — the form's toggle, beside a
            // subheading reading "Joint (50/50) expenditure" while they type.
            //
            // The column itself cannot record that: `expenditure_sharing_mode` is
            // NOT NULL DEFAULT 'joint', so every row has said "joint" since it was
            // created and a household that has never been asked is indistinguishable
            // from one that chose. Fyn needs to tell them apart to know whether it
            // may halve a figure or must ask (`CoordinatingAgent::handleSetExpenditure`).
            //
            // Stamped on the acting user only. The mode is a fact about the
            // household, but the DECLARATION is an act by a person, and the writer
            // already propagates the mode itself to the spouse's row.
            $updateData['expenditure_sharing_mode_declared_at'] = now();
        }

        // Ensure annual_expenditure is set when monthly_expenditure is provided
        if (isset($updateData['monthly_expenditure']) && ! isset($updateData['annual_expenditure'])) {
            $updateData['annual_expenditure'] = (float) $updateData['monthly_expenditure'] * 12;
        }

        // The form sends what the HOUSEHOLD spends; each account stores ITS
        // SHARE, and both shares are derived from that one household figure in
        // one transaction. W-0190 applied the rule here but left the spouse's
        // half to a separate request from the frontend; when that request did
        // not arrive the household total quietly inherited the difference
        // (W-0412). One home for the write — App\Services\Expenditure\
        // HouseholdExpenditureWriter, which invalidates both accounts' caches.
        $this->expenditureWriter->write($user, $updateData);

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
        //
        // W-0350 — and RECIPROCAL. `User` soft-deletes, so the live test was already
        // most of what `liveSpouseId()` bought here; what it never answered is whether
        // the named account named this one back. This returns their entire
        // `UserResource`.
        $reciprocalSpouse = $currentUser->reciprocalLiveSpouse();

        if ($reciprocalSpouse === null || $reciprocalSpouse->id !== $userId) {
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
        // W-0350 — reciprocal only; this endpoint returns the OTHER account's
        // financial commitments.
        $spouse = $user->reciprocalLiveSpouse();

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
        //
        // **W-0350 — RECIPROCAL, not merely live.** `liveSpouseId()` answers "is the
        // account I named still there?", which `User`'s soft deletes largely answer
        // anyway. It does not answer "did they name me back". Naming someone as your
        // spouse was enough to overwrite twenty-one expenditure columns in their
        // account — a write into someone else's records, which the census ranks above
        // any read of them.
        $reciprocalSpouse = $currentUser->reciprocalLiveSpouse();

        if ($reciprocalSpouse === null || $reciprocalSpouse->id !== $userId) {
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

        $spouse = $reciprocalSpouse;

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

        // Under a JOINT mode the caller is sending the household's figures, and
        // a household figure has exactly one home to be written from — the one
        // that derives both halves together. Routing this endpoint through it
        // makes the call idempotent with the acting user's own save rather than
        // a second, independent chance to get one row wrong (W-0412).
        //
        // The acting user's mode is the household's — they are the one who just
        // declared it. Reading it off the spouse's row would divide the two
        // halves of one save by two different rules if that row had not caught
        // up yet.
        if (SharedExpenditure::isShared($currentUser->expenditure_sharing_mode)) {
            $this->expenditureWriter->write($currentUser, $updateData);

            return response()->json([
                'success' => true,
                'message' => 'Spouse expenditure information updated successfully',
                'data' => [
                    'user' => new UserResource($spouse->fresh()),
                ],
            ]);
        }

        // Separate spending: the spouse's own figures, stored whole, on the
        // spouse's own row.
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
