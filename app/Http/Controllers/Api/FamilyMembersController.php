<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\SpouseCollisionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFamilyMemberRequest;
use App\Http\Requests\UpdateFamilyMemberRequest;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\FamilyMember;
use App\Models\SpousePermission;
use App\Models\User;
use App\Services\Cache\CacheInvalidationService;
use App\Services\Onboarding\SpouseLinkingService;
use App\Services\UserProfile\UserProfileService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class FamilyMembersController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly CacheInvalidationService $cacheInvalidation,
        private readonly UserProfileService $userProfileService
    ) {}

    /**
     * Display a listing of the authenticated user's family members.
     *
     * GET /api/user/family-members
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $allMembers = $this->userProfileService->getFamilyMembersWithSharing($user);

        return response()->json([
            'success' => true,
            'data' => [
                'family_members' => $allMembers,
                'count' => count($allMembers),
            ],
        ]);
    }

    /**
     * Store a newly created family member.
     *
     * POST /api/user/family-members
     */
    public function store(StoreFamilyMemberRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        // Special handling for spouse relationship
        if ($data['relationship'] === 'spouse' && isset($data['email'])) {
            return $this->handleSpouseCreation($user, $data);
        }

        // Check for duplicate children when user has linked spouse
        if ($data['relationship'] === 'child' && $user->liveSpouseId()) {
            $duplicateInUserRecords = FamilyMember::where('user_id', $user->id)
                ->where('relationship', 'child')
                ->where('first_name', $data['first_name'])
                ->where('last_name', $data['last_name'])
                ->where('date_of_birth', $data['date_of_birth'] ?? null)
                ->exists();

            $liveSpouseId = $user->liveSpouseId();
            $duplicateInSpouseRecords = $liveSpouseId !== null
                && FamilyMember::where('user_id', $liveSpouseId)
                    ->where('relationship', 'child')
                    ->where('first_name', $data['first_name'])
                    ->where('last_name', $data['last_name'])
                    ->where('date_of_birth', $data['date_of_birth'] ?? null)
                    ->exists();

            if ($duplicateInUserRecords || $duplicateInSpouseRecords) {
                return response()->json([
                    'success' => false,
                    'message' => 'This child already exists in your or your spouse\'s family members. Children are automatically shared between linked spouses.',
                ], 422);
            }
        }

        // Construct full name from name parts for legacy 'name' field
        $fullName = trim(($data['first_name'] ?? '').' '.
            (isset($data['middle_name']) && $data['middle_name'] ? $data['middle_name'].' ' : '').
            ($data['last_name'] ?? ''));

        // `partner` and `step_child` are offered by the form but are not values
        // the column holds, and strict mode turns that into a 500 rather than a
        // degraded row (W-0114). One resolver, shared with the Fyn tool path.
        $resolved = FamilyMember::resolveRelationship($data['relationship']);
        $data['relationship'] = $resolved['relationship'];
        $data['stated_relationship'] = $resolved['stated'];
        $notes = FamilyMember::composeRelationshipNotes($resolved['note'], $data['notes'] ?? null);
        if ($notes !== null) {
            $data['notes'] = $notes;
        }

        $familyMember = FamilyMember::create([
            ...$data,
            'user_id' => $user->id,
            'household_id' => $user->household_id,
            'name' => $fullName,  // Construct for legacy field (overrides any sent name)
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Family member added successfully',
            'data' => [
                'family_member' => $familyMember,
            ],
        ], 201);
    }

    /**
     * Handle spouse creation or linking.
     *
     * Rule 20 — this used to carry its own 250-line copy of the linking logic:
     * link both sides, write both SpousePermission rows, write both
     * family_members rows. `SpouseLinkingService` did the same job for Fyn
     * onboarding, and its own docblock already claimed it served this path too.
     * Two mechanisms meant a fix could land in one and miss the other, and they
     * had already diverged (this one forced marital_status to 'married' and so
     * quietly demoted a civil partnership). One home now; the checks that shape
     * THIS surface's responses stay here, the linking itself does not.
     */
    private function handleSpouseCreation($currentUser, array $data): JsonResponse
    {
        // Applied here rather than on the route: `store` also adds children and
        // other relatives, and a large family must not run into an invitation
        // limit. Only this branch can point an email address at a stranger's
        // account — or create one for an address that has none (W-0349).
        $throttleKey = 'spouse-invite:'.$currentUser->id;

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            return response()->json([
                'success' => false,
                'message' => 'Too many household invitations. Please try again in an hour.',
            ], 429);
        }

        RateLimiter::hit($throttleKey, 3600);

        $spouseEmail = strtolower(trim((string) $data['email']));
        $data['email'] = $spouseEmail;

        // Kept here, ahead of the service, because this surface answers a
        // closed account with a field-level validation error on `email` rather
        // than a plain message — the form highlights the field the user must
        // change. The service raises a collision for the same case.
        $spouseUser = User::withTrashed()->where('email', $spouseEmail)->first();

        if ($spouseUser?->trashed()) {
            return $this->unlinkableEmailValidationError();
        }

        if ($spouseUser && $spouseUser->id === $currentUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot add yourself as a spouse',
            ], 422);
        }

        try {
            $result = app(SpouseLinkingService::class)->linkOrCreateSpouse($currentUser, $data);
        } catch (SpouseCollisionException $exception) {
            // Same response as a closed account, deliberately. "Already linked
            // to another spouse" and "that email is already in use" are two
            // different confirmations that the address holds a Fynla account,
            // handed to any authenticated caller who guesses one (W-0349).
            return $this->unlinkableEmailValidationError();
        } catch (\InvalidArgumentException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        } catch (QueryException $exception) {
            if ($this->isDuplicateEmailException($exception)) {
                return $this->unlinkableEmailValidationError();
            }

            throw $exception;
        }

        $familyMember = $result['family_member'];
        $spouseUser = $result['spouse_user'];
        $rowIsNew = $familyMember->wasRecentlyCreated;

        if ($result['created_new_user']) {
            return response()->json([
                'success' => true,
                'message' => $result['email_sent']
                    ? 'Spouse account created successfully. They will receive an email with login instructions.'
                    : 'Spouse account created but email delivery failed. They can use the "Forgot Password" feature to set their password.',
                'data' => [
                    'family_member' => $familyMember,
                    'spouse_user' => $this->spouseSummary($spouseUser),
                    'created' => true,
                    'email_sent' => $result['email_sent'],
                    'spouse_email' => $spouseEmail,
                ],
            ], 201);
        }

        if ($result['already_linked']) {
            return response()->json([
                'success' => true,
                'message' => $rowIsNew
                    ? 'Spouse family member record created (accounts already linked)'
                    : 'Spouse is already linked',
                'data' => [
                    'family_member' => $familyMember,
                    'spouse_user' => $this->spouseSummary($spouseUser),
                    'linked' => true,
                    'already_existed' => ! $rowIsNew,
                    'record_created' => $rowIsNew,
                ],
            ], $rowIsNew ? 201 : 200);
        }

        // An existing account was INVITED, not linked (W-0347). Saying "linked"
        // here would be the interface telling the user the thing the old code
        // did wrong — and the message is the only place they learn their spouse
        // has to agree.
        return response()->json([
            'success' => true,
            'message' => 'Invitation sent. Your spouse will be asked to confirm the link before anything is shared.',
            'data' => [
                'family_member' => $familyMember,
                // No counterparty details at all. Nothing about an account that
                // has not agreed to be linked is disclosed to the caller — not
                // their name, not their id, not confirmation that the address is
                // registered (W-0348, W-0349).
                'linked' => false,
                'invitation_pending' => true,
            ],
        ], 201);
    }

    /**
     * The only fields a client renders for a linked spouse.
     *
     * Was the whole Eloquent model. `$hidden` strips credentials and the
     * national insurance number and nothing else, so date of birth, address,
     * phone, occupation, employer, every `annual_*_income`, monthly and annual
     * expenditure with all 21 category columns, health status, smoking status
     * and domicile all shipped — and any column added to `users` later would
     * have shipped too, silently (W-0348).
     *
     * @return array{id: int, first_name: ?string, surname: ?string, name: ?string, email: string}
     */
    private function spouseSummary(User $spouseUser): array
    {
        return [
            'id' => $spouseUser->id,
            'first_name' => $spouseUser->first_name,
            'surname' => $spouseUser->surname,
            'name' => $spouseUser->name,
            // The caller supplied this address, so it discloses nothing new —
            // and the UI echoes it back as confirmation of where the email went.
            'email' => $spouseUser->email,
        ];
    }

    /**
     * One message for every address that cannot be linked, whatever the reason.
     *
     * Three distinguishable refusals used to sit behind this surface — closed
     * account, already in another household, duplicate on insert — and each one
     * told an authenticated caller something true about an address they had
     * merely typed. Collapsed to one (W-0349). The remaining distinction, and
     * it is deliberate rather than overlooked: an address with NO account still
     * gets one created. That is a product decision on the board, not something
     * to change quietly here.
     */
    private function unlinkableEmailValidationError(): JsonResponse
    {
        return $this->validationErrorResponse(
            'That email address cannot be linked to your household',
            ['email' => ['That email address cannot be linked to your household']]
        );
    }

    private function isDuplicateEmailException(QueryException $exception): bool
    {
        return $exception->getCode() === '23000'
            && str_contains($exception->getMessage(), 'users_email_unique');
    }

    /**
     * Display the specified family member.
     *
     * GET /api/user/family-members/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $familyMember = FamilyMember::where('user_id', $user->id)
            ->findOrFail($id);

        $memberArray = $familyMember->toArray();

        // The email belongs to the account THIS row links to. Reading it off
        // the user's spouse instead handed the real spouse's address to an
        // unlinked row, which is how an orphan came back looking linked and the
        // edit form pre-filled somebody else's email (W-0051).
        if ($linkedUser = $familyMember->liveLinkedUser()) {
            $memberArray['email'] = $linkedUser->email;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'family_member' => $memberArray,
            ],
        ]);
    }

    /**
     * Update the specified family member.
     *
     * PUT /api/user/family-members/{id}
     */
    public function update(UpdateFamilyMemberRequest $request, int $id): JsonResponse
    {
        $user = $request->user();

        $familyMember = FamilyMember::where('user_id', $user->id)
            ->findOrFail($id);

        $data = $request->validated();

        // Same resolver as store(): the update request accepts `step_child`
        // too, and writing it would 500 on the enum exactly as creating it did.
        if (isset($data['relationship'])) {
            $resolved = FamilyMember::resolveRelationship($data['relationship']);
            $data['relationship'] = $resolved['relationship'];
            $data['stated_relationship'] = $resolved['stated'];
            $notes = FamilyMember::composeRelationshipNotes($resolved['note'], $data['notes'] ?? null);
            if ($notes !== null) {
                $data['notes'] = $notes;
            }
        }

        // Construct full name from name parts if provided
        if (isset($data['first_name']) || isset($data['last_name'])) {
            $firstName = $data['first_name'] ?? $familyMember->first_name ?? '';
            $middleName = $data['middle_name'] ?? $familyMember->middle_name ?? '';
            $lastName = $data['last_name'] ?? $familyMember->last_name ?? '';
            $data['name'] = trim($firstName.($middleName ? ' '.$middleName : '').' '.$lastName);
        }

        $familyMember->update($data);

        // Sync to the account THIS row links to — never to whoever happens to
        // sit in `users.spouse_id`. Branching on the relationship string meant
        // editing an unlinked spouse record rewrote the real spouse's name,
        // date of birth, income and National Insurance number (W-0051).
        $spouseUser = $familyMember->fresh()->liveLinkedUser();

        if ($spouseUser !== null) {
            // One declared correspondence between the two tables, rather than
            // five hand-written lines that had to guess which column each field
            // lands in. The name line guessed `name`, which `users` does not
            // have, so every rename was discarded in silence (W-0112).
            $spouseUpdates = app(SpouseLinkingService::class)->userAttributesFrom($data);

            if (! empty($spouseUpdates)) {
                $spouseUser->update($spouseUpdates);

                $this->cacheInvalidation->invalidateForUserAndSpouse($user->id, $spouseUser->id);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Family member updated successfully',
            'data' => [
                'family_member' => $familyMember->fresh(),
            ],
        ]);
    }

    /**
     * Remove the specified family member.
     *
     * DELETE /api/user/family-members/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $familyMember = FamilyMember::where('user_id', $user->id)
            ->findOrFail($id);

        // Unlink the household ONLY when this row is what carries the link.
        // Branching on the relationship string meant deleting an unlinked
        // spouse record tore down a real, separate link: it nulled `spouse_id`
        // on both users, deleted both SpousePermission rows and deleted the
        // reciprocal record on the spouse's own account — for a row that never
        // linked anything (W-0051). An unlinked record is now just a record,
        // and deleting it removes exactly itself.
        $spouseUser = $familyMember->liveLinkedUser();

        if ($spouseUser !== null) {
            // Delete the reciprocal family_member record on spouse's account —
            // matched by the link back to this user, not by relationship.
            FamilyMember::where('user_id', $spouseUser->id)
                ->where('linked_user_id', $user->id)
                ->delete();

            // Delete bidirectional spouse permissions
            SpousePermission::where(function ($query) use ($user, $spouseUser) {
                $query->where('user_id', $user->id)->where('spouse_id', $spouseUser->id);
            })->orWhere(function ($query) use ($user, $spouseUser) {
                $query->where('user_id', $spouseUser->id)->where('spouse_id', $user->id);
            })->delete();

            $this->cacheInvalidation->invalidateForUserAndSpouse($user->id, $spouseUser->id);

            // Clear spouse linkage for both users
            $spouseUser->spouse_id = null;
            $spouseUser->save();

            $user->spouse_id = null;
            $user->save();
        }

        $familyMember->delete();

        return response()->json([
            'success' => true,
            'message' => 'Family member deleted successfully',
        ]);
    }
}
