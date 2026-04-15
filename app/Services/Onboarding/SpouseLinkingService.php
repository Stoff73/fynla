<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Mail\SpouseAccountCreated;
use App\Mail\SpouseAccountLinked;
use App\Models\FamilyMember;
use App\Models\SpousePermission;
use App\Models\User;
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
    ) {}

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
        $spouseEmail = (string) ($data['email'] ?? '');
        if ($spouseEmail === '') {
            throw new \InvalidArgumentException('Spouse email is required for linking.');
        }

        Log::info('[SpouseLinkingService] linkOrCreateSpouse called', [
            'current_user_id' => $currentUser->id,
            'current_marital_status' => $currentUser->marital_status,
            'spouse_email' => $spouseEmail,
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

        $spouseUser = User::where('email', $spouseEmail)->first();

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
            throw new \InvalidArgumentException('This user is already linked to another spouse.');
        }

        // Already linked to current user — make sure the FamilyMember
        // record exists on the current user side.
        if ($spouseUser->spouse_id === $currentUser->id) {
            $existing = FamilyMember::where('user_id', $currentUser->id)
                ->where('relationship', 'spouse')
                ->first();

            if ($existing) {
                return [
                    'family_member' => $existing,
                    'spouse_user' => $spouseUser,
                    'created_new_user' => false,
                    'already_linked' => true,
                    'email_sent' => false,
                    'temporary_password' => null,
                ];
            }

            $familyMember = $this->createFamilyMemberRow($currentUser, $spouseUser->id, $data);

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
                throw new \RuntimeException('Spouse was linked to another user during transaction.');
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

            $familyMember = $this->createFamilyMemberRow($currentUser, $lockedSpouse->id, $data);
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
            $fullName = trim($firstName.' '.$lastName);

            $spouseUser = User::create([
                'first_name' => $firstName,
                'surname' => $lastName,
                'name' => $fullName !== '' ? $fullName : null,
                'email' => $spouseEmail,
                'password' => Hash::make($temporaryPassword),
                'must_change_password' => true,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'gender' => $data['gender'] ?? null,
                'marital_status' => $maritalStatus,
                'spouse_id' => $currentUser->id,
                'household_id' => $currentUser->household_id,
                'is_primary_account' => false,
                'national_insurance_number' => $data['national_insurance_number'] ?? null,
                'annual_employment_income' => $data['annual_income'] ?? 0,
                'address_line_1' => $currentUser->address_line_1,
                'address_line_2' => $currentUser->address_line_2,
                'city' => $currentUser->city,
                'county' => $currentUser->county,
                'postcode' => $currentUser->postcode,
            ]);

            $currentUser->spouse_id = $spouseUser->id;
            $currentUser->marital_status = $maritalStatus;
            $currentUser->save();

            $this->cacheInvalidation->invalidateForUserAndSpouse($currentUser->id, $spouseUser->id);
            $this->createSpousePermissions($currentUser->id, $spouseUser->id);

            $familyMember = $this->createFamilyMemberRow($currentUser, $spouseUser->id, $data);
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
     * @param  array<string, mixed>  $data
     */
    private function createFamilyMemberRow(User $currentUser, int $linkedUserId, array $data): FamilyMember
    {
        $firstName = (string) ($data['first_name'] ?? '');
        $middleName = (string) ($data['middle_name'] ?? '');
        $lastName = (string) ($data['last_name'] ?? '');
        $fullName = trim(implode(' ', array_filter([$firstName, $middleName, $lastName])));

        return FamilyMember::create([
            'user_id' => $currentUser->id,
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
        ]);
    }

    private function createReciprocalFamilyMember(User $newSpouseUser, User $currentUser): FamilyMember
    {
        $nameParts = array_values(array_filter(explode(' ', (string) $currentUser->name)));
        $currentFirst = $nameParts[0] ?? '';
        $currentLast = $nameParts[count($nameParts) - 1] ?? '';

        return FamilyMember::create([
            'user_id' => $newSpouseUser->id,
            'household_id' => $newSpouseUser->household_id,
            'linked_user_id' => $currentUser->id,
            'relationship' => 'spouse',
            'first_name' => $currentFirst !== '' ? $currentFirst : null,
            'last_name' => $currentLast !== '' && $currentLast !== $currentFirst ? $currentLast : null,
            'date_of_birth' => $currentUser->date_of_birth,
            'gender' => $currentUser->gender,
            'annual_income' => $currentUser->annual_employment_income ?? 0,
            'is_dependent' => false,
            'name' => $currentUser->name,
        ]);
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
