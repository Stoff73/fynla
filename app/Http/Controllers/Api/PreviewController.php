<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CriticalIllnessPolicy;
use App\Models\DBPension;
use App\Models\DCPension;
use App\Models\Estate\Liability;
use App\Models\FamilyMember;
use App\Models\IncomeProtectionPolicy;
use App\Models\Investment\Holding;
use App\Models\Investment\InvestmentAccount;
use App\Models\LifeInsurancePolicy;
use App\Models\Mortgage;
use App\Models\Property;
use App\Models\SavingsAccount;
use App\Models\StatePension;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PreviewController extends Controller
{
    /**
     * Available persona IDs
     */
    private const VALID_PERSONAS = [
        'young_family',
        'peak_earners',
        'entrepreneur',
        'young_saver',
        'retired_couple',
        'student',
        // Spouse personas (for spouse view toggle)
        'young_family_spouse',
        'peak_earners_spouse',
        'retired_couple_spouse',
    ];

    /**
     * Persona metadata for display in UI
     */
    private const PERSONA_METADATA = [
        'young_family' => [
            'id' => 'young_family',
            'name' => 'Emily & James Carter',
            'tagline' => 'Young family building their future',
            'description' => 'A young married couple in their early 30s with two children, mortgage, and workplace pensions.',
        ],
        'peak_earners' => [
            'id' => 'peak_earners',
            'name' => 'David & Sarah Mitchell',
            'tagline' => 'Peak earners planning ahead',
            'description' => 'A couple in their late 40s at peak earning capacity, with substantial assets and complex planning needs.',
        ],
        'entrepreneur' => [
            'id' => 'entrepreneur',
            'name' => 'Alex Chen',
            'tagline' => 'Entrepreneur with business interests',
            'description' => 'A 42-year-old business owner with complex income streams and business succession planning needs.',
        ],
        'young_saver' => [
            'id' => 'young_saver',
            'name' => 'John Morgan',
            'tagline' => 'Young professional building first-time buyer savings',
            'description' => 'A 24-year-old junior data analyst, renting and saving for a house deposit with a Lifetime ISA.',
        ],
        'retired_couple' => [
            'id' => 'retired_couple',
            'name' => 'Patricia & Harold Bennett',
            'tagline' => 'Retired couple with estate planning focus',
            'description' => 'A retired couple in their early 70s drawing DB pensions, focusing on IHT planning and gifting to grandchildren.',
        ],
        'student' => [
            'id' => 'student',
            'name' => 'Janice Taylor',
            'tagline' => 'University student building early financial habits',
            'description' => 'A 21-year-old Economics student with a student loan, Cash ISA, and Lifetime ISA for a future first home.',
        ],
        // Spouse personas (for spouse view toggle)
        'young_family_spouse' => [
            'id' => 'young_family_spouse',
            'name' => 'Emily Carter',
            'tagline' => 'Viewing as Emily Carter',
            'description' => 'Emily\'s individual view of the Carter family finances.',
        ],
        'peak_earners_spouse' => [
            'id' => 'peak_earners_spouse',
            'name' => 'Sarah Mitchell',
            'tagline' => 'Viewing as Sarah Mitchell',
            'description' => 'Sarah\'s individual view of the Mitchell family finances.',
        ],
        'retired_couple_spouse' => [
            'id' => 'retired_couple_spouse',
            'name' => 'Harold Bennett',
            'tagline' => 'Viewing as Harold Bennett',
            'description' => 'Harold\'s individual view of the Bennett family finances.',
        ],
    ];

    /**
     * Login as a preview user (no credentials required)
     *
     * POST /api/preview/login/{personaId}
     */
    public function login(Request $request, string $personaId): JsonResponse
    {
        if (! in_array($personaId, self::VALID_PERSONAS)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid persona ID',
            ], 400);
        }

        // CRITICAL SECURITY: Clear any existing session to prevent data leakage
        // This ensures that if a real user was logged in, their session is destroyed
        // before we log in the preview user
        if (\Illuminate\Support\Facades\Auth::guard('web')->check()) {
            \Illuminate\Support\Facades\Auth::guard('web')->logout();
        }

        // Invalidate the session and regenerate CSRF token (if session is available)
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        // Find the preview user for this persona
        $previewUser = User::where('is_preview_user', true)
            ->where('preview_persona_id', $personaId)
            ->first();

        if (! $previewUser) {
            return response()->json([
                'success' => false,
                'message' => 'Preview user not found. Please run php artisan db:seed --class=PreviewUserSeeder',
            ], 404);
        }

        // Create a Sanctum token for the preview user
        $token = $previewUser->createToken('preview-access')->plainTextToken;

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $previewUser->id,
                'first_name' => $previewUser->first_name,
                'surname' => $previewUser->surname,
                'name' => $previewUser->name,
                'email' => $previewUser->email,
                'is_preview_user' => true,
                'preview_persona_id' => $personaId,
            ],
            'persona' => self::PERSONA_METADATA[$personaId] ?? null,
        ]);
    }

    /**
     * Switch to a different preview persona
     *
     * POST /api/preview/switch/{personaId}
     */
    public function switch(Request $request, string $personaId): JsonResponse
    {
        $currentUser = $request->user();

        if (! $currentUser || ! $currentUser->is_preview_user) {
            return response()->json([
                'success' => false,
                'message' => 'Not currently in preview mode',
            ], 400);
        }

        if (! in_array($personaId, self::VALID_PERSONAS)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid persona ID',
            ], 400);
        }

        // Find the new preview user
        $newPreviewUser = User::where('is_preview_user', true)
            ->where('preview_persona_id', $personaId)
            ->first();

        if (! $newPreviewUser) {
            return response()->json([
                'success' => false,
                'message' => 'Preview user not found for this persona',
            ], 404);
        }

        // Revoke current user's tokens
        $currentUser->tokens()->delete();

        // Create new token for the new persona
        $token = $newPreviewUser->createToken('preview-access')->plainTextToken;

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $newPreviewUser->id,
                'first_name' => $newPreviewUser->first_name,
                'surname' => $newPreviewUser->surname,
                'name' => $newPreviewUser->name,
                'email' => $newPreviewUser->email,
                'is_preview_user' => true,
                'preview_persona_id' => $personaId,
            ],
            'persona' => self::PERSONA_METADATA[$personaId] ?? null,
        ]);
    }

    /**
     * Exit preview mode (logout)
     *
     * POST /api/preview/exit
     */
    public function exit(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user) {
            // Only delete tokens if user is a preview user
            if ($user->is_preview_user) {
                $user->tokens()->delete();
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Exited preview mode',
        ]);
    }

    /**
     * Get available personas with metadata
     *
     * GET /api/preview/personas
     */
    public function getPersonas(): JsonResponse
    {
        return response()->json([
            'personas' => array_values(self::PERSONA_METADATA),
        ]);
    }

    /**
     * Seed persona data to authenticated user's account
     *
     * POST /api/user/seed-persona-data
     */
    public function seedPersonaData(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'persona_id' => 'required|string|in:'.implode(',', self::VALID_PERSONAS),
            'create_spouse_account' => 'boolean',
        ]);

        $user = $request->user();
        $personaData = $this->loadPersonaJson($validated['persona_id']);

        if (! $personaData) {
            return response()->json([
                'success' => false,
                'message' => 'Persona data not found',
            ], 404);
        }

        try {
            DB::transaction(function () use ($user, $personaData, $validated) {
                // Seed all data with is_demo_origin = true flag
                $this->seedUserProfile($user, $personaData);
                $this->seedProperties($user, $personaData['properties'] ?? []);
                $this->seedMortgages($user, $personaData['mortgages'] ?? []);
                $this->seedSavingsAccounts($user, $personaData['savings_accounts'] ?? []);
                $this->seedInvestmentAccounts($user, $personaData['investment_accounts'] ?? []);
                $this->seedDCPensions($user, $personaData['dc_pensions'] ?? []);
                $this->seedDBPensions($user, $personaData['db_pensions'] ?? []);
                $this->seedStatePension($user, $personaData['state_pension'] ?? null);
                $this->seedLifeInsurancePolicies($user, $personaData['life_insurance_policies'] ?? []);
                $this->seedCriticalIllnessPolicies($user, $personaData['critical_illness_policies'] ?? []);
                $this->seedIncomeProtectionPolicies($user, $personaData['income_protection_policies'] ?? []);
                $this->seedLiabilities($user, $personaData['liabilities'] ?? []);
                $this->seedFamilyMembers($user, $personaData['family_members'] ?? []);

                // Update user with preview origin tracking
                $user->update([
                    'preview_persona_kept' => $validated['persona_id'],
                    'guidance_active' => true,
                    'guidance_current_step' => 0,
                    'guidance_completed_steps' => json_encode([]),
                    'guidance_skipped_steps' => json_encode([]),
                    'guidance_version' => '1.0.0',
                ]);

                // Handle spouse account creation if requested
                if (($validated['create_spouse_account'] ?? false) && isset($personaData['spouse'])) {
                    $this->createSpouseAccount($user, $personaData['spouse']);
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Persona data seeded successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to seed persona data: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get guidance status for authenticated user
     *
     * GET /api/user/guidance-status
     */
    public function getGuidanceStatus(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'guidance_active' => $user->guidance_active ?? false,
            'guidance_current_step' => $user->guidance_current_step ?? 0,
            'guidance_completed_steps' => json_decode($user->guidance_completed_steps ?? '[]', true),
            'guidance_skipped_steps' => json_decode($user->guidance_skipped_steps ?? '[]', true),
            'guidance_version' => $user->guidance_version ?? '1.0.0',
            'guidance_completed' => $user->guidance_completed ?? false,
        ]);
    }

    /**
     * Update guidance status for authenticated user
     *
     * POST /api/user/guidance-status
     */
    public function updateGuidanceStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'guidance_active' => 'required|boolean',
            'guidance_current_step' => 'required|integer|min:0',
            'guidance_completed_steps' => 'array',
            'guidance_skipped_steps' => 'array',
            'guidance_version' => 'nullable|string|max:10',
        ]);

        $user = $request->user();

        // Calculate if guidance is complete
        $totalSteps = 8; // Number of guidance steps
        $handledSteps = count($validated['guidance_completed_steps'] ?? []) +
                       count($validated['guidance_skipped_steps'] ?? []);
        $isComplete = $handledSteps >= $totalSteps;

        $user->update([
            'guidance_active' => $validated['guidance_active'],
            'guidance_current_step' => $validated['guidance_current_step'],
            'guidance_completed_steps' => json_encode($validated['guidance_completed_steps'] ?? []),
            'guidance_skipped_steps' => json_encode($validated['guidance_skipped_steps'] ?? []),
            'guidance_version' => $validated['guidance_version'] ?? '1.0.0',
            'guidance_completed' => $isComplete,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Guidance status updated successfully',
        ]);
    }

    /**
     * Load persona JSON file
     */
    private function loadPersonaJson(string $personaId): ?array
    {
        // Try loading from storage first (for production)
        $storagePath = "personas/{$personaId}.json";
        if (Storage::exists($storagePath)) {
            $content = Storage::get($storagePath);

            return json_decode($content, true);
        }

        // Fall back to resources directory (for development)
        $resourcePath = resource_path("js/data/personas/{$personaId}.json");
        if (file_exists($resourcePath)) {
            $content = file_get_contents($resourcePath);

            return json_decode($content, true);
        }

        return null;
    }

    /**
     * Seed user profile fields from persona data
     */
    private function seedUserProfile(User $user, array $data): void
    {
        $userData = $data['user'] ?? [];

        $profileFields = array_filter([
            'date_of_birth' => $userData['date_of_birth'] ?? null,
            'gender' => $userData['gender'] ?? null,
            'marital_status' => $userData['marital_status'] ?? null,
            'address_line_1' => $userData['address_line_1'] ?? null,
            'address_line_2' => $userData['address_line_2'] ?? null,
            'city' => $userData['city'] ?? null,
            'county' => $userData['county'] ?? null,
            'postcode' => $userData['postcode'] ?? null,
            'phone' => $userData['phone'] ?? null,
            'national_insurance_number' => $userData['national_insurance_number'] ?? null,
            'employment_status' => $userData['employment_status'] ?? null,
            'occupation' => $userData['occupation'] ?? null,
            'employer_name' => $userData['employer_name'] ?? null,
            'annual_salary' => $userData['annual_salary'] ?? null,
            'other_income' => $userData['other_income'] ?? null,
            'health_status' => $userData['health_status'] ?? null,
            'smoker_status' => $userData['smoker_status'] ?? null,
            'monthly_expenditure' => $userData['monthly_expenditure'] ?? null,
            'registration_source' => 'preview',
        ], fn ($value) => $value !== null);

        if (! empty($profileFields)) {
            $user->update($profileFields);
        }
    }

    /**
     * Seed properties
     */
    private function seedProperties(User $user, array $properties): void
    {
        foreach ($properties as $property) {
            Property::create(array_merge($property, [
                'user_id' => $user->id,
            ]));
        }
    }

    /**
     * Seed mortgages
     */
    private function seedMortgages(User $user, array $mortgages): void
    {
        // Get the user's first property for association
        $property = Property::where('user_id', $user->id)->first();

        foreach ($mortgages as $mortgage) {
            $mortgageData = array_merge($mortgage, [
                'user_id' => $user->id,
            ]);

            // Associate with property if available
            if ($property && ! isset($mortgageData['property_id'])) {
                $mortgageData['property_id'] = $property->id;
            }

            Mortgage::create($mortgageData);
        }
    }

    /**
     * Seed savings accounts
     */
    private function seedSavingsAccounts(User $user, array $accounts): void
    {
        foreach ($accounts as $account) {
            SavingsAccount::create(array_merge($account, [
                'user_id' => $user->id,
            ]));
        }
    }

    /**
     * Seed investment accounts with holdings
     */
    private function seedInvestmentAccounts(User $user, array $accounts): void
    {
        foreach ($accounts as $accountData) {
            $holdings = $accountData['holdings'] ?? [];
            unset($accountData['holdings']);

            $account = InvestmentAccount::create(array_merge($accountData, [
                'user_id' => $user->id,
            ]));

            // Seed holdings (map JSON field names to database field names)
            foreach ($holdings as $holding) {
                Holding::create([
                    'holdable_type' => InvestmentAccount::class,
                    'holdable_id' => $account->id,
                    'security_name' => $holding['holding_name'] ?? $holding['security_name'] ?? null,
                    'ticker' => $holding['ticker'] ?? null,
                    'isin' => $holding['isin'] ?? null,
                    'asset_type' => $holding['asset_type'] ?? null,
                    'quantity' => $holding['units'] ?? $holding['quantity'] ?? null,
                    'purchase_price' => $holding['initial_unit_cost'] ?? $holding['purchase_price'] ?? null,
                    'current_price' => $holding['current_unit_price'] ?? $holding['current_price'] ?? null,
                    'current_value' => $holding['current_value'] ?? null,
                    'allocation_percent' => $holding['allocation_percentage'] ?? $holding['allocation_percent'] ?? null,
                    'ocf_percent' => $holding['annual_fee'] ?? $holding['ocf_percent'] ?? null,
                    'cost_basis' => $holding['cost_basis'] ?? 0,
                    'dividend_yield' => $holding['dividend_yield'] ?? 0,
                    'purchase_date' => $holding['purchase_date'] ?? null,
                ]);
            }
        }
    }

    /**
     * Seed DC pensions
     */
    private function seedDCPensions(User $user, array $pensions): void
    {
        foreach ($pensions as $pension) {
            DCPension::create(array_merge($pension, [
                'user_id' => $user->id,
            ]));
        }
    }

    /**
     * Seed DB pensions
     */
    private function seedDBPensions(User $user, array $pensions): void
    {
        foreach ($pensions as $pension) {
            DBPension::create(array_merge($pension, [
                'user_id' => $user->id,
            ]));
        }
    }

    /**
     * Seed state pension
     */
    private function seedStatePension(User $user, ?array $pension): void
    {
        if (! $pension) {
            return;
        }

        StatePension::create(array_merge($pension, [
            'user_id' => $user->id,
        ]));
    }

    /**
     * Seed life insurance policies
     */
    private function seedLifeInsurancePolicies(User $user, array $policies): void
    {
        foreach ($policies as $policy) {
            LifeInsurancePolicy::create(array_merge($policy, [
                'user_id' => $user->id,
            ]));
        }
    }

    /**
     * Seed critical illness policies
     */
    private function seedCriticalIllnessPolicies(User $user, array $policies): void
    {
        foreach ($policies as $policy) {
            CriticalIllnessPolicy::create(array_merge($policy, [
                'user_id' => $user->id,
            ]));
        }
    }

    /**
     * Seed income protection policies
     */
    private function seedIncomeProtectionPolicies(User $user, array $policies): void
    {
        foreach ($policies as $policy) {
            IncomeProtectionPolicy::create(array_merge($policy, [
                'user_id' => $user->id,
            ]));
        }
    }

    /**
     * Seed liabilities
     */
    private function seedLiabilities(User $user, array $liabilities): void
    {
        foreach ($liabilities as $liability) {
            Liability::create(array_merge($liability, [
                'user_id' => $user->id,
            ]));
        }
    }

    /**
     * Seed family members
     */
    private function seedFamilyMembers(User $user, array $members): void
    {
        foreach ($members as $member) {
            // Construct name from first_name and last_name if not provided
            $name = $member['name'] ?? trim(($member['first_name'] ?? '').' '.($member['last_name'] ?? ''));

            FamilyMember::create([
                'user_id' => $user->id,
                'name' => $name,
                'first_name' => $member['first_name'] ?? null,
                'last_name' => $member['last_name'] ?? null,
                'relationship' => $member['relationship'] ?? null,
                'date_of_birth' => $member['date_of_birth'] ?? null,
                'is_dependent' => $member['is_dependent'] ?? false,
                'gender' => $member['gender'] ?? null,
                'annual_income' => $member['annual_income'] ?? null,
            ]);
        }
    }

    /**
     * Create spouse account from persona data
     */
    private function createSpouseAccount(User $primaryUser, array $spouseData): void
    {
        // Generate a random password for the spouse
        $temporaryPassword = Str::random(16);

        $spouse = User::create([
            'name' => $spouseData['name'],
            'email' => $spouseData['email'] ?? Str::slug($spouseData['name']).'@temp.fps.com',
            'password' => Hash::make($temporaryPassword),
            'date_of_birth' => $spouseData['date_of_birth'] ?? null,
            'gender' => $spouseData['gender'] ?? null,
            'marital_status' => 'married',
            'employment_status' => $spouseData['employment_status'] ?? null,
            'occupation' => $spouseData['occupation'] ?? null,
            'employer_name' => $spouseData['employer_name'] ?? null,
            'annual_salary' => $spouseData['annual_salary'] ?? null,
            'health_status' => $spouseData['health_status'] ?? null,
            'smoker_status' => $spouseData['smoker_status'] ?? null,
            'registration_source' => 'preview_spouse',
            'preview_persona_kept' => $primaryUser->preview_persona_kept,
        ]);

        // Link accounts
        $primaryUser->update(['spouse_id' => $spouse->id]);
        $spouse->update(['spouse_id' => $primaryUser->id]);

        // Create spouse's data from persona if available
        if (isset($spouseData['dc_pensions'])) {
            $this->seedDCPensions($spouse, $spouseData['dc_pensions']);
        }

        if (isset($spouseData['savings_accounts'])) {
            $this->seedSavingsAccounts($spouse, $spouseData['savings_accounts']);
        }

        if (isset($spouseData['investment_accounts'])) {
            $this->seedInvestmentAccounts($spouse, $spouseData['investment_accounts']);
        }

        if (isset($spouseData['life_insurance_policies'])) {
            $this->seedLifeInsurancePolicies($spouse, $spouseData['life_insurance_policies']);
        }
    }
}
