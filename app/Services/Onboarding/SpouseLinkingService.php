<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Exceptions\SpouseCollisionException;
use App\Mail\SpouseInvitation;
use App\Models\FamilyMember;
use App\Models\SpousePermission;
use App\Models\User;
use App\Notifications\SpousePermissionRequest;
use App\Services\Cache\CacheInvalidationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Shared spouse linking logic used by both the FamilyMembers UI flow
 * (through FamilyMembersController::handleSpouseCreation) and the Fyn
 * onboarding director (OnboardingChatDirector) when capturing base_spouse
 * from a grouped-extract turn.
 *
 * Three paths:
 *   1. Spouse user exists and is already linked to current user
 *      → ensure FamilyMember record exists, no email sent
 *   2. Spouse user exists but not linked
 *      → INVITE: record a pending spouse_permission and the caller's own
 *        family_member row, notify the invitee, and write NOTHING to their
 *        account. The link itself is established only when they accept
 *        (SpousePermissionController::accept). W-0347.
 *   3. Spouse user does not exist
 *      → create new user with random temporary password, link
 *        bidirectionally, create permissions, create family_member rows,
 *        send "account created" email with temporary credentials
 *
 * Preserves the current user's marital_status (so civil_partnership
 * stays as civil_partnership rather than being forced to 'married'),
 * which is the main difference from the inline controller implementation.
 *
 * Plan: April/April15Updates/fynOnboardFix.md §5.2 (base_spouse grouped
 * extraction) + CLAUDE.md §14 icon rule.
 */
final class SpouseLinkingService
{
    public function __construct(
        private readonly CacheInvalidationService $cacheInvalidation,
        private readonly HouseholdProvisioner $householdProvisioner,
    ) {}

    /**
     * THE correspondence between a spouse's `family_members` row and their own
     * `users` row (Rule 20).
     *
     * The two tables hold the same person under different column names, and the
     * translation was written out by hand wherever it was needed. The edit path
     * got it wrong: it synced `name`, which `users` does not have — `name` is an
     * appended accessor derived from first_name/middle_name/surname
     * (`User::getNameAttribute()`), and `isFillable('name')` is false, so the
     * value was dropped by `fill()` without an error, a warning or a failed
     * save. Correcting a spouse's name updated their card and left their own
     * account, and with it every surface that reads the spouse off the user
     * record — the profile payload, `/m`, native iOS, beneficiary dropdowns
     * (W-0112).
     *
     * `last_name` → `surname` is the whole reason this needs a declared home:
     * the one field whose name differs is the one a hand-written sync gets
     * wrong, and getting it wrong fails silently.
     *
     * @var array<string, string> family_members column => users column
     */
    public const FAMILY_MEMBER_TO_USER_COLUMNS = [
        'first_name' => 'first_name',
        'middle_name' => 'middle_name',
        'last_name' => 'surname',
        'date_of_birth' => 'date_of_birth',
        'gender' => 'gender',
        'annual_income' => 'annual_employment_income',
        'national_insurance_number' => 'national_insurance_number',
    ];

    /**
     * Translate family-member-shaped fields into the columns the linked user
     * actually has.
     *
     * Only keys PRESENT in $fields are translated, so a caller syncing one
     * edited field never blanks the rest — and a key present with an explicit
     * null does clear its column, because that is the user removing a value.
     * Anything with no correspondence (relationship, is_dependent, notes,
     * education_status, and the derived `name`) is dropped here rather than
     * being offered to the user record at all.
     *
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    public function userAttributesFrom(array $fields): array
    {
        $attributes = [];

        foreach (self::FAMILY_MEMBER_TO_USER_COLUMNS as $memberColumn => $userColumn) {
            if (array_key_exists($memberColumn, $fields)) {
                $attributes[$userColumn] = $fields[$memberColumn];
            }
        }

        return $attributes;
    }

    /**
     * Link an existing spouse account or create a new one.
     *
     * @param  array<string, mixed>  $data  Required: first_name, email. Optional:
     *                                      last_name, date_of_birth, annual_income,
     *                                      gender, national_insurance_number.
     * @return array{
     *   family_member: FamilyMember,
     *   spouse_user: User,
     *   created_new_user: bool,
     *   already_linked: bool,
     *   invitation_pending?: bool,
     *   email_sent: bool,
     *   temporary_password: ?string
     * }
     *
     * `invitation_pending` is present and true only on path 2 — an existing
     * account has been invited and has NOT been linked. Callers rendering
     * "linked" off the absence of an error will otherwise tell the user their
     * spouse is connected when nothing has happened yet.
     */
    public function linkOrCreateSpouse(User $currentUser, array $data): array
    {
        // Lowercase + trim before any lookup. Email comparisons in PHP/MySQL
        // can be case-sensitive depending on collation; "Jane@Example.com"
        // and "jane@example.com" must resolve to the same account or we
        // either (a) tell a legitimate user "that email belongs to another
        // household" or, on case-mismatch DBs, (b) create a parallel
        // duplicate account. Normalise once here so every downstream
        // lookup, INSERT, and email send uses the canonical form.
        $spouseEmail = strtolower(trim((string) ($data['email'] ?? '')));
        if ($spouseEmail === '') {
            throw new \InvalidArgumentException('Spouse email is required for linking.');
        }
        $data['email'] = $spouseEmail;

        // B-2 — make sure the current user has a household_id before we
        // propagate it to the newly-linked spouse and the FamilyMember row.
        // Without this, both records inherit NULL and the "plan together"
        // queries silently return nothing.
        $this->householdProvisioner->ensureFor($currentUser);

        Log::info('[SpouseLinkingService] linkOrCreateSpouse called', [
            'current_user_id' => $currentUser->id,
            'current_marital_status' => $currentUser->marital_status,
        ]);

        // Preserve the current user's marital status — if they picked
        // 'civil_partnership' in base_marital, they stay civil_partnership.
        // The inline controller implementation forces 'married' for all
        // spouses; we explicitly do NOT do that here.
        $maritalStatus = in_array(
            $currentUser->marital_status,
            ['married', 'civil_partnership'],
            true
        ) ? $currentUser->marital_status : 'married';

        // withTrashed, because the unique index does not honour soft deletes.
        // A default lookup skipped a closed account, reported "no such user",
        // and then INSERTed straight into a 1062 duplicate-key violation — the
        // user was told to re-send the first name, date of birth and email they
        // had just sent, forever (live: user 49, isenbret@gmail.com, 17:54, and
        // twice more on 2026-07-23). PR #697 closed this in
        // FamilyMembersController; this is the same hole in the path Fyn uses.
        $spouseUser = User::withTrashed()->where('email', $spouseEmail)->first();

        if ($spouseUser?->trashed()) {
            throw new SpouseCollisionException(
                'That email belongs to a closed Fynla account, so I cannot link it.'
            );
        }

        if ($spouseUser) {
            return $this->linkExistingSpouse($currentUser, $spouseUser, $data, $maritalStatus);
        }

        return $this->inviteUnregisteredSpouse($currentUser, $data, $spouseEmail, $maritalStatus);
    }

    /**
     * Spouse already has an account. Three sub-paths depending on
     * existing linkage:
     *   (a) linking to self → error
     *   (b) already linked to a different user → error
     *   (c) already linked to current user → ensure FamilyMember exists
     *   (d) not linked → INVITE; no write to their account, no link yet
     *
     * @param  array<string, mixed>  $data
     * @return array{family_member: FamilyMember, spouse_user: User, created_new_user: bool, already_linked: bool, email_sent: bool, temporary_password: ?string}
     */
    private function linkExistingSpouse(
        User $currentUser,
        User $spouseUser,
        array $data,
        string $maritalStatus
    ): array {
        if ($spouseUser->id === $currentUser->id) {
            throw new \InvalidArgumentException('You cannot add yourself as a spouse.');
        }

        if ($spouseUser->spouse_id && $spouseUser->spouse_id !== $currentUser->id) {
            throw new SpouseCollisionException('This email is already linked to another Fynla household.');
        }

        // Already linked to current user — make sure the FamilyMember record
        // exists on the current user side AND actually carries the link. It
        // previously returned whatever spouse row it found first, which on a
        // household holding an unlinked row returned the orphan and reported
        // "already linked" over the top of it (W-0051).
        if ($spouseUser->spouse_id === $currentUser->id) {
            $familyMember = $this->upsertFamilyMemberRow($currentUser, $spouseUser->id, $data);

            return [
                'family_member' => $familyMember,
                'spouse_user' => $spouseUser,
                'created_new_user' => false,
                'already_linked' => true,
                'email_sent' => false,
                'temporary_password' => null,
            ];
        }

        // Not linked — INVITE. This account belongs to somebody else, so nothing
        // here writes their row and nothing here grants the caller access to it
        // (W-0347).
        //
        // What this used to do, and why it was critical: it set the named
        // account's `spouse_id`, `marital_status`, `annual_employment_income`
        // (to a figure the CALLER supplied) and five address fields, then wrote
        // `status => 'accepted'` on BOTH `spouse_permissions` rows. One
        // authenticated POST and a guessed email address was therefore enough to
        // establish a link the target never agreed to — and because the server
        // wrote both halves, the two gates that would otherwise have caught it
        // (`hasReciprocalSpouseLink()` and `hasAcceptedSpousePermission()`) were
        // both satisfied by the forgery. Every downstream gate in the
        // application was decorative against this one endpoint.
        //
        // The rule this now keeps, and the one to test against if this is ever
        // rewritten: NO ACCOUNT'S ROW IS EVER WRITTEN BY ANOTHER ACCOUNT. Until
        // the invitee accepts there is no link in either direction, so there is
        // no one-sided link for any of the 53 `spouse_id` consumers (W-0350) to
        // trust. Acceptance — `SpousePermissionController::accept()` — is what
        // writes both rows, and by then both parties have asked for it.
        $familyMember = DB::transaction(function () use ($currentUser, $spouseUser, $data, $maritalStatus) {
            // Re-read under lock. The invitee must still be unattached at the
            // moment the invitation is recorded, or two households end up
            // holding a pending invitation for the same person.
            $lockedSpouse = User::lockForUpdate()->find($spouseUser->id);
            if ($lockedSpouse->spouse_id && $lockedSpouse->spouse_id !== $currentUser->id) {
                throw new SpouseCollisionException('Spouse was linked to another user during transaction.');
            }

            // The caller's OWN marital status is theirs to set. Their
            // `spouse_id` is not set yet: it is half of a link, and half a link
            // is what every gate in the application mistakes for a whole one.
            $currentUser->marital_status = $maritalStatus;
            $currentUser->save();

            $this->createPendingSpouseInvitation($currentUser->id, $lockedSpouse->id);

            // The caller's own household record of their spouse, carrying NO
            // `linked_user_id` — the card is the caller's to keep, the link to
            // the account is the invitee's to grant. No reciprocal row is
            // written on the invitee's side; that would be their row.
            return $this->upsertFamilyMemberRow($currentUser, null, $data);
        });

        $emailSent = $this->sendInvitationNotification($spouseUser, $currentUser);

        return [
            'family_member' => $familyMember,
            'spouse_user' => $spouseUser,
            'created_new_user' => false,
            'already_linked' => false,
            'invitation_pending' => true,
            'email_sent' => $emailSent,
            'temporary_password' => null,
        ];
    }

    /**
     * Establish the link, once the invitee has accepted it.
     *
     * THE ONLY place in the application where one account's row is written as
     * a result of another account's request — and it is legitimate here for
     * exactly one reason: the requester asked, and the accepter is the person
     * whose row is being written, acting on their own authenticated request.
     * Called from `SpousePermissionController::accept()`, never from a linking
     * path (W-0347).
     *
     * Both `spouse_id`s are set together and inside one transaction. A link
     * that exists on one side only is what every gate in the application
     * mistakes for a real one, so it must never be observable as an
     * intermediate state.
     *
     * Note what is NOT copied across: income, and the address block. The old
     * flow pushed the CALLER's figures into the other person's account, so an
     * attacker set their victim's stated income. An accepted link shares
     * visibility of records; it does not overwrite the other person's facts
     * with yours.
     *
     * @throws SpouseCollisionException if either side was linked elsewhere in
     *                                  the window between invitation and acceptance
     */
    public function establishAcceptedLink(User $requester, User $accepter): void
    {
        if ($requester->id === $accepter->id) {
            throw new \InvalidArgumentException('You cannot link an account to itself.');
        }

        $this->householdProvisioner->ensureFor($requester);

        DB::transaction(function () use ($requester, $accepter) {
            // Lock the LOWER id first, always. Two invitations accepted at the
            // same moment in opposite directions would otherwise each hold the
            // row the other is waiting for.
            $ids = [$requester->id, $accepter->id];
            sort($ids);
            $locked = User::lockForUpdate()->findMany($ids)->keyBy('id');

            $lockedRequester = $locked[$requester->id];
            $lockedAccepter = $locked[$accepter->id];

            foreach ([[$lockedRequester, $lockedAccepter], [$lockedAccepter, $lockedRequester]] as [$side, $other]) {
                if ($side->spouse_id && (int) $side->spouse_id !== $other->id) {
                    throw new SpouseCollisionException(
                        'One of these accounts was linked to a different spouse while the request was pending.'
                    );
                }
            }

            $maritalStatus = in_array($lockedRequester->marital_status, ['married', 'civil_partnership'], true)
                ? $lockedRequester->marital_status
                : 'married';

            $lockedRequester->spouse_id = $lockedAccepter->id;
            $lockedRequester->marital_status = $maritalStatus;
            $lockedRequester->save();

            $lockedAccepter->spouse_id = $lockedRequester->id;
            $lockedAccepter->marital_status = $maritalStatus;
            // The invitee joins the requester's household so the "plan
            // together" queries see one household rather than two.
            $lockedAccepter->household_id = $lockedRequester->household_id;
            $lockedAccepter->save();

            // Now that the link is real, the caller's card carries it, and the
            // accepter gets their own card for their partner.
            $this->attachLinkToExistingSpouseRow($lockedRequester, $lockedAccepter->id);
            $this->createReciprocalFamilyMember($lockedAccepter, $lockedRequester);

            $this->cacheInvalidation->invalidateForUserAndSpouse($lockedRequester->id, $lockedAccepter->id);
        });
    }

    /**
     * Point the requester's existing spouse card at the account that just
     * accepted. The card was written unlinked at invitation time; this is the
     * moment it earns its `linked_user_id`.
     */
    private function attachLinkToExistingSpouseRow(User $requester, int $linkedUserId): void
    {
        $row = $this->adoptableSpouseRow($requester->id, $linkedUserId);

        if ($row === null) {
            return;
        }

        $row->linked_user_id = $linkedUserId;
        $row->household_id = $requester->household_id;
        $row->save();
    }

    /**
     * Spouse address has NO Fynla account. INVITE it; create nothing.
     *
     * W-0349, CSJ decision 2026-08-23. This method replaces
     * `createAndLinkNewSpouse()`, which created a real `User` row with a random
     * temporary password for an address the caller had merely typed, linked it,
     * wrote `accepted` on both permission rows via
     * `createSpousePermissionsForCreatedAccount()`, and emailed login
     * credentials. Two harms came out of that, and this method removes both:
     *
     *   1. **Account creation for arbitrary addresses.** Any authenticated user
     *      could cause `users` rows to exist for any address they could type.
     *   2. **The enumeration oracle.** Because the created-account branch
     *      returned a visibly different response from the invited-account
     *      branch, the endpoint answered "does this address hold a Fynla
     *      account?" for anyone who asked. Collapsing the two branches to one
     *      response is what closes it — and they can only return the same thing
     *      if they DO the same thing. That is why the fix is here in the
     *      service and not a cosmetic edit in the controller.
     *
     * The shape returned is deliberately identical to `linkExistingSpouse()`'s
     * invitation branch, field for field. **If you add a key to one, add it to
     * the other or the oracle re-opens** — the difference between the two
     * responses IS the disclosure.
     *
     * No `spouse_permissions` row is written: that table's `spouse_id` is a
     * foreign key to `users`, and there is no invitee row to point at. The link
     * begins when they register and one side asks — which is the same consent
     * path an existing account already takes.
     *
     * @param  array<string, mixed>  $data
     * @return array{family_member: FamilyMember, spouse_user: ?User, created_new_user: bool, already_linked: bool, invitation_pending: bool, email_sent: bool, temporary_password: null}
     */
    private function inviteUnregisteredSpouse(
        User $currentUser,
        array $data,
        string $spouseEmail,
        string $maritalStatus
    ): array {
        $familyMember = DB::transaction(function () use ($currentUser, $data, $maritalStatus) {
            // The caller's OWN marital status is theirs to set, exactly as in
            // the existing-account invitation. Their `spouse_id` is not set:
            // there is no account to point it at, and a half link is what every
            // gate in the application mistakes for a whole one (W-0347).
            $currentUser->marital_status = $maritalStatus;
            $currentUser->save();

            // The caller's own household card, carrying NO `linked_user_id`.
            // They keep the details they entered about their partner; what they
            // do not get is an account belonging to somebody else.
            return $this->upsertFamilyMemberRow($currentUser, null, $data);
        });

        $emailSent = $this->sendRegistrationInvitation($spouseEmail, $currentUser);

        return [
            'family_member' => $familyMember,
            // Null, not a User. There is no counterparty account, and callers
            // that used to read `spouse_user` on this path were reading one
            // this method had just manufactured.
            'spouse_user' => null,
            'created_new_user' => false,
            'already_linked' => false,
            'invitation_pending' => true,
            'email_sent' => $emailSent,
            'temporary_password' => null,
        ];
    }

    /**
     * Write the current user's spouse row, ADOPTING an existing unlinked one
     * rather than adding a second (W-0051).
     *
     * A household can only have one spouse. Before this, any row already sitting
     * on the account — the one Fyn's free-text capture writes, or one carried
     * over from an older build — was ignored and a second row inserted, so
     * linking a spouse the product's own happy way left two cards for one
     * person and no way to remove either. Adoption is also what makes the whole
     * flow idempotent: re-linking the same spouse updates the row it already
     * wrote instead of stacking another.
     *
     * @param  array<string, mixed>  $data
     */
    private function upsertFamilyMemberRow(User $currentUser, ?int $linkedUserId, array $data): FamilyMember
    {
        $firstName = (string) ($data['first_name'] ?? '');
        $middleName = (string) ($data['middle_name'] ?? '');
        $lastName = (string) ($data['last_name'] ?? '');
        $fullName = trim(implode(' ', array_filter([$firstName, $middleName, $lastName])));

        $attributes = [
            'household_id' => $currentUser->household_id,
            'linked_user_id' => $linkedUserId,
            'relationship' => 'spouse',
            'first_name' => $firstName !== '' ? $firstName : null,
            'middle_name' => $middleName !== '' ? $middleName : null,
            'last_name' => $lastName !== '' ? $lastName : null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'gender' => $data['gender'] ?? null,
            'annual_income' => $data['annual_income'] ?? null,
            'is_dependent' => false,
            'name' => $fullName !== '' ? $fullName : null,
            'notes' => $data['notes'] ?? null,
        ];

        $existing = $this->adoptableSpouseRow($currentUser->id, $linkedUserId);

        if ($existing !== null) {
            // A field the caller did not supply must not wipe one the row
            // already holds — adoption enriches the record, it does not reset it.
            $existing->fill(array_filter(
                $attributes,
                static fn ($value) => $value !== null && $value !== '',
            ));
            // Only ever set a link, never clear one. An invitation re-sent over
            // a row that is ALREADY linked (the invitee accepted, then the
            // caller edited the card) must not tear the accepted link down.
            if ($linkedUserId !== null) {
                $existing->linked_user_id = $linkedUserId;
            }
            $existing->save();

            return $existing;
        }

        return FamilyMember::create(['user_id' => $currentUser->id] + $attributes);
    }

    private function createReciprocalFamilyMember(User $newSpouseUser, User $currentUser): FamilyMember
    {
        // Read the user's own name columns rather than splitting the display
        // name back apart. `name` is derived FROM these three
        // (User::getNameAttribute), so exploding it on spaces threw away the
        // middle name and mis-split any double-barrelled or multi-word surname
        // — for the one record the spouse sees of their partner (W-0112).
        $attributes = [
            'household_id' => $newSpouseUser->household_id,
            'linked_user_id' => $currentUser->id,
            'relationship' => 'spouse',
            'first_name' => $currentUser->first_name ?: null,
            'middle_name' => $currentUser->middle_name ?: null,
            'last_name' => $currentUser->surname ?: null,
            'date_of_birth' => $currentUser->date_of_birth,
            'gender' => $currentUser->gender,
            'annual_income' => $currentUser->annual_employment_income ?? 0,
            'is_dependent' => false,
            'name' => $currentUser->name,
        ];

        // The far side needs the same adoption rule: the spouse's own account
        // can already hold an unlinked row for their partner.
        $existing = $this->adoptableSpouseRow($newSpouseUser->id, $currentUser->id);

        if ($existing !== null) {
            $existing->fill(array_filter(
                $attributes,
                static fn ($value) => $value !== null && $value !== '',
            ));
            $existing->linked_user_id = $currentUser->id;
            $existing->save();

            return $existing;
        }

        return FamilyMember::create(['user_id' => $newSpouseUser->id] + $attributes);
    }

    /**
     * The spouse row on `$userId` that this link should take over: one already
     * pointing at `$linkedUserId` (re-link, idempotent) or one pointing at
     * nobody (the orphan). A row pointing at a DIFFERENT account is somebody
     * else's link and is never touched — the caller has already refused that
     * case with a collision.
     */
    private function adoptableSpouseRow(int $userId, ?int $linkedUserId): ?FamilyMember
    {
        return FamilyMember::where('user_id', $userId)
            ->where('relationship', 'spouse')
            ->where(function ($query) use ($linkedUserId) {
                $query->whereNull('linked_user_id');
                // `orWhere('linked_user_id', null)` compiles to `= NULL`, which
                // is never true — guard rather than rely on that accident.
                if ($linkedUserId !== null) {
                    $query->orWhere('linked_user_id', $linkedUserId);
                }
            })
            // Non-null first: where a household already holds the real linked
            // row, that is the one to keep current. The orphan beside it is the
            // repair command's job, not something to silently overwrite here.
            ->orderByRaw('linked_user_id IS NULL')
            ->first();
    }

    /**
     * Record the caller's request to link, for the invitee to accept or decline.
     *
     * `requested_at` set and `responded_at` left NULL is what distinguishes a
     * real request from the forged rows this replaced: every one of the ten
     * `accepted` rows on the dev database at the time of the fix carried
     * `requested_at = NULL`, because no request was ever made — the service
     * wrote the acceptance itself.
     *
     * **W-0347 G2 — that NULL was the audit marker only until the release.**
     * `2026_08_24_130000_reask_spouse_permissions_nobody_granted` converts every row
     * carrying it into an unanswered request, stamping `requested_at` as it goes, so
     * after that migration runs no row carries the marker and the population is no
     * longer identifiable in the data. **If the legacy population has to be
     * enumerated — for the production census, or for anything that follows from it —
     * it must be enumerated BEFORE the migration runs.**
     */
    private function createPendingSpouseInvitation(int $requesterId, int $inviteeId): void
    {
        SpousePermission::updateOrCreate(
            ['user_id' => $requesterId, 'spouse_id' => $inviteeId],
            ['status' => 'pending', 'requested_at' => now(), 'responded_at' => null]
        );
    }

    /**
     * Tell the invitee somebody has asked to link, and that it is theirs to
     * decide.
     *
     * Deliberately NOT `SpouseAccountLinked` — that mail states the accounts
     * are linked and sharing is on, which is now false at this point in the
     * flow and was the notification a victim of the old behaviour would have
     * received as the only sign anything had happened. `SpousePermissionRequest`
     * is the existing notification for exactly this, already written, already
     * queued, and it links to the response screen.
     *
     * A failed send must not roll the invitation back: the invitee can still
     * find and answer it in the app, and losing the record would silently
     * re-open the caller's request next time they submit.
     */
    private function sendInvitationNotification(User $spouseUser, User $currentUser): bool
    {
        try {
            $spouseUser->notify(new SpousePermissionRequest($currentUser->name));

            return true;
        } catch (\Throwable $e) {
            Log::error('[SpouseLinkingService] SpousePermissionRequest notification failed: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Invite an address with no account to make one. W-0349.
     *
     * Replaces `sendAccountCreatedEmail()`, which sent a temporary password for
     * an account this service had just created. `SpouseInvitation` takes plain
     * strings rather than a `User` — on this path there is no user, and that is
     * the whole change.
     *
     * A failed send must not roll the family-member row back: the caller's own
     * record of their partner is theirs, and losing it would make them re-enter
     * the details with no explanation.
     */
    private function sendRegistrationInvitation(string $spouseEmail, User $currentUser): bool
    {
        try {
            Mail::to($spouseEmail)->send(new SpouseInvitation($spouseEmail, $currentUser->name));

            return true;
        } catch (\Throwable $e) {
            Log::error('[SpouseLinkingService] SpouseInvitation email failed: '.$e->getMessage());

            return false;
        }
    }
}
