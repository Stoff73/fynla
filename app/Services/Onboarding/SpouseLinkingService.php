<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Exceptions\SpouseCollisionException;
use App\Mail\SpouseAccountCreated;
use App\Mail\SpouseAccountLinked;
use App\Models\FamilyMember;
use App\Models\SpousePermission;
use App\Models\User;
use App\Models\UserConsent;
use App\Services\Cache\CacheInvalidationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

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
 *      → link bidirectionally, create permissions, create family_member
 *        rows, send "account linked" email
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
     *   email_sent: bool,
     *   temporary_password: ?string
     * }
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

        return $this->createAndLinkNewSpouse($currentUser, $data, $spouseEmail, $maritalStatus);
    }

    /**
     * Spouse already has an account. Three sub-paths depending on
     * existing linkage:
     *   (a) linking to self → error
     *   (b) already linked to a different user → error
     *   (c) already linked to current user → ensure FamilyMember exists
     *   (d) not linked → link both, create permissions, send email
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

        // Not linked — link both sides inside a transaction with
        // pessimistic row-level lock on the spouse.
        $familyMember = DB::transaction(function () use ($currentUser, $spouseUser, $data, $maritalStatus) {
            $lockedSpouse = User::lockForUpdate()->find($spouseUser->id);
            if ($lockedSpouse->spouse_id && $lockedSpouse->spouse_id !== $currentUser->id) {
                throw new SpouseCollisionException('Spouse was linked to another user during transaction.');
            }

            $currentUser->spouse_id = $lockedSpouse->id;
            $currentUser->marital_status = $maritalStatus;
            $currentUser->save();

            $lockedSpouse->spouse_id = $currentUser->id;
            $lockedSpouse->marital_status = $maritalStatus;
            if (isset($data['annual_income']) && (float) $data['annual_income'] > 0) {
                $lockedSpouse->annual_employment_income = $data['annual_income'];
            }
            if (! $lockedSpouse->address_line_1 && $currentUser->address_line_1) {
                $lockedSpouse->address_line_1 = $currentUser->address_line_1;
                $lockedSpouse->address_line_2 = $currentUser->address_line_2;
                $lockedSpouse->city = $currentUser->city;
                $lockedSpouse->county = $currentUser->county;
                $lockedSpouse->postcode = $currentUser->postcode;
            }
            $lockedSpouse->save();

            $this->cacheInvalidation->invalidateForUserAndSpouse($currentUser->id, $lockedSpouse->id);
            $this->createSpousePermissions($currentUser->id, $lockedSpouse->id);

            $familyMember = $this->upsertFamilyMemberRow($currentUser, $lockedSpouse->id, $data);
            $this->createReciprocalFamilyMember($lockedSpouse, $currentUser);

            return $familyMember;
        });

        $emailSent = $this->sendLinkedEmail($spouseUser, $currentUser);

        return [
            'family_member' => $familyMember,
            'spouse_user' => $spouseUser,
            'created_new_user' => false,
            'already_linked' => false,
            'email_sent' => $emailSent,
            'temporary_password' => null,
        ];
    }

    /**
     * Spouse does not exist. Create a new User row with a random
     * temporary password, link, create permissions, send credentials
     * email. All DB writes inside a transaction; email is sent outside
     * the transaction so a mail failure does not roll back the linking.
     *
     * @param  array<string, mixed>  $data
     * @return array{family_member: FamilyMember, spouse_user: User, created_new_user: bool, already_linked: bool, email_sent: bool, temporary_password: ?string}
     */
    private function createAndLinkNewSpouse(
        User $currentUser,
        array $data,
        string $spouseEmail,
        string $maritalStatus
    ): array {
        $temporaryPassword = Str::random(16);

        [$familyMember, $spouseUser] = DB::transaction(function () use (
            $currentUser,
            $data,
            $spouseEmail,
            $temporaryPassword,
            $maritalStatus
        ) {
            $firstName = (string) ($data['first_name'] ?? '');
            $lastName = (string) ($data['last_name'] ?? '');

            $spouseUser = User::create([
                // The person's own details come from the ONE declared
                // correspondence, so a field the family-member row carries
                // cannot quietly fail to reach their account. `middle_name` did
                // exactly that while this list was written out by hand — the
                // card had it, the account did not (W-0112). No `name` key
                // either: `users` has no such column and User::isFillable('name')
                // is false, so passing one was discarded in silence.
                ...$this->userAttributesFrom($data),
                // Creation-only shaping the map does not carry: empty strings
                // rather than nulls for the name parts, and an explicit zero
                // income rather than the column default.
                'first_name' => $firstName,
                'surname' => $lastName,
                'annual_employment_income' => $data['annual_income'] ?? 0,
                'email' => $spouseEmail,
                'password' => Hash::make($temporaryPassword),
                'must_change_password' => true,
                'marital_status' => $maritalStatus,
                'spouse_id' => $currentUser->id,
                'household_id' => $currentUser->household_id,
                'is_primary_account' => false,
                'address_line_1' => $currentUser->address_line_1,
                'address_line_2' => $currentUser->address_line_2,
                'city' => $currentUser->city,
                'county' => $currentUser->county,
                'postcode' => $currentUser->postcode,
            ]);

            // A spouse account is a real login, so it needs the same ai_chat
            // consent registration grants — without it the Fyn gate answers 403
            // on every surface and the account is locked out of the product with
            // nothing to tap. Same basis as AuthController: the journey is chat.
            UserConsent::recordConsent($spouseUser->id, UserConsent::TYPE_AI_CHAT);

            $currentUser->spouse_id = $spouseUser->id;
            $currentUser->marital_status = $maritalStatus;
            $currentUser->save();

            $this->cacheInvalidation->invalidateForUserAndSpouse($currentUser->id, $spouseUser->id);
            $this->createSpousePermissions($currentUser->id, $spouseUser->id);

            $familyMember = $this->upsertFamilyMemberRow($currentUser, $spouseUser->id, $data);
            $this->createReciprocalFamilyMember($spouseUser, $currentUser);

            return [$familyMember, $spouseUser];
        });

        $emailSent = $this->sendAccountCreatedEmail($spouseUser, $currentUser, $temporaryPassword);

        return [
            'family_member' => $familyMember,
            'spouse_user' => $spouseUser,
            'created_new_user' => true,
            'already_linked' => false,
            'email_sent' => $emailSent,
            'temporary_password' => $temporaryPassword,
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
    private function upsertFamilyMemberRow(User $currentUser, int $linkedUserId, array $data): FamilyMember
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
            $existing->linked_user_id = $linkedUserId;
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
    private function adoptableSpouseRow(int $userId, int $linkedUserId): ?FamilyMember
    {
        return FamilyMember::where('user_id', $userId)
            ->where('relationship', 'spouse')
            ->where(function ($query) use ($linkedUserId) {
                $query->whereNull('linked_user_id')
                    ->orWhere('linked_user_id', $linkedUserId);
            })
            // Non-null first: where a household already holds the real linked
            // row, that is the one to keep current. The orphan beside it is the
            // repair command's job, not something to silently overwrite here.
            ->orderByRaw('linked_user_id IS NULL')
            ->first();
    }

    private function createSpousePermissions(int $userId, int $spouseId): void
    {
        SpousePermission::updateOrCreate(
            ['user_id' => $userId, 'spouse_id' => $spouseId],
            ['status' => 'accepted', 'responded_at' => now()]
        );

        SpousePermission::updateOrCreate(
            ['user_id' => $spouseId, 'spouse_id' => $userId],
            ['status' => 'accepted', 'responded_at' => now()]
        );
    }

    private function sendLinkedEmail(User $spouseUser, User $currentUser): bool
    {
        try {
            Mail::to($spouseUser->email)->send(new SpouseAccountLinked($spouseUser, $currentUser));

            return true;
        } catch (\Throwable $e) {
            Log::error('[SpouseLinkingService] SpouseAccountLinked email failed: '.$e->getMessage());

            return false;
        }
    }

    private function sendAccountCreatedEmail(User $spouseUser, User $currentUser, string $temporaryPassword): bool
    {
        try {
            Mail::to($spouseUser->email)->send(new SpouseAccountCreated($spouseUser, $currentUser, $temporaryPassword));

            return true;
        } catch (\Throwable $e) {
            Log::error('[SpouseLinkingService] SpouseAccountCreated email failed: '.$e->getMessage());

            return false;
        }
    }
}
