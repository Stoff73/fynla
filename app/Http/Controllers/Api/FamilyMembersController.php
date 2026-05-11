<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFamilyMemberRequest;
use App\Http\Requests\UpdateFamilyMemberRequest;
use App\Http\Traits\SanitizedErrorResponse;
use App\Mail\SpouseAccountCreated;
use App\Mail\SpouseAccountLinked;
use App\Models\FamilyMember;
use App\Models\SpousePermission;
use App\Models\User;
use App\Services\Cache\CacheInvalidationService;
use App\Services\UserProfile\UserProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

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
        if ($data['relationship'] === 'child' && $user->spouse_id) {
            $duplicateInUserRecords = FamilyMember::where('user_id', $user->id)
                ->where('relationship', 'child')
                ->where('first_name', $data['first_name'])
                ->where('last_name', $data['last_name'])
                ->where('date_of_birth', $data['date_of_birth'])
                ->exists();

            $duplicateInSpouseRecords = FamilyMember::where('user_id', $user->spouse_id)
                ->where('relationship', 'child')
                ->where('first_name', $data['first_name'])
                ->where('last_name', $data['last_name'])
                ->where('date_of_birth', $data['date_of_birth'])
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
     * Handle spouse creation or linking
     */
    private function handleSpouseCreation($currentUser, array $data): JsonResponse
    {
        $spouseEmail = $data['email'];

        Log::info('handleSpouseCreation called', [
            'current_user_id' => $currentUser->id,
            'current_user_email' => $currentUser->email,
            'current_user_spouse_id' => $currentUser->spouse_id,
            'spouse_email' => $spouseEmail,
        ]);

        // Check if spouse already has an account
        $spouseUser = User::where('email', $spouseEmail)->first();

        Log::info('Spouse user lookup result', [
            'found' => $spouseUser ? 'yes' : 'no',
            'spouse_user_id' => $spouseUser?->id,
            'spouse_user_spouse_id' => $spouseUser?->spouse_id,
        ]);

        if ($spouseUser) {
            // Spouse already exists - link the accounts
            if ($spouseUser->id === $currentUser->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot add yourself as a spouse',
                ], 422);
            }

            // Check if spouse is linked to a DIFFERENT user (not the current user)
            if ($spouseUser->spouse_id && $spouseUser->spouse_id !== $currentUser->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'This user is already linked to another spouse',
                ], 422);
            }

            // If already linked to current user, check if family member record exists
            if ($spouseUser->spouse_id === $currentUser->id) {
                // Already linked - check if family member record exists
                $existingFamilyMember = FamilyMember::where('user_id', $currentUser->id)
                    ->where('relationship', 'spouse')
                    ->first();

                if ($existingFamilyMember) {
                    // Family member record already exists, return it
                    return response()->json([
                        'success' => true,
                        'message' => 'Spouse is already linked',
                        'data' => [
                            'family_member' => $existingFamilyMember,
                            'spouse_user' => $spouseUser,
                            'linked' => true,
                            'already_existed' => true,
                        ],
                    ], 200);
                }

                // Linked but family member record missing - create it
                $fullName = trim(($data['first_name'] ?? '').' '.(isset($data['middle_name']) && $data['middle_name'] ? $data['middle_name'].' ' : '').($data['last_name'] ?? ''));
                $familyMember = FamilyMember::create([
                    'user_id' => $currentUser->id,
                    'household_id' => $currentUser->household_id,
                    'linked_user_id' => $spouseUser->id,
                    'relationship' => 'spouse',
                    'first_name' => $data['first_name'],
                    'middle_name' => $data['middle_name'] ?? null,
                    'last_name' => $data['last_name'],
                    'date_of_birth' => $data['date_of_birth'] ?? null,
                    'gender' => $data['gender'] ?? null,
                    'national_insurance_number' => $data['national_insurance_number'] ?? null,
                    'annual_income' => $data['annual_income'] ?? null,
                    'is_dependent' => $data['is_dependent'] ?? false,
                    'notes' => $data['notes'] ?? null,
                    'name' => $fullName,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Spouse family member record created (accounts already linked)',
                    'data' => [
                        'family_member' => $familyMember,
                        'spouse_user' => $spouseUser,
                        'linked' => true,
                        'record_created' => true,
                    ],
                ], 201);
            }

            // Link both users inside a transaction with pessimistic locking
            $familyMember = DB::transaction(function () use ($currentUser, $spouseUser, $data) {
                // Lock spouse row to prevent concurrent linking by another user
                $spouseUser = User::lockForUpdate()->find($spouseUser->id);
                if ($spouseUser->spouse_id && $spouseUser->spouse_id !== $currentUser->id) {
                    return null;
                }

                $currentUser->spouse_id = $spouseUser->id;
                $currentUser->marital_status = 'married';
                $currentUser->save();

                $spouseUser->spouse_id = $currentUser->id;
                $spouseUser->marital_status = 'married';
                // Update spouse user's income if provided in family member data
                if (isset($data['annual_income']) && $data['annual_income'] > 0) {
                    $spouseUser->annual_employment_income = $data['annual_income'];
                }
                // Copy address from current user if spouse doesn't have one
                if (! $spouseUser->address_line_1 && $currentUser->address_line_1) {
                    $spouseUser->address_line_1 = $currentUser->address_line_1;
                    $spouseUser->address_line_2 = $currentUser->address_line_2;
                    $spouseUser->city = $currentUser->city;
                    $spouseUser->county = $currentUser->county;
                    $spouseUser->postcode = $currentUser->postcode;
                }
                $spouseUser->save();

                // Clear cached protection analysis for both users since spouse linkage affects completeness
                $this->cacheInvalidation->invalidateForUserAndSpouse($currentUser->id, $spouseUser->id);

                // Create bidirectional spouse data sharing permissions
                SpousePermission::updateOrCreate(
                    [
                        'user_id' => $currentUser->id,
                        'spouse_id' => $spouseUser->id,
                    ],
                    [
                        'status' => 'accepted',
                        'responded_at' => now(),
                    ]
                );

                SpousePermission::updateOrCreate(
                    [
                        'user_id' => $spouseUser->id,
                        'spouse_id' => $currentUser->id,
                    ],
                    [
                        'status' => 'accepted',
                        'responded_at' => now(),
                    ]
                );

                // Create family member record for current user
                $fullName = trim(($data['first_name'] ?? '').' '.(isset($data['middle_name']) && $data['middle_name'] ? $data['middle_name'].' ' : '').($data['last_name'] ?? ''));
                $familyMember = FamilyMember::create([
                    'user_id' => $currentUser->id,
                    'household_id' => $currentUser->household_id,
                    'linked_user_id' => $spouseUser->id,
                    'relationship' => 'spouse',
                    'first_name' => $data['first_name'],
                    'middle_name' => $data['middle_name'] ?? null,
                    'last_name' => $data['last_name'],
                    'date_of_birth' => $data['date_of_birth'] ?? null,
                    'gender' => $data['gender'] ?? null,
                    'national_insurance_number' => $data['national_insurance_number'] ?? null,
                    'annual_income' => $data['annual_income'] ?? null,
                    'is_dependent' => $data['is_dependent'] ?? false,
                    'notes' => $data['notes'] ?? null,
                    'name' => $fullName,
                ]);

                // Create reciprocal family member record for spouse
                $currentUserNameParts = explode(' ', $currentUser->name);
                $currentUserFirstName = $currentUserNameParts[0] ?? '';
                $currentUserLastName = implode(' ', array_slice($currentUserNameParts, 1)) ?: '';

                FamilyMember::create([
                    'user_id' => $spouseUser->id,
                    'household_id' => $spouseUser->household_id,
                    'linked_user_id' => $currentUser->id,
                    'relationship' => 'spouse',
                    'first_name' => $currentUserFirstName,
                    'last_name' => $currentUserLastName,
                    'date_of_birth' => $currentUser->date_of_birth,
                    'gender' => $currentUser->gender,
                    'national_insurance_number' => $currentUser->national_insurance_number,
                    'annual_income' => $currentUser->employment_income ?? 0,
                    'is_dependent' => false,
                    'name' => $currentUser->name,
                ]);

                return $familyMember;
            });

            // Race condition: spouse was linked by another user during our transaction
            if ($familyMember === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'This user is already linked to another spouse',
                ], 422);
            }

            // Send email notification to spouse (outside transaction)
            try {
                Mail::to($spouseUser->email)->send(new SpouseAccountLinked($spouseUser, $currentUser));
            } catch (\Exception $e) {
                Log::error('Failed to send spouse account linked email: '.$e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Spouse account linked successfully',
                'data' => [
                    'family_member' => $familyMember,
                    'spouse_user' => $spouseUser,
                    'linked' => true,
                ],
            ], 201);
        }

        // Spouse doesn't exist - create new user account inside a transaction
        $temporaryPassword = Str::random(16);

        [$familyMember, $spouseUser] = DB::transaction(function () use ($currentUser, $data, $spouseEmail, $temporaryPassword) {
            // Construct full name from name parts
            $fullName = trim(($data['first_name'] ?? '').' '.
                (isset($data['middle_name']) && $data['middle_name'] ? $data['middle_name'].' ' : '').
                ($data['last_name'] ?? ''));

            $spouseUser = User::create([
                'first_name' => $data['first_name'] ?? '',
                'surname' => $data['last_name'] ?? '',
                'name' => $fullName,
                'email' => $spouseEmail,
                'password' => Hash::make($temporaryPassword),
                'must_change_password' => true,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'gender' => $data['gender'] ?? null,
                'marital_status' => 'married',
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

            // Update current user
            $currentUser->spouse_id = $spouseUser->id;
            $currentUser->marital_status = 'married';
            $currentUser->save();

            $this->cacheInvalidation->invalidateForUserAndSpouse($currentUser->id, $spouseUser->id);

            // Create bidirectional spouse data sharing permissions
            SpousePermission::updateOrCreate(
                [
                    'user_id' => $currentUser->id,
                    'spouse_id' => $spouseUser->id,
                ],
                [
                    'status' => 'accepted',
                    'responded_at' => now(),
                ]
            );

            SpousePermission::updateOrCreate(
                [
                    'user_id' => $spouseUser->id,
                    'spouse_id' => $currentUser->id,
                ],
                [
                    'status' => 'accepted',
                    'responded_at' => now(),
                ]
            );

            // Create family member record for current user
            $fullName = trim(($data['first_name'] ?? '').' '.(isset($data['middle_name']) && $data['middle_name'] ? $data['middle_name'].' ' : '').($data['last_name'] ?? ''));
            $familyMember = FamilyMember::create([
                'user_id' => $currentUser->id,
                'household_id' => $currentUser->household_id,
                'linked_user_id' => $spouseUser->id,
                'relationship' => 'spouse',
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'last_name' => $data['last_name'],
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'gender' => $data['gender'] ?? null,
                'national_insurance_number' => $data['national_insurance_number'] ?? null,
                'annual_income' => $data['annual_income'] ?? null,
                'is_dependent' => $data['is_dependent'] ?? false,
                'notes' => $data['notes'] ?? null,
                'name' => $fullName,
            ]);

            // Create reciprocal family member record for new spouse
            $currentUserNameParts = explode(' ', $currentUser->name);
            $currentUserFirstName = $currentUserNameParts[0] ?? '';
            $currentUserLastName = implode(' ', array_slice($currentUserNameParts, 1)) ?: '';

            FamilyMember::create([
                'user_id' => $spouseUser->id,
                'household_id' => $spouseUser->household_id,
                'linked_user_id' => $currentUser->id,
                'relationship' => 'spouse',
                'first_name' => $currentUserFirstName,
                'last_name' => $currentUserLastName,
                'date_of_birth' => $currentUser->date_of_birth,
                'gender' => $currentUser->gender,
                'national_insurance_number' => $currentUser->national_insurance_number,
                'annual_income' => $currentUser->employment_income ?? 0,
                'is_dependent' => false,
                'name' => $currentUser->name,
            ]);

            return [$familyMember, $spouseUser];
        });

        // Send email to spouse with temporary password (outside transaction)
        $emailSent = false;
        try {
            Mail::to($spouseEmail)->send(new SpouseAccountCreated($spouseUser, $currentUser, $temporaryPassword));
            $emailSent = true;
        } catch (\Exception $e) {
            Log::error('Failed to send spouse account created email: '.$e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => $emailSent
                ? 'Spouse account created successfully. They will receive an email with login instructions.'
                : 'Spouse account created but email delivery failed. They can use the "Forgot Password" feature to set their password.',
            'data' => [
                'family_member' => $familyMember,
                'spouse_user' => $spouseUser,
                'created' => true,
                'email_sent' => $emailSent,
                'spouse_email' => $spouseEmail,
            ],
        ], 201);
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

        // If this is a spouse and user has a spouse_id, get the spouse's email
        if ($familyMember->relationship === 'spouse' && $user->spouse_id) {
            $spouse = User::find($user->spouse_id);
            $memberArray['email'] = $spouse ? $spouse->email : null;
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

        // Construct full name from name parts if provided
        if (isset($data['first_name']) || isset($data['last_name'])) {
            $firstName = $data['first_name'] ?? $familyMember->first_name ?? '';
            $middleName = $data['middle_name'] ?? $familyMember->middle_name ?? '';
            $lastName = $data['last_name'] ?? $familyMember->last_name ?? '';
            $data['name'] = trim($firstName.($middleName ? ' '.$middleName : '').' '.$lastName);
        }

        $familyMember->update($data);

        // If updating a spouse, sync relevant fields to the spouse user account
        if ($familyMember->relationship === 'spouse' && $user->spouse_id) {
            $spouseUser = User::find($user->spouse_id);
            if ($spouseUser) {
                $spouseUpdates = [];

                // Sync name if it was updated
                if (isset($data['name'])) {
                    $spouseUpdates['name'] = $data['name'];
                }

                // Sync date of birth if updated
                if (isset($data['date_of_birth'])) {
                    $spouseUpdates['date_of_birth'] = $data['date_of_birth'];
                }

                // Sync gender if updated
                if (isset($data['gender'])) {
                    $spouseUpdates['gender'] = $data['gender'];
                }

                // Sync income if updated
                if (isset($data['annual_income'])) {
                    $spouseUpdates['annual_employment_income'] = $data['annual_income'];
                }

                // Sync NI number if updated
                if (isset($data['national_insurance_number'])) {
                    $spouseUpdates['national_insurance_number'] = $data['national_insurance_number'];
                }

                if (! empty($spouseUpdates)) {
                    $spouseUser->update($spouseUpdates);

                    $this->cacheInvalidation->invalidateForUserAndSpouse($user->id, $spouseUser->id);
                }
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

        // If deleting a spouse, clear the spouse linkage and delete reciprocal record
        if ($familyMember->relationship === 'spouse' && $user->spouse_id) {
            $spouseUser = User::find($user->spouse_id);

            if ($spouseUser) {
                // Delete the reciprocal family_member record on spouse's account
                FamilyMember::where('user_id', $spouseUser->id)
                    ->where('relationship', 'spouse')
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
            }

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
