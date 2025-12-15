<?php

declare(strict_types=1);

namespace Database\Seeders;

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
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PreviewUserSeeder extends Seeder
{
    /**
     * Valid persona IDs that can be seeded.
     */
    private const PERSONAS = [
        'young_family',
        'peak_earners',
        'widow',
        'entrepreneur',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::PERSONAS as $personaId) {
            $this->seedPersona($personaId);
        }

        $this->command->info('Preview users seeded successfully.');
    }

    /**
     * Seed a single persona with all their data.
     */
    private function seedPersona(string $personaId): void
    {
        $jsonPath = resource_path("js/data/personas/{$personaId}.json");

        if (!file_exists($jsonPath)) {
            $this->command->warn("Persona file not found: {$jsonPath}");
            return;
        }

        $data = json_decode(file_get_contents($jsonPath), true);

        if (!$data) {
            $this->command->error("Failed to parse persona JSON: {$personaId}");
            return;
        }

        // Check if preview user already exists
        $existingUser = User::where('is_preview_user', true)
            ->where('preview_persona_id', $personaId)
            ->first();

        if ($existingUser) {
            $this->command->info("Preview user for {$personaId} already exists, skipping.");
            return;
        }

        $this->command->info("Seeding persona: {$personaId}");

        // Create primary user
        $user = $this->createUser($data['user'], $personaId);

        // Create spouse if exists
        $spouse = null;
        if (!empty($data['spouse'])) {
            $spouse = $this->createSpouse($data['spouse'], $personaId, $user);
        }

        // Create family members
        $this->createFamilyMembers($user, $data['family_members'] ?? []);

        // Create properties and mortgages
        $propertyMap = $this->createProperties($user, $spouse, $data['properties'] ?? []);
        $this->createMortgages($user, $spouse, $data['mortgages'] ?? [], $propertyMap);

        // Create savings accounts
        $this->createSavingsAccounts($user, $spouse, $data['savings_accounts'] ?? []);

        // Create investment accounts with holdings
        $this->createInvestmentAccounts($user, $spouse, $data['investment_accounts'] ?? []);

        // Create pensions
        $this->createDCPensions($user, $spouse, $data['dc_pensions'] ?? []);
        $this->createDBPensions($user, $spouse, $data['db_pensions'] ?? []);

        // Create insurance policies
        $this->createLifeInsurancePolicies($user, $spouse, $data['life_insurance_policies'] ?? []);
        $this->createCriticalIllnessPolicies($user, $spouse, $data['critical_illness_policies'] ?? []);
        $this->createIncomeProtectionPolicies($user, $spouse, $data['income_protection_policies'] ?? []);

        // Create liabilities
        $this->createLiabilities($user, $spouse, $data['liabilities'] ?? []);

        $this->command->info("  Created user: {$user->name} ({$user->email})");
        if ($spouse) {
            $this->command->info("  Created spouse: {$spouse->name} ({$spouse->email})");
        }
    }

    /**
     * Create the primary preview user.
     */
    private function createUser(array $userData, string $personaId): User
    {
        $user = new User();

        // Set preview user flags (bypassing guarded)
        $user->is_preview_user = true;
        $user->preview_persona_id = $personaId;

        // Basic info
        $user->name = ($userData['first_name'] ?? '') . ' ' . ($userData['last_name'] ?? '');
        $user->email = "preview_{$personaId}@fynla.local";
        $user->password = Hash::make(Str::random(32)); // Random password - never used

        // Profile info (using correct column names)
        $user->date_of_birth = $userData['date_of_birth'] ?? null;
        $user->gender = $userData['gender'] ?? null;
        $user->marital_status = $userData['marital_status'] ?? 'single';
        $user->employment_status = $userData['employment_status'] ?? null;
        $user->occupation = $userData['occupation'] ?? null;
        $user->employer = $userData['employer_name'] ?? null;
        $user->annual_employment_income = $userData['annual_income'] ?? null;
        $user->target_retirement_age = $userData['target_retirement_age'] ?? 65;
        $user->monthly_expenditure = $userData['monthly_expenditure'] ?? null;
        $user->health_status = $userData['health_status'] ?? null;
        $user->smoking_status = $userData['smoking_status'] ?? null;

        // Address
        if (!empty($userData['address'])) {
            $user->address_line_1 = $userData['address']['line_1'] ?? null;
            $user->address_line_2 = $userData['address']['line_2'] ?? null;
            $user->city = $userData['address']['city'] ?? null;
            $user->county = $userData['address']['county'] ?? null;
            $user->postcode = $userData['address']['postcode'] ?? null;
        }

        $user->save();

        return $user;
    }

    /**
     * Create the spouse as a separate preview user.
     */
    private function createSpouse(array $spouseData, string $personaId, User $primaryUser): User
    {
        $spouse = new User();

        // Set preview user flags
        $spouse->is_preview_user = true;
        $spouse->preview_persona_id = "{$personaId}_spouse";

        // Basic info
        $spouse->name = ($spouseData['first_name'] ?? '') . ' ' . ($spouseData['last_name'] ?? '');
        $spouse->email = "preview_{$personaId}_spouse@fynla.local";
        $spouse->password = Hash::make(Str::random(32));

        // Profile info (using correct column names)
        $spouse->date_of_birth = $spouseData['date_of_birth'] ?? null;
        $spouse->gender = $spouseData['gender'] ?? null;
        $spouse->marital_status = 'married';
        $spouse->employment_status = $spouseData['employment_status'] ?? null;
        $spouse->occupation = $spouseData['occupation'] ?? null;
        $spouse->employer = $spouseData['employer_name'] ?? null;
        $spouse->annual_employment_income = $spouseData['annual_income'] ?? null;

        $spouse->save();

        // Link spouse to primary user
        $primaryUser->spouse_id = $spouse->id;
        $primaryUser->save();

        $spouse->spouse_id = $primaryUser->id;
        $spouse->save();

        return $spouse;
    }

    /**
     * Create family members.
     */
    private function createFamilyMembers(User $user, array $familyMembers): void
    {
        foreach ($familyMembers as $member) {
            $firstName = $member['first_name'] ?? '';
            $lastName = $member['last_name'] ?? '';

            FamilyMember::create([
                'user_id' => $user->id,
                'name' => trim("{$firstName} {$lastName}"),
                'first_name' => $firstName,
                'last_name' => $lastName,
                'relationship' => $member['relationship'] ?? 'other_dependent',
                'date_of_birth' => $member['date_of_birth'] ?? null,
                'is_dependent' => $member['is_dependent'] ?? false,
            ]);
        }
    }

    /**
     * Create properties and return a map of old ID to new ID.
     */
    private function createProperties(User $user, ?User $spouse, array $properties): array
    {
        $propertyMap = [];

        foreach ($properties as $prop) {
            $isJoint = ($prop['ownership_type'] ?? 'individual') === 'joint';

            // Parse the address if provided as a single string
            $addressParts = $this->parseAddress($prop['address'] ?? '');

            $property = Property::create([
                'user_id' => $user->id,
                'property_type' => $prop['property_type'] ?? 'main_residence',
                'current_value' => $prop['current_value'] ?? 0,
                'purchase_price' => $prop['purchase_price'] ?? null,
                'purchase_date' => $prop['purchase_date'] ?? null,
                'ownership_type' => $prop['ownership_type'] ?? 'individual',
                'ownership_percentage' => $prop['ownership_percentage'] ?? 100,
                'monthly_rental_income' => $prop['estimated_rental_value'] ?? null,
                'joint_owner_id' => $isJoint && $spouse ? $spouse->id : null,
                'address_line_1' => $addressParts['line_1'],
                'city' => $addressParts['city'],
                'county' => $addressParts['county'] ?: null,
                'postcode' => $addressParts['postcode'] ?: null,
            ]);

            $propertyMap[$prop['id']] = $property->id;
        }

        return $propertyMap;
    }

    /**
     * Parse a comma-separated address into parts.
     * Handles formats like:
     * - "42 Oak Avenue, Birmingham, B15 2TT" (3 parts)
     * - "8 Church Lane, Bourton-on-the-Water, Gloucestershire, GL54 2BY" (4 parts)
     */
    private function parseAddress(string $address): array
    {
        $parts = array_map('trim', explode(',', $address));
        $count = count($parts);

        // Last part is always the postcode (if it looks like a UK postcode)
        $postcode = '';
        $city = '';
        $county = '';
        $line1 = $parts[0] ?? '';

        if ($count >= 3) {
            // Check if last part looks like a UK postcode
            $lastPart = end($parts);
            if (preg_match('/^[A-Z]{1,2}\d[A-Z\d]?\s*\d[A-Z]{2}$/i', $lastPart)) {
                $postcode = $lastPart;
                if ($count === 3) {
                    $city = $parts[1] ?? '';
                } elseif ($count >= 4) {
                    $city = $parts[1] ?? '';
                    $county = $parts[$count - 2] ?? '';
                }
            } else {
                // No postcode at end
                $city = $parts[1] ?? '';
                $county = $parts[2] ?? '';
            }
        } elseif ($count === 2) {
            $city = $parts[1] ?? '';
        }

        return [
            'line_1' => $line1,
            'city' => $city,
            'county' => $county,
            'postcode' => $postcode,
        ];
    }

    /**
     * Create mortgages linked to properties.
     */
    private function createMortgages(User $user, ?User $spouse, array $mortgages, array $propertyMap): void
    {
        foreach ($mortgages as $mort) {
            $propertyId = $propertyMap[$mort['property_id']] ?? null;
            $isJoint = ($mort['ownership_type'] ?? 'individual') === 'joint';

            Mortgage::create([
                'user_id' => $user->id,
                'property_id' => $propertyId,
                'lender_name' => $mort['lender_name'] ?? '',
                'outstanding_balance' => $mort['outstanding_balance'] ?? 0,
                'original_loan_amount' => $mort['original_amount'] ?? null,
                'mortgage_type' => $mort['mortgage_type'] ?? 'repayment',
                'interest_rate' => $mort['interest_rate'] ?? null,
                'rate_type' => $mort['rate_type'] ?? 'fixed',
                'rate_fix_end_date' => $mort['fixed_rate_end_date'] ?? null,
                'monthly_payment' => $mort['monthly_payment'] ?? null,
                'remaining_term_months' => $mort['remaining_term_months'] ?? null,
                'start_date' => $mort['mortgage_start_date'] ?? null,
                'ownership_type' => $mort['ownership_type'] ?? 'individual',
                'joint_owner_id' => $isJoint && $spouse ? $spouse->id : null,
            ]);
        }
    }

    /**
     * Create savings accounts.
     */
    private function createSavingsAccounts(User $user, ?User $spouse, array $accounts): void
    {
        foreach ($accounts as $account) {
            $isJoint = ($account['ownership_type'] ?? 'individual') === 'joint';

            SavingsAccount::create([
                'user_id' => $user->id,
                'institution' => $account['provider_name'] ?? '',
                'account_type' => $account['account_type'] ?? 'instant_access',
                'current_balance' => $account['current_balance'] ?? 0,
                'interest_rate' => $account['interest_rate'] ?? null,
                'is_isa' => $account['is_isa'] ?? false,
                'access_type' => $account['access_type'] ?? 'immediate',
                'ownership_type' => $account['ownership_type'] ?? 'individual',
                'joint_owner_id' => $isJoint && $spouse ? $spouse->id : null,
            ]);
        }
    }

    /**
     * Create investment accounts with their holdings.
     */
    private function createInvestmentAccounts(User $user, ?User $spouse, array $accounts): void
    {
        foreach ($accounts as $account) {
            $isJoint = ($account['ownership_type'] ?? 'individual') === 'joint';

            $investmentAccount = InvestmentAccount::create([
                'user_id' => $user->id,
                'provider' => $account['provider_name'] ?? '',
                'account_type' => $account['account_type'] ?? 'gia',
                'current_value' => $account['current_value'] ?? 0,
                'contributions_ytd' => $account['annual_contribution'] ?? 0,
                'tax_year' => '2024/25',
                'ownership_type' => $account['ownership_type'] ?? 'individual',
                'joint_owner_id' => $isJoint && $spouse ? $spouse->id : null,
            ]);

            // Create holdings
            foreach ($account['holdings'] ?? [] as $holding) {
                Holding::create([
                    'holdable_type' => InvestmentAccount::class,
                    'holdable_id' => $investmentAccount->id,
                    'security_name' => $holding['holding_name'] ?? '',
                    'asset_type' => $holding['asset_type'] ?? 'fund',
                    'current_value' => $holding['current_value'] ?? 0,
                    'allocation_percent' => $holding['allocation_percentage'] ?? null,
                    'ocf_percent' => $holding['annual_fee'] ?? null,
                ]);
            }
        }
    }

    /**
     * Create DC pensions.
     */
    private function createDCPensions(User $user, ?User $spouse, array $pensions): void
    {
        foreach ($pensions as $pension) {
            DCPension::create([
                'user_id' => $user->id,
                'scheme_name' => $pension['scheme_name'] ?? '',
                'provider' => $pension['provider_name'] ?? '',
                'pension_type' => $pension['pension_type'] ?? 'occupational',
                'scheme_type' => $pension['scheme_type'] ?? 'workplace',
                'current_fund_value' => $pension['current_fund_value'] ?? 0,
                'employee_contribution_percent' => $pension['employee_contribution_percent'] ?? null,
                'employer_contribution_percent' => $pension['employer_contribution_percent'] ?? null,
                'annual_salary' => $pension['annual_salary'] ?? null,
                'retirement_age' => $pension['retirement_age'] ?? 65,
            ]);
        }
    }

    /**
     * Create DB pensions.
     */
    private function createDBPensions(User $user, ?User $spouse, array $pensions): void
    {
        foreach ($pensions as $pension) {
            DBPension::create([
                'user_id' => $user->id,
                'scheme_name' => $pension['scheme_name'] ?? '',
                'scheme_type' => $pension['pension_type'] ?? 'final_salary',
                'accrued_annual_pension' => $pension['current_annual_pension'] ?? 0,
                'normal_retirement_age' => $pension['normal_retirement_age'] ?? 65,
                'lump_sum_entitlement' => $pension['lump_sum_option'] ?? null,
                'inflation_protection' => $pension['inflation_protection'] ?? 'cpi',
            ]);
        }
    }

    /**
     * Create life insurance policies.
     */
    private function createLifeInsurancePolicies(User $user, ?User $spouse, array $policies): void
    {
        foreach ($policies as $policy) {
            LifeInsurancePolicy::create([
                'user_id' => $user->id,
                'policy_type' => $policy['policy_type'] ?? 'term',
                'provider' => $policy['provider_name'] ?? '',
                'sum_assured' => $policy['sum_assured'] ?? 0,
                'premium_amount' => $policy['premium_amount'] ?? null,
                'premium_frequency' => $policy['premium_frequency'] ?? 'monthly',
                'policy_start_date' => $policy['policy_start_date'] ?? null,
                'policy_end_date' => $policy['policy_end_date'] ?? null,
                'in_trust' => $policy['in_trust'] ?? false,
                'policy_number' => $policy['policy_reference'] ?? null,
            ]);
        }
    }

    /**
     * Create critical illness policies.
     */
    private function createCriticalIllnessPolicies(User $user, ?User $spouse, array $policies): void
    {
        foreach ($policies as $policy) {
            CriticalIllnessPolicy::create([
                'user_id' => $user->id,
                'policy_type' => $policy['policy_type'] ?? 'standalone',
                'provider' => $policy['provider_name'] ?? '',
                'sum_assured' => $policy['sum_assured'] ?? 0,
                'premium_amount' => $policy['premium_amount'] ?? null,
                'premium_frequency' => $policy['premium_frequency'] ?? 'monthly',
                'policy_start_date' => $policy['policy_start_date'] ?? null,
                'policy_number' => $policy['policy_reference'] ?? null,
            ]);
        }
    }

    /**
     * Create income protection policies.
     */
    private function createIncomeProtectionPolicies(User $user, ?User $spouse, array $policies): void
    {
        foreach ($policies as $policy) {
            IncomeProtectionPolicy::create([
                'user_id' => $user->id,
                'provider' => $policy['provider_name'] ?? '',
                'benefit_amount' => $policy['monthly_benefit'] ?? 0,
                'deferred_period_weeks' => $policy['deferred_period_weeks'] ?? null,
                'premium_amount' => $policy['premium_amount'] ?? null,
                'policy_start_date' => $policy['policy_start_date'] ?? null,
                'policy_number' => $policy['policy_reference'] ?? null,
            ]);
        }
    }

    /**
     * Create liabilities.
     */
    private function createLiabilities(User $user, ?User $spouse, array $liabilities): void
    {
        foreach ($liabilities as $liability) {
            Liability::create([
                'user_id' => $user->id,
                'liability_type' => $liability['liability_type'] ?? 'other',
                'liability_name' => $liability['liability_name'] ?? '',
                'current_balance' => $liability['current_balance'] ?? 0,
                'interest_rate' => $liability['interest_rate'] ?? null,
                'monthly_payment' => $liability['monthly_payment'] ?? null,
                'maturity_date' => $liability['end_date'] ?? null,
            ]);
        }
    }
}
