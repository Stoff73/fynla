<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\BusinessInterest;
use App\Models\Chattel;
use App\Models\CriticalIllnessPolicy;
use App\Models\DBPension;
use App\Models\DCPension;
use App\Models\DisabilityPolicy;
use App\Models\Estate\Bequest;
use App\Models\Estate\Gift;
use App\Models\Estate\IHTProfile;
use App\Models\Estate\LastingPowerOfAttorney;
use App\Models\Estate\Liability;
use App\Models\Estate\LpaAttorney;
use App\Models\Estate\LpaNotificationPerson;
use App\Models\Estate\Trust;
use App\Models\Estate\Will;
use App\Models\Estate\WillDocument;
use App\Models\FamilyMember;
use App\Models\Goal;
use App\Models\IncomeProtectionPolicy;
use App\Models\Investment\Holding;
use App\Models\Investment\InvestmentAccount;
use App\Models\Investment\RiskProfile;
use App\Models\LetterToSpouse;
use App\Models\LifeEvent;
use App\Models\LifeInsurancePolicy;
use App\Models\Mortgage;
use App\Models\Property;
use App\Models\RetirementProfile;
use App\Models\SavingsAccount;
use App\Models\SicknessIllnessPolicy;
use App\Models\SpousePermission;
use App\Models\StatePension;
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
        'entrepreneur',
        'young_saver',
        'retired_couple',
        'student',
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

        if (! file_exists($jsonPath)) {
            $this->command->warn("Persona file not found: {$jsonPath}");

            return;
        }

        $data = json_decode(file_get_contents($jsonPath), true);

        if (! $data) {
            $this->command->error("Failed to parse persona JSON: {$personaId}");

            return;
        }

        // Check if preview user already exists - if so, delete and recreate
        $existingUser = User::where('is_preview_user', true)
            ->where('preview_persona_id', $personaId)
            ->first();

        if ($existingUser) {
            $this->command->info("Deleting existing preview user for {$personaId} to recreate with fresh data...");
            $this->deletePreviewUser($existingUser, $personaId);
        }

        $this->command->info("Seeding persona: {$personaId}");

        // Create primary user (pass expenditure data if available)
        $user = $this->createUser($data['user'], $personaId, $data['expenditure'] ?? null);

        // Set life stage from persona JSON
        if (! empty($data['life_stage'])) {
            $user->update(['life_stage' => $data['life_stage']]);
        }

        // Create spouse if exists and not deceased (pass expenditure data for household sharing)
        $spouse = null;
        if (! empty($data['spouse']) && empty($data['spouse']['deceased'])) {
            $spouse = $this->createSpouse($data['spouse'], $personaId, $user, $data['expenditure'] ?? null);
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
        $this->createStatePension($user, $spouse, $data['state_pension'] ?? null, $data['spouse_state_pension'] ?? null);

        // Create insurance policies
        $this->createLifeInsurancePolicies($user, $spouse, $data['life_insurance_policies'] ?? []);
        $this->createCriticalIllnessPolicies($user, $spouse, $data['critical_illness_policies'] ?? []);
        $this->createIncomeProtectionPolicies($user, $spouse, $data['income_protection_policies'] ?? []);
        $this->createDisabilityPolicies($user, $spouse, $data['disability_policies'] ?? []);
        $this->createSicknessIllnessPolicies($user, $spouse, $data['sickness_illness_policies'] ?? []);

        // Create liabilities
        $this->createLiabilities($user, $spouse, $data['liabilities'] ?? []);

        // Create risk profiles
        $this->createRiskProfiles($user, $spouse, $data['risk_profile'] ?? null);

        // Create retirement profiles
        $this->createRetirementProfiles($user, $spouse, $data['user'] ?? [], $data['spouse'] ?? []);

        // Create wills and bequests
        $this->createWills($user, $spouse, $data['will'] ?? null);

        // Create trusts
        $this->createTrusts($user, $data['trusts'] ?? []);

        // Create gifts
        $this->createGifts($user, $spouse, $data['gifts'] ?? []);

        // Create IHT profiles (for widows with transferred allowances)
        $this->createIHTProfiles($user, $spouse, $data['iht_profile'] ?? null);

        // Create business interests
        $this->createBusinessInterests($user, $data['business_interests'] ?? []);

        // Create chattels
        $this->createChattels($user, $spouse, $data['chattels'] ?? []);

        // Create goals
        $this->createGoals($user, $spouse, $data['goals'] ?? []);

        // Create goal dependencies and link goals to accounts
        $this->createGoalDependencies($user);
        $this->linkGoalsToAccounts($user);

        // Create life events
        $this->createLifeEvents($user, $spouse, $data['life_events'] ?? []);

        // Create letters to spouse
        $this->createLetterToSpouse($user, $spouse, $data['letter_to_spouse'] ?? null, $data['chattels'] ?? []);

        // Create Lasting Powers of Attorney
        $this->createLpas($user, $spouse, $personaId);

        // Create Will Documents (Will Builder)
        $this->createWillDocuments($user, $spouse, $personaId);

        // Set journey states and selections
        $this->setJourneyData($user, $personaId);

        $this->command->info("  Created user: {$user->name} ({$user->email})");
        if ($spouse) {
            $this->command->info("  Created spouse: {$spouse->name} ({$spouse->email})");
        }
    }

    /**
     * Delete a preview user and all their related data.
     */
    private function deletePreviewUser(User $user, string $personaId): void
    {
        // Also delete spouse if exists
        $spousePersonaId = "{$personaId}_spouse";
        $spouse = User::where('is_preview_user', true)
            ->where('preview_persona_id', $spousePersonaId)
            ->first();

        // Delete all related data for the user
        $this->deleteUserData($user);

        // Delete spouse data if exists
        if ($spouse) {
            $this->deleteUserData($spouse);
            $spouse->forceDelete();
        }

        $user->forceDelete();
    }

    /**
     * Delete all data associated with a user.
     */
    private function deleteUserData(User $user): void
    {
        // Delete related records
        FamilyMember::where('user_id', $user->id)->delete();
        Property::where('user_id', $user->id)->delete();
        Mortgage::where('user_id', $user->id)->delete();
        SavingsAccount::where('user_id', $user->id)->delete();

        // Delete investment accounts and their holdings
        $investmentAccounts = InvestmentAccount::where('user_id', $user->id)->get();
        foreach ($investmentAccounts as $account) {
            Holding::where('holdable_type', InvestmentAccount::class)
                ->where('holdable_id', $account->id)
                ->delete();
        }
        InvestmentAccount::where('user_id', $user->id)->delete();

        // Delete pensions and their holdings
        $dcPensions = DCPension::where('user_id', $user->id)->get();
        foreach ($dcPensions as $pension) {
            Holding::where('holdable_type', DCPension::class)
                ->where('holdable_id', $pension->id)
                ->delete();
        }
        DCPension::where('user_id', $user->id)->delete();
        DBPension::where('user_id', $user->id)->delete();
        StatePension::where('user_id', $user->id)->delete();

        // Delete insurance policies
        LifeInsurancePolicy::where('user_id', $user->id)->delete();
        CriticalIllnessPolicy::where('user_id', $user->id)->delete();
        IncomeProtectionPolicy::where('user_id', $user->id)->delete();
        DisabilityPolicy::where('user_id', $user->id)->delete();
        SicknessIllnessPolicy::where('user_id', $user->id)->delete();

        // Delete estate data
        Liability::where('user_id', $user->id)->delete();
        Gift::where('user_id', $user->id)->delete();
        Trust::where('user_id', $user->id)->delete();
        BusinessInterest::where('user_id', $user->id)->delete();
        Chattel::where('user_id', $user->id)->delete();
        Goal::where('user_id', $user->id)->delete();
        // Delete life events where user is owner OR joint owner (force delete to clear soft-deleted records)
        LifeEvent::withTrashed()->where('user_id', $user->id)->forceDelete();
        LifeEvent::withTrashed()->where('joint_owner_id', $user->id)->forceDelete();

        // Delete wills and bequests
        $wills = Will::where('user_id', $user->id)->get();
        foreach ($wills as $will) {
            Bequest::where('will_id', $will->id)->delete();
        }
        Will::where('user_id', $user->id)->delete();

        // Delete profiles
        RiskProfile::where('user_id', $user->id)->delete();
        RetirementProfile::where('user_id', $user->id)->delete();
        IHTProfile::where('user_id', $user->id)->delete();

        // Delete letters to spouse
        LetterToSpouse::where('user_id', $user->id)->delete();

        // Delete Will Documents
        WillDocument::withTrashed()->where('user_id', $user->id)->forceDelete();

        // Delete Lasting Powers of Attorney (and their attorneys/notification persons via cascade)
        $lpas = LastingPowerOfAttorney::withTrashed()->where('user_id', $user->id)->get();
        foreach ($lpas as $lpa) {
            LpaAttorney::where('lasting_power_of_attorney_id', $lpa->id)->delete();
            LpaNotificationPerson::where('lasting_power_of_attorney_id', $lpa->id)->delete();
        }
        LastingPowerOfAttorney::withTrashed()->where('user_id', $user->id)->forceDelete();
    }

    /**
     * Create the primary preview user.
     */
    private function createUser(array $userData, string $personaId, ?array $expenditureData = null): User
    {
        $user = new User;

        // Set preview user flags (bypassing guarded)
        $user->is_preview_user = true;
        $user->preview_persona_id = $personaId;

        // Basic info - using new separate name fields
        $user->first_name = $userData['first_name'] ?? null;
        $user->middle_name = $userData['middle_name'] ?? null;
        $user->surname = $userData['last_name'] ?? null;
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
        $user->annual_dividend_income = $userData['annual_dividend_income'] ?? 0;
        $user->annual_self_employment_income = $userData['annual_self_employment_income'] ?? 0;
        $user->annual_rental_income = $userData['annual_rental_income'] ?? 0;
        $user->annual_interest_income = $userData['annual_interest_income'] ?? 0;
        $user->annual_trust_income = $userData['annual_trust_income'] ?? null;
        $user->payday_day_of_month = $userData['payday_day_of_month'] ?? null;
        $user->target_retirement_age = $userData['target_retirement_age'] ?? 65;
        $user->monthly_expenditure = $userData['monthly_expenditure'] ?? null;
        $user->health_status = $userData['health_status'] ?? null;
        $user->smoking_status = $userData['smoking_status'] ?? null;
        $user->education_level = $userData['education_level'] ?? null;

        // Expenditure categories (from separate expenditure data in persona JSON)
        // For married users in joint mode, each spouse gets 50% of household expenditure
        // For single users, they get 100%
        // Supports both old names (food, transport) and new names (food_groceries, transport_fuel)
        if ($expenditureData && ! empty($expenditureData['categories'])) {
            $categories = $expenditureData['categories'];
            // Married users get 50% share, single users get 100%
            $share = ($userData['marital_status'] ?? 'single') === 'married' ? 0.5 : 1.0;
            $user->monthly_expenditure = round(($expenditureData['total_monthly'] ?? $userData['monthly_expenditure'] ?? 0) * $share);
            $user->food_groceries = round(($categories['food_groceries'] ?? $categories['food'] ?? 0) * $share);
            $user->transport_fuel = round(($categories['transport_fuel'] ?? $categories['transport'] ?? 0) * $share);
            $user->healthcare_medical = round(($categories['healthcare_medical'] ?? 0) * $share);
            $user->insurance = round(($categories['insurance'] ?? 0) * $share);
            $user->mobile_phones = round(($categories['mobile_phones'] ?? 0) * $share);
            $user->internet_tv = round(($categories['internet_tv'] ?? 0) * $share);
            $user->subscriptions = round(($categories['subscriptions'] ?? 0) * $share);
            $user->clothing_personal_care = round(($categories['clothing_personal_care'] ?? $categories['clothing'] ?? 0) * $share);
            $user->entertainment_dining = round(($categories['entertainment_dining'] ?? $categories['entertainment'] ?? 0) * $share);
            $user->holidays_travel = round(($categories['holidays_travel'] ?? 0) * $share);
            $user->pets = round(($categories['pets'] ?? 0) * $share);
            $user->childcare = round(($categories['childcare'] ?? 0) * $share);
            $user->school_fees = round(($categories['school_fees'] ?? 0) * $share);
            $user->school_lunches = round(($categories['school_lunches'] ?? 0) * $share);
            $user->school_extras = round(($categories['school_extras'] ?? 0) * $share);
            $user->university_fees = round(($categories['university_fees'] ?? 0) * $share);
            $user->children_activities = round(($categories['children_activities'] ?? 0) * $share);
            $user->gifts_charity = round(($categories['gifts_charity'] ?? 0) * $share);
            $user->regular_savings = round(($categories['regular_savings'] ?? 0) * $share);
            $user->other_expenditure = round(($categories['other_expenditure'] ?? $categories['other'] ?? 0) * $share);
            $user->rent = round(($categories['rent'] ?? $categories['housing'] ?? 0) * $share);
            $user->utilities = round(($categories['utilities'] ?? 0) * $share);
        } else {
            // Set defaults if no expenditure data provided
            $share = ($userData['marital_status'] ?? 'single') === 'married' ? 0.5 : 1.0;
            $user->monthly_expenditure = round(($userData['monthly_expenditure'] ?? 0) * $share);
            $user->food_groceries = 0;
            $user->transport_fuel = 0;
            $user->healthcare_medical = 0;
            $user->insurance = 0;
            $user->mobile_phones = 0;
            $user->internet_tv = 0;
            $user->subscriptions = 0;
            $user->clothing_personal_care = 0;
            $user->entertainment_dining = 0;
            $user->holidays_travel = 0;
            $user->pets = 0;
            $user->childcare = 0;
            $user->school_fees = 0;
            $user->school_lunches = 0;
            $user->school_extras = 0;
            $user->university_fees = 0;
            $user->children_activities = 0;
            $user->gifts_charity = 0;
            $user->regular_savings = 0;
            $user->other_expenditure = 0;
            $user->rent = 0;
            $user->utilities = 0;
        }

        // Charitable donations and registered blind status
        $user->is_registered_blind = $userData['is_registered_blind'] ?? false;
        $user->annual_charitable_donations = $userData['annual_charitable_donations'] ?? null;
        $user->is_gift_aid = $userData['is_gift_aid'] ?? false;

        // Address
        if (! empty($userData['address'])) {
            $user->address_line_1 = $userData['address']['line_1'] ?? null;
            $user->address_line_2 = $userData['address']['line_2'] ?? null;
            $user->city = $userData['address']['city'] ?? null;
            $user->county = $userData['address']['county'] ?? null;
            $user->postcode = $userData['address']['postcode'] ?? null;
        }

        // Domicile information
        if (! empty($userData['domicile'])) {
            $domicile = $userData['domicile'];
            $user->country_of_birth = $domicile['country_of_birth'] ?? null;
            $user->uk_arrival_date = $domicile['uk_arrival_date'] ?? null;
            $user->years_uk_resident = $domicile['years_uk_resident'] ?? null;
            $user->domicile_status = $domicile['domicile_status'] ?? 'uk_domiciled';
            $user->deemed_domicile_date = $domicile['deemed_domicile_date'] ?? null;
        }

        $user->save();

        return $user;
    }

    /**
     * Create the spouse as a separate preview user.
     * For household expenditure, spouse gets a proportional share based on their income.
     */
    private function createSpouse(array $spouseData, string $personaId, User $primaryUser, ?array $expenditureData = null): User
    {
        $spouse = new User;

        // Set preview user flags
        $spouse->is_preview_user = true;
        $spouse->preview_persona_id = "{$personaId}_spouse";

        // Basic info - using new separate name fields
        $spouse->first_name = $spouseData['first_name'] ?? null;
        $spouse->middle_name = $spouseData['middle_name'] ?? null;
        $spouse->surname = $spouseData['last_name'] ?? null;
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

        // Health data
        $spouse->health_status = $spouseData['health_status'] ?? null;
        $spouse->smoking_status = $spouseData['smoking_status'] ?? null;
        $spouse->education_level = $spouseData['education_level'] ?? null;

        // Expenditure: In joint mode (default), each spouse gets 50% of household expenditure
        // Household total = user 50% + spouse 50% = 100%
        if ($expenditureData && ! empty($expenditureData['categories'])) {
            $categories = $expenditureData['categories'];
            $share = 0.5;
            $spouse->monthly_expenditure = round(($expenditureData['total_monthly'] ?? 0) * $share);
            $spouse->food_groceries = round(($categories['food_groceries'] ?? $categories['food'] ?? 0) * $share);
            $spouse->transport_fuel = round(($categories['transport_fuel'] ?? $categories['transport'] ?? 0) * $share);
            $spouse->healthcare_medical = round(($categories['healthcare_medical'] ?? 0) * $share);
            $spouse->insurance = round(($categories['insurance'] ?? 0) * $share);
            $spouse->mobile_phones = round(($categories['mobile_phones'] ?? 0) * $share);
            $spouse->internet_tv = round(($categories['internet_tv'] ?? 0) * $share);
            $spouse->subscriptions = round(($categories['subscriptions'] ?? 0) * $share);
            $spouse->clothing_personal_care = round(($categories['clothing_personal_care'] ?? $categories['clothing'] ?? 0) * $share);
            $spouse->entertainment_dining = round(($categories['entertainment_dining'] ?? $categories['entertainment'] ?? 0) * $share);
            $spouse->holidays_travel = round(($categories['holidays_travel'] ?? 0) * $share);
            $spouse->pets = round(($categories['pets'] ?? 0) * $share);
            $spouse->childcare = round(($categories['childcare'] ?? 0) * $share);
            $spouse->school_fees = round(($categories['school_fees'] ?? 0) * $share);
            $spouse->school_lunches = round(($categories['school_lunches'] ?? 0) * $share);
            $spouse->school_extras = round(($categories['school_extras'] ?? 0) * $share);
            $spouse->university_fees = round(($categories['university_fees'] ?? 0) * $share);
            $spouse->children_activities = round(($categories['children_activities'] ?? 0) * $share);
            $spouse->gifts_charity = round(($categories['gifts_charity'] ?? 0) * $share);
            $spouse->regular_savings = round(($categories['regular_savings'] ?? 0) * $share);
            $spouse->other_expenditure = round(($categories['other_expenditure'] ?? $categories['other'] ?? 0) * $share);
            $spouse->rent = round(($categories['rent'] ?? $categories['housing'] ?? 0) * $share);
            $spouse->utilities = round(($categories['utilities'] ?? 0) * $share);
        } else {
            // No expenditure data - set all to 0
            $spouse->monthly_expenditure = 0;
            $spouse->food_groceries = 0;
            $spouse->transport_fuel = 0;
            $spouse->healthcare_medical = 0;
            $spouse->insurance = 0;
            $spouse->mobile_phones = 0;
            $spouse->internet_tv = 0;
            $spouse->subscriptions = 0;
            $spouse->clothing_personal_care = 0;
            $spouse->entertainment_dining = 0;
            $spouse->holidays_travel = 0;
            $spouse->pets = 0;
            $spouse->childcare = 0;
            $spouse->school_fees = 0;
            $spouse->school_lunches = 0;
            $spouse->school_extras = 0;
            $spouse->university_fees = 0;
            $spouse->children_activities = 0;
            $spouse->gifts_charity = 0;
            $spouse->regular_savings = 0;
            $spouse->other_expenditure = 0;
            $spouse->rent = 0;
            $spouse->utilities = 0;
        }

        // Charitable donations and registered blind status
        $spouse->is_registered_blind = $spouseData['is_registered_blind'] ?? false;
        $spouse->annual_charitable_donations = $spouseData['annual_charitable_donations'] ?? null;
        $spouse->is_gift_aid = $spouseData['is_gift_aid'] ?? false;

        // Domicile information (spouse can have their own domicile data)
        if (! empty($spouseData['domicile'])) {
            $domicile = $spouseData['domicile'];
            $spouse->country_of_birth = $domicile['country_of_birth'] ?? null;
            $spouse->uk_arrival_date = $domicile['uk_arrival_date'] ?? null;
            $spouse->years_uk_resident = $domicile['years_uk_resident'] ?? null;
            $spouse->domicile_status = $domicile['domicile_status'] ?? 'uk_domiciled';
            $spouse->deemed_domicile_date = $domicile['deemed_domicile_date'] ?? null;
        }

        $spouse->save();

        // Link spouse to primary user
        $primaryUser->spouse_id = $spouse->id;
        $primaryUser->save();

        $spouse->spouse_id = $primaryUser->id;
        $spouse->save();

        // Create bidirectional spouse data sharing permissions
        SpousePermission::updateOrCreate(
            ['user_id' => $primaryUser->id, 'spouse_id' => $spouse->id],
            ['status' => 'accepted', 'responded_at' => now()]
        );
        SpousePermission::updateOrCreate(
            ['user_id' => $spouse->id, 'spouse_id' => $primaryUser->id],
            ['status' => 'accepted', 'responded_at' => now()]
        );

        // Create bidirectional spouse family member records with linked_user_id
        FamilyMember::updateOrCreate(
            ['user_id' => $primaryUser->id, 'relationship' => 'spouse'],
            [
                'name' => trim(($spouse->first_name ?? '').' '.($spouse->surname ?? '')),
                'first_name' => $spouse->first_name ?? '',
                'last_name' => $spouse->surname ?? '',
                'date_of_birth' => $spouse->date_of_birth,
                'gender' => $spouse->gender,
                'linked_user_id' => $spouse->id,
                'is_dependent' => false,
            ]
        );
        FamilyMember::updateOrCreate(
            ['user_id' => $spouse->id, 'relationship' => 'spouse'],
            [
                'name' => trim(($primaryUser->first_name ?? '').' '.($primaryUser->surname ?? '')),
                'first_name' => $primaryUser->first_name ?? '',
                'last_name' => $primaryUser->surname ?? '',
                'date_of_birth' => $primaryUser->date_of_birth,
                'gender' => $primaryUser->gender,
                'linked_user_id' => $primaryUser->id,
                'is_dependent' => false,
            ]
        );

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
     * Create properties.
     *
     * Single-Record Architecture:
     * - ONE record stores the FULL property value
     * - joint_owner_id links to secondary owner
     * - NO reciprocal record creation
     */
    private function createProperties(User $user, ?User $spouse, array $properties): array
    {
        $propertyMap = [];

        foreach ($properties as $prop) {
            $ownershipType = $prop['ownership_type'] ?? 'individual';
            $isSharedOwnership = in_array($ownershipType, ['joint', 'tenants_in_common']);
            $totalValue = $prop['current_value'] ?? 0;

            // Parse the address if provided as a single string
            $addressParts = $this->parseAddress($prop['address'] ?? '');

            // Determine ownership percentage - use from JSON if provided, otherwise default
            $ownershipPercentage = $prop['ownership_percentage'] ?? ($isSharedOwnership ? 50 : 100);

            // Determine joint owner - linked spouse or named person
            $jointOwnerId = null;
            $jointOwnerName = null;
            if ($isSharedOwnership) {
                if (! empty($prop['joint_owner_name'])) {
                    // Non-linked joint owner (e.g., "Mike Jones")
                    $jointOwnerName = $prop['joint_owner_name'];
                } elseif ($spouse) {
                    // Linked spouse account
                    $jointOwnerId = $spouse->id;
                }
            }

            // Single-record pattern: Store FULL value directly (no splitting)
            $property = Property::create([
                'user_id' => $user->id,
                'property_type' => $prop['property_type'] ?? 'main_residence',
                'current_value' => $totalValue, // FULL value
                'purchase_price' => $prop['purchase_price'] ?? null,
                'purchase_date' => $prop['purchase_date'] ?? null,
                'ownership_type' => $ownershipType,
                'ownership_percentage' => $ownershipPercentage,
                'monthly_rental_income' => $prop['monthly_rental_income'] ?? $prop['actual_rental_income'] ?? $prop['estimated_rental_value'] ?? null,
                'joint_owner_id' => $jointOwnerId,
                'joint_owner_name' => $jointOwnerName,
                'address_line_1' => $addressParts['line_1'],
                'city' => $addressParts['city'],
                'county' => $addressParts['county'] ?: null,
                'postcode' => $addressParts['postcode'] ?: null,
                // Monthly expenses
                'monthly_council_tax' => $prop['monthly_council_tax'] ?? null,
                'monthly_gas' => $prop['monthly_gas'] ?? null,
                'monthly_electricity' => $prop['monthly_electricity'] ?? null,
                'monthly_water' => $prop['monthly_water'] ?? null,
                'monthly_building_insurance' => $prop['monthly_building_insurance'] ?? null,
                'monthly_contents_insurance' => $prop['monthly_contents_insurance'] ?? null,
                'monthly_service_charge' => $prop['monthly_service_charge'] ?? null,
                'monthly_maintenance_reserve' => $prop['monthly_maintenance_reserve'] ?? null,
                'other_monthly_costs' => $prop['other_monthly_costs'] ?? null,
                // Tenant info (for BTL)
                'tenant_name' => $prop['tenant_name'] ?? null,
                'lease_start_date' => $prop['lease_start_date'] ?? null,
                'lease_end_date' => $prop['lease_end_date'] ?? null,
            ]);

            $propertyMap[$prop['id']] = $property->id;
            // Single-record pattern: NO reciprocal property for spouse
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
     *
     * Single-Record Architecture:
     * - ONE record stores the FULL mortgage balance
     * - joint_owner_id links to secondary owner
     * - NO reciprocal record creation
     */
    private function createMortgages(User $user, ?User $spouse, array $mortgages, array $propertyMap): void
    {
        foreach ($mortgages as $mort) {
            $propertyId = $propertyMap[$mort['property_id']] ?? null;
            $ownershipType = $mort['ownership_type'] ?? 'individual';
            $isSharedOwnership = in_array($ownershipType, ['joint', 'tenants_in_common']);
            $totalBalance = $mort['outstanding_balance'] ?? 0;
            $totalPayment = $mort['monthly_payment'] ?? null;

            // Determine ownership percentage - use from JSON if provided, otherwise default
            $ownershipPercentage = $mort['ownership_percentage'] ?? ($isSharedOwnership ? 50 : 100);

            // Determine joint owner - linked spouse or named person
            $jointOwnerId = null;
            $jointOwnerName = null;
            if ($isSharedOwnership) {
                if (! empty($mort['joint_owner_name'])) {
                    // Non-linked joint owner (e.g., "Mike Jones")
                    $jointOwnerName = $mort['joint_owner_name'];
                } elseif ($spouse) {
                    // Linked spouse account
                    $jointOwnerId = $spouse->id;
                }
            }

            // Single-record pattern: Store FULL balances directly (no splitting)
            Mortgage::create([
                'user_id' => $user->id,
                'property_id' => $propertyId,
                'lender_name' => $mort['lender_name'] ?? '',
                'outstanding_balance' => $totalBalance, // FULL balance
                'original_loan_amount' => $mort['original_amount'] ?? null,
                'mortgage_type' => $mort['mortgage_type'] ?? 'repayment',
                'interest_rate' => $mort['interest_rate'] ?? null,
                'rate_type' => $mort['rate_type'] ?? 'fixed',
                'rate_fix_end_date' => $mort['fixed_rate_end_date'] ?? null,
                'monthly_payment' => $totalPayment, // FULL payment
                'remaining_term_months' => $mort['remaining_term_months'] ?? null,
                'start_date' => $mort['mortgage_start_date'] ?? null,
                'ownership_type' => $ownershipType,
                'ownership_percentage' => $ownershipPercentage,
                'joint_owner_id' => $jointOwnerId,
                'joint_owner_name' => $jointOwnerName,
            ]);
            // Single-record pattern: NO reciprocal mortgage for spouse
        }
    }

    /**
     * Create savings accounts.
     *
     * Single-Record Architecture:
     * - ONE record stores the FULL account balance
     * - joint_owner_id links to secondary owner
     * - NO reciprocal record creation
     */
    private function createSavingsAccounts(User $user, ?User $spouse, array $accounts): void
    {
        foreach ($accounts as $account) {
            $isJoint = ($account['ownership_type'] ?? 'individual') === 'joint';
            $totalBalance = $account['current_balance'] ?? 0;

            // Determine owner for individual accounts (might belong to spouse)
            $owner = $this->determineAccountOwner($account, $user, $spouse);
            $isSpouseOwned = $owner->id !== $user->id;

            // For joint accounts, set the correct joint owner
            $jointOwnerId = null;
            if ($isJoint && $spouse) {
                $jointOwnerId = $isSpouseOwned ? $user->id : $spouse->id;
            }

            // Single-record pattern: Store FULL balance directly (no splitting)
            $isIsa = $account['is_isa'] ?? false;
            $accountType = $account['account_type'] ?? 'instant_access';
            $isaType = null;
            $isaSubscriptionAmount = null;
            $isaSubscriptionYear = null;

            if ($isIsa) {
                // Determine ISA type from account_type
                if ($accountType === 'cash_isa') {
                    $isaType = 'cash';
                } elseif ($accountType === 'lisa') {
                    $isaType = 'LISA';
                }

                // Set subscription data if available
                $isaSubscriptionAmount = $account['isa_subscription_current_year'] ?? $account['isa_subscription_amount'] ?? null;
                if ($isaSubscriptionAmount !== null) {
                    $isaSubscriptionYear = '2025/26';
                }
            }

            // Determine regular contribution amount and frequency
            $monthlyContribution = $account['monthly_contribution'] ?? 0;
            $contributionFrequency = ($monthlyContribution > 0) ? ($account['contribution_frequency'] ?? 'monthly') : null;

            SavingsAccount::create([
                'user_id' => $owner->id,
                'account_name' => $account['account_name'] ?? null,
                'institution' => $account['provider_name'] ?? '',
                'account_type' => $accountType,
                'current_balance' => $totalBalance, // FULL balance
                'interest_rate' => $account['interest_rate'] ?? null,
                'is_isa' => $isIsa,
                'isa_type' => $isaType,
                'isa_subscription_amount' => $isaSubscriptionAmount,
                'isa_subscription_year' => $isaSubscriptionYear,
                'regular_contribution_amount' => $monthlyContribution > 0 ? $monthlyContribution : null,
                'contribution_frequency' => $contributionFrequency,
                'access_type' => $account['access_type'] ?? 'immediate',
                'ownership_type' => $account['ownership_type'] ?? 'individual',
                'ownership_percentage' => $isJoint ? 50 : 100,
                'joint_owner_id' => $jointOwnerId,
            ]);
            // Single-record pattern: NO reciprocal account for other owner
        }
    }

    /**
     * Create investment accounts with their holdings.
     *
     * Single-Record Architecture:
     * - ONE record stores the FULL account value
     * - joint_owner_id links to secondary owner
     * - NO reciprocal record creation
     */
    private function createInvestmentAccounts(User $user, ?User $spouse, array $accounts): void
    {
        foreach ($accounts as $account) {
            $isJoint = ($account['ownership_type'] ?? 'individual') === 'joint';
            $totalValue = $account['current_value'] ?? 0;

            // Determine owner for individual accounts (might belong to spouse)
            $owner = $this->determineAccountOwner($account, $user, $spouse);
            $isSpouseOwned = $owner->id !== $user->id;

            // For joint accounts, set the correct joint owner
            $jointOwnerId = null;
            if ($isJoint && $spouse) {
                $jointOwnerId = $isSpouseOwned ? $user->id : $spouse->id;
            }

            // Single-record pattern: Store FULL value directly (no splitting)
            $accountType = $account['account_type'] ?? 'gia';
            $annualContribution = $account['annual_contribution'] ?? 0;

            // For ISA and LISA accounts, set isa_subscription_current_year
            $isaSubscription = null;
            if (in_array($accountType, ['isa', 'lisa'])) {
                $isaSubscription = $account['isa_subscription_current_year'] ?? $annualContribution;
            }

            $investmentAccount = InvestmentAccount::create([
                'user_id' => $owner->id,
                'account_name' => $account['account_name'] ?? null,
                'provider' => $account['provider_name'] ?? '',
                'account_type' => $accountType,
                'current_value' => $totalValue, // FULL value
                'contributions_ytd' => $annualContribution,
                'monthly_contribution_amount' => $account['monthly_contribution_amount'] ?? null,
                'contribution_frequency' => $account['contribution_frequency'] ?? 'monthly',
                'planned_lump_sum_amount' => $account['planned_lump_sum_amount'] ?? null,
                'planned_lump_sum_date' => isset($account['planned_lump_sum_date']) ? $account['planned_lump_sum_date'] : null,
                'isa_subscription_current_year' => $isaSubscription,
                'tax_year' => '2025/26',
                'ownership_type' => $account['ownership_type'] ?? 'individual',
                'ownership_percentage' => $isJoint ? 50 : 100,
                'joint_owner_id' => $jointOwnerId,
                'platform_fee_percent' => $account['platform_fee_percent'] ?? 0,
                'advisor_fee_percent' => $account['advisor_fee_percent'] ?? 0,
                'risk_preference' => $account['risk_preference'] ?? null,
                'has_custom_risk' => ! empty($account['has_custom_risk']),
            ]);

            // Create holdings for the account (FULL values)
            foreach ($account['holdings'] ?? [] as $holding) {
                $units = $holding['units'] ?? null;
                $purchasePrice = $holding['initial_unit_cost'] ?? null;
                $costBasis = ($units && $purchasePrice) ? $units * $purchasePrice : null;

                Holding::create([
                    'holdable_type' => InvestmentAccount::class,
                    'holdable_id' => $investmentAccount->id,
                    'security_name' => $holding['holding_name'] ?? '',
                    'ticker' => $holding['ticker'] ?? null,
                    'isin' => $holding['isin'] ?? null,
                    'asset_type' => $holding['asset_type'] ?? 'fund',
                    'quantity' => $units,
                    'purchase_price' => $purchasePrice,
                    'current_price' => $holding['current_unit_price'] ?? null,
                    'current_value' => $holding['current_value'] ?? 0,
                    'cost_basis' => $costBasis,
                    'allocation_percent' => $holding['allocation_percentage'] ?? null,
                    'ocf_percent' => $holding['annual_fee'] ?? null,
                ]);
            }
            // Single-record pattern: NO reciprocal account for other owner
        }
    }

    /**
     * Create DC pensions.
     * Assigns pensions to spouse based on:
     * 1. Explicit 'owner' field set to 'spouse'
     * 2. Notes mentioning spouse's name
     * 3. Matching annual_salary with spouse's income
     */
    private function createDCPensions(User $user, ?User $spouse, array $pensions): void
    {
        foreach ($pensions as $pension) {
            // Determine if this pension belongs to spouse
            $owner = $this->determinePensionOwner($pension, $user, $spouse);

            $dcPension = DCPension::create([
                'user_id' => $owner->id,
                'scheme_name' => $pension['scheme_name'] ?? '',
                'provider' => $pension['provider_name'] ?? '',
                'pension_type' => $pension['pension_type'] ?? 'occupational',
                'scheme_type' => $pension['scheme_type'] ?? 'workplace',
                'current_fund_value' => $pension['current_fund_value'] ?? 0,
                'employee_contribution_percent' => $pension['employee_contribution_percent'] ?? null,
                'employer_contribution_percent' => $pension['employer_contribution_percent'] ?? null,
                'employer_matching_limit' => $pension['employer_matching_limit'] ?? null,
                'monthly_contribution_amount' => $pension['monthly_contribution_amount'] ?? null,
                'annual_salary' => $pension['annual_salary'] ?? null,
                'retirement_age' => $pension['retirement_age'] ?? 65,
                'risk_preference' => $pension['risk_preference'] ?? null,
                'has_custom_risk' => ! empty($pension['has_custom_risk']),
                'platform_fee_percent' => $pension['platform_fee_percent'] ?? null,
            ]);

            // Create holdings for the pension (e.g., SIPP holdings)
            foreach ($pension['holdings'] ?? [] as $holding) {
                $units = $holding['units'] ?? null;
                $purchasePrice = $holding['initial_unit_cost'] ?? null;
                $costBasis = ($units && $purchasePrice) ? $units * $purchasePrice : null;

                Holding::create([
                    'holdable_type' => DCPension::class,
                    'holdable_id' => $dcPension->id,
                    'security_name' => $holding['holding_name'] ?? '',
                    'ticker' => $holding['ticker'] ?? null,
                    'isin' => $holding['isin'] ?? null,
                    'asset_type' => $holding['asset_type'] ?? 'fund',
                    'quantity' => $units,
                    'purchase_price' => $purchasePrice,
                    'current_price' => $holding['current_unit_price'] ?? null,
                    'current_value' => $holding['current_value'] ?? 0,
                    'cost_basis' => $costBasis,
                    'allocation_percent' => $holding['allocation_percentage'] ?? null,
                    'ocf_percent' => $holding['annual_fee'] ?? null,
                ]);
            }
        }
    }

    /**
     * Determine who owns a pension based on various indicators.
     */
    private function determinePensionOwner(array $pension, User $user, ?User $spouse): User
    {
        // No spouse means it belongs to primary user
        if (! $spouse) {
            return $user;
        }

        // Check for explicit owner field
        if (isset($pension['owner']) && $pension['owner'] === 'spouse') {
            return $spouse;
        }

        // Check if notes mention spouse's first name
        $notes = strtolower($pension['notes'] ?? '');
        $spouseFirstName = strtolower(explode(' ', $spouse->name)[0] ?? '');
        if ($spouseFirstName && str_contains($notes, $spouseFirstName)) {
            return $spouse;
        }

        // Check if scheme name contains spouse's employer
        $schemeName = strtolower($pension['scheme_name'] ?? '');
        $spouseEmployer = strtolower($spouse->employer ?? '');
        if ($spouseEmployer && str_contains($schemeName, $spouseEmployer)) {
            return $spouse;
        }

        // Check if annual salary matches spouse's income (within 1%)
        $pensionSalary = $pension['annual_salary'] ?? 0;
        $spouseIncome = $spouse->annual_employment_income ?? 0;
        if ($pensionSalary > 0 && $spouseIncome > 0) {
            $difference = abs($pensionSalary - $spouseIncome) / $spouseIncome;
            if ($difference < 0.01) { // Within 1%
                return $spouse;
            }
        }

        return $user;
    }

    /**
     * Determine who owns a savings/investment account based on account name.
     */
    private function determineAccountOwner(array $account, User $user, ?User $spouse): User
    {
        // No spouse means it belongs to primary user
        if (! $spouse) {
            return $user;
        }

        // Check for explicit owner field
        if (isset($account['owner']) && $account['owner'] === 'spouse') {
            return $spouse;
        }

        // Check if account name contains spouse's first name
        $accountName = strtolower($account['account_name'] ?? '');
        $spouseFirstName = strtolower(explode(' ', $spouse->name)[0] ?? '');
        if ($spouseFirstName && str_contains($accountName, $spouseFirstName)) {
            return $spouse;
        }

        // Check if notes mention spouse's first name
        $notes = strtolower($account['notes'] ?? '');
        if ($spouseFirstName && str_contains($notes, $spouseFirstName)) {
            return $spouse;
        }

        return $user;
    }

    /**
     * Create DB pensions.
     * Uses same owner determination logic as DC pensions.
     */
    private function createDBPensions(User $user, ?User $spouse, array $pensions): void
    {
        foreach ($pensions as $pension) {
            // Determine if this pension belongs to spouse
            $owner = $this->determinePensionOwner($pension, $user, $spouse);

            DBPension::create([
                'user_id' => $owner->id,
                'scheme_name' => $pension['scheme_name'] ?? '',
                'scheme_type' => $pension['pension_type'] ?? 'final_salary',
                'accrued_annual_pension' => $pension['accrued_annual_pension'] ?? $pension['current_annual_pension'] ?? 0,
                'normal_retirement_age' => $pension['normal_retirement_age'] ?? 65,
                'lump_sum_entitlement' => $pension['lump_sum_entitlement'] ?? $pension['lump_sum_option'] ?? null,
                'inflation_protection' => $pension['inflation_protection'] ?? 'cpi',
                'spouse_pension_percent' => $pension['spouse_benefit_percentage'] ?? null,
                'pensionable_service_years' => $pension['years_of_service'] ?? null,
            ]);
        }
    }

    /**
     * Create state pension for the user and optionally the spouse.
     */
    private function createStatePension(User $user, ?User $spouse, ?array $statePension, ?array $spouseStatePension = null): void
    {
        if ($statePension) {
            StatePension::create([
                'user_id' => $user->id,
                'ni_years_completed' => $statePension['qualifying_years'] ?? 35,
                'ni_years_required' => 35,
                'state_pension_forecast_annual' => $statePension['forecast_annual_amount'] ?? 0,
                'state_pension_age' => $statePension['state_pension_age'] ?? 66,
                'already_receiving' => $statePension['already_receiving'] ?? false,
            ]);
        }

        // Create spouse state pension if provided
        if ($spouse && $spouseStatePension) {
            StatePension::create([
                'user_id' => $spouse->id,
                'ni_years_completed' => $spouseStatePension['qualifying_years'] ?? 35,
                'ni_years_required' => 35,
                'state_pension_forecast_annual' => $spouseStatePension['forecast_annual_amount'] ?? 0,
                'state_pension_age' => $spouseStatePension['state_pension_age'] ?? 66,
                'already_receiving' => $spouseStatePension['already_receiving'] ?? false,
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
                'beneficiaries' => $policy['beneficiaries'] ?? null,
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
     * Create disability policies.
     */
    private function createDisabilityPolicies(User $user, ?User $spouse, array $policies): void
    {
        foreach ($policies as $policy) {
            $owner = ($policy['owner'] ?? 'user') === 'spouse' && $spouse ? $spouse : $user;

            DisabilityPolicy::create([
                'user_id' => $owner->id,
                'provider' => $policy['provider_name'] ?? '',
                'policy_number' => $policy['policy_reference'] ?? null,
                'benefit_amount' => $policy['benefit_amount'] ?? 0,
                'benefit_frequency' => $policy['benefit_frequency'] ?? 'monthly',
                'deferred_period_weeks' => $policy['deferred_period_weeks'] ?? null,
                'benefit_period_months' => $policy['benefit_period_months'] ?? null,
                'premium_amount' => $policy['premium_amount'] ?? null,
                'premium_frequency' => $policy['premium_frequency'] ?? 'monthly',
                'occupation_class' => $policy['occupation_class'] ?? null,
                'coverage_type' => $policy['coverage_type'] ?? 'accident_and_sickness',
                'policy_start_date' => $policy['policy_start_date'] ?? null,
                'policy_term_years' => $policy['policy_term_years'] ?? null,
            ]);
        }
    }

    /**
     * Create sickness/illness policies.
     */
    private function createSicknessIllnessPolicies(User $user, ?User $spouse, array $policies): void
    {
        foreach ($policies as $policy) {
            $owner = ($policy['owner'] ?? 'user') === 'spouse' && $spouse ? $spouse : $user;

            SicknessIllnessPolicy::create([
                'user_id' => $owner->id,
                'provider' => $policy['provider_name'] ?? '',
                'policy_number' => $policy['policy_reference'] ?? null,
                'benefit_amount' => $policy['benefit_amount'] ?? 0,
                'benefit_frequency' => $policy['benefit_frequency'] ?? 'monthly',
                'deferred_period_weeks' => $policy['deferred_period_weeks'] ?? null,
                'benefit_period_months' => $policy['benefit_period_months'] ?? null,
                'premium_amount' => $policy['premium_amount'] ?? null,
                'premium_frequency' => $policy['premium_frequency'] ?? 'monthly',
                'conditions_covered' => $policy['conditions_covered'] ?? null,
                'exclusions' => $policy['exclusions'] ?? null,
                'policy_start_date' => $policy['policy_start_date'] ?? null,
                'policy_term_years' => $policy['policy_term_years'] ?? null,
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

    /**
     * Create risk profiles for users.
     */
    private function createRiskProfiles(User $user, ?User $spouse, ?array $riskData): void
    {
        if (! $riskData) {
            return;
        }

        // Create risk profile for primary user
        if (! empty($riskData['main_risk_level'])) {
            RiskProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'risk_level' => $riskData['main_risk_level'],
                    'risk_tolerance' => $this->mapRiskLevelToTolerance($riskData['main_risk_level']),
                    'capacity_for_loss_percent' => $this->mapRiskLevelToCapacity($riskData['main_risk_level']),
                    'time_horizon_years' => 10,
                    'knowledge_level' => 'intermediate',
                    'attitude_to_volatility' => 'comfortable',
                    'esg_preference' => false,
                    'risk_assessed_at' => now(),
                    'is_self_assessed' => true,
                ]
            );
        }

        // Create risk profile for spouse if they have one
        if ($spouse && ! empty($riskData['spouse_risk_level'])) {
            RiskProfile::updateOrCreate(
                ['user_id' => $spouse->id],
                [
                    'risk_level' => $riskData['spouse_risk_level'],
                    'risk_tolerance' => $this->mapRiskLevelToTolerance($riskData['spouse_risk_level']),
                    'capacity_for_loss_percent' => $this->mapRiskLevelToCapacity($riskData['spouse_risk_level']),
                    'time_horizon_years' => 10,
                    'knowledge_level' => 'intermediate',
                    'attitude_to_volatility' => 'comfortable',
                    'esg_preference' => false,
                    'risk_assessed_at' => now(),
                    'is_self_assessed' => true,
                ]
            );
        }
    }

    /**
     * Map risk level to legacy tolerance value.
     */
    private function mapRiskLevelToTolerance(string $riskLevel): string
    {
        return match ($riskLevel) {
            'low', 'lower_medium' => 'cautious',
            'medium' => 'balanced',
            'upper_medium', 'high' => 'adventurous',
            default => 'balanced',
        };
    }

    /**
     * Map risk level to capacity for loss percentage.
     */
    private function mapRiskLevelToCapacity(string $riskLevel): int
    {
        return match ($riskLevel) {
            'low' => 10,
            'lower_medium' => 20,
            'medium' => 35,
            'upper_medium' => 50,
            'high' => 70,
            default => 35,
        };
    }

    /**
     * Create retirement profiles for users.
     */
    private function createRetirementProfiles(User $user, ?User $spouse, array $userData, array $spouseData): void
    {
        // Create retirement profile for primary user if target_retirement_income is set
        if (! empty($userData['target_retirement_income']) || ! empty($userData['target_retirement_age'])) {
            RetirementProfile::create([
                'user_id' => $user->id,
                'current_age' => $user->date_of_birth ? $user->date_of_birth->age : null,
                'target_retirement_age' => $userData['target_retirement_age'] ?? 65,
                'current_annual_salary' => $userData['annual_income'] ?? null,
                'target_retirement_income' => $userData['target_retirement_income'] ?? null,
            ]);
        }

        // Create retirement profile for spouse if they have data
        if ($spouse && (! empty($spouseData['target_retirement_income']) || ! empty($spouseData['target_retirement_age']))) {
            RetirementProfile::create([
                'user_id' => $spouse->id,
                'current_age' => $spouse->date_of_birth ? $spouse->date_of_birth->age : null,
                'target_retirement_age' => $spouseData['target_retirement_age'] ?? 65,
                'current_annual_salary' => $spouseData['annual_income'] ?? null,
                'target_retirement_income' => $spouseData['target_retirement_income'] ?? null,
            ]);
        }
    }

    /**
     * Create wills and bequests for users.
     */
    private function createWills(User $user, ?User $spouse, ?array $willData): void
    {
        if (! $willData) {
            return;
        }

        // Create will for primary user
        $will = Will::create([
            'user_id' => $user->id,
            'has_will' => $willData['has_will'] ?? false,
            'spouse_primary_beneficiary' => $willData['spouse_primary_beneficiary'] ?? true,
            'spouse_bequest_percentage' => $willData['spouse_bequest_percentage'] ?? 100,
            'executor_name' => $willData['executor_name'] ?? null,
            'executor_notes' => $willData['executor_notes'] ?? null,
            'will_last_updated' => $willData['will_last_updated'] ?? null,
        ]);

        // Create bequests for the will
        foreach ($willData['bequests'] ?? [] as $bequestData) {
            Bequest::create([
                'will_id' => $will->id,
                'user_id' => $user->id,
                'beneficiary_name' => $bequestData['beneficiary_name'] ?? '',
                'bequest_type' => $bequestData['bequest_type'] ?? 'percentage',
                'percentage_of_estate' => $bequestData['percentage_of_estate'] ?? null,
                'specific_amount' => $bequestData['specific_amount'] ?? null,
                'specific_asset_description' => $bequestData['specific_asset_description'] ?? null,
                'priority_order' => $bequestData['priority_order'] ?? 1,
                'conditions' => $bequestData['conditions'] ?? null,
            ]);
        }

        // Create will for spouse if they have will data
        if ($spouse && ! empty($willData['spouse_will'])) {
            $spouseWillData = $willData['spouse_will'];
            $spouseWill = Will::create([
                'user_id' => $spouse->id,
                'has_will' => $spouseWillData['has_will'] ?? $willData['has_will'] ?? false,
                'spouse_primary_beneficiary' => $spouseWillData['spouse_primary_beneficiary'] ?? true,
                'spouse_bequest_percentage' => $spouseWillData['spouse_bequest_percentage'] ?? 100,
                'executor_name' => $spouseWillData['executor_name'] ?? $willData['executor_name'] ?? null,
                'executor_notes' => $spouseWillData['executor_notes'] ?? null,
                'will_last_updated' => $spouseWillData['will_last_updated'] ?? $willData['will_last_updated'] ?? null,
            ]);

            // Create bequests for spouse's will
            foreach ($spouseWillData['bequests'] ?? [] as $bequestData) {
                Bequest::create([
                    'will_id' => $spouseWill->id,
                    'user_id' => $spouse->id,
                    'beneficiary_name' => $bequestData['beneficiary_name'] ?? '',
                    'bequest_type' => $bequestData['bequest_type'] ?? 'percentage',
                    'percentage_of_estate' => $bequestData['percentage_of_estate'] ?? null,
                    'specific_amount' => $bequestData['specific_amount'] ?? null,
                    'specific_asset_description' => $bequestData['specific_asset_description'] ?? null,
                    'priority_order' => $bequestData['priority_order'] ?? 1,
                    'conditions' => $bequestData['conditions'] ?? null,
                ]);
            }
        }
    }

    /**
     * Create trusts for users.
     */
    private function createTrusts(User $user, array $trusts): void
    {
        foreach ($trusts as $trust) {
            Trust::create([
                'user_id' => $user->id,
                'trust_name' => $trust['trust_name'] ?? '',
                'trust_type' => $trust['trust_type'] ?? 'discretionary',
                'trust_creation_date' => $trust['trust_creation_date'] ?? null,
                'initial_value' => $trust['initial_value'] ?? 0,
                'current_value' => $trust['current_value'] ?? 0,
                'settlor' => $trust['settlor'] ?? null,
                'beneficiaries' => $trust['beneficiaries'] ?? null,
                'trustees' => $trust['trustees'] ?? null,
                'purpose' => $trust['purpose'] ?? null,
                'notes' => $trust['notes'] ?? null,
                'is_relevant_property_trust' => $trust['is_relevant_property_trust'] ?? false,
                'is_active' => $trust['is_active'] ?? true,
            ]);
        }
    }

    /**
     * Create Lasting Powers of Attorney for specific personas.
     */
    private function createLpas(User $user, ?User $spouse, string $personaId): void
    {
        if ($personaId === 'peak_earners') {
            $this->createPeakEarnerLpas($user, $spouse);
        } elseif ($personaId === 'widow') {
            $this->createWidowLpas($user);
        }
    }

    /**
     * Create LPAs for peak_earners (David & Sarah Mitchell).
     */
    private function createPeakEarnerLpas(User $user, ?User $spouse): void
    {
        // David: Property & Financial Affairs (registered)
        $davidPf = LastingPowerOfAttorney::create([
            'user_id' => $user->id,
            'lpa_type' => 'property_financial',
            'status' => 'registered',
            'source' => 'uploaded',
            'donor_full_name' => $user->name,
            'donor_date_of_birth' => $user->date_of_birth,
            'donor_address_line_1' => '14 Oakwood Drive',
            'donor_address_city' => 'Sevenoaks',
            'donor_address_county' => 'Kent',
            'donor_address_postcode' => 'TN13 1QR',
            'attorney_decision_type' => 'jointly_and_severally',
            'when_attorneys_can_act' => 'only_when_lost_capacity',
            'preferences' => 'I would prefer my attorneys to consult with my financial adviser before making significant investment decisions over £50,000. I would also prefer that my main residence is not sold unless absolutely necessary for my care needs.',
            'instructions' => 'My attorneys must not make gifts from my estate exceeding £500 per person per year, other than to my spouse. Any decision to sell property must be agreed by both attorneys acting together.',
            'certificate_provider_name' => 'Robert Hartley',
            'certificate_provider_relationship' => 'Family Solicitor',
            'certificate_provider_known_years' => 12,
            'registration_date' => '2024-06-15',
            'opg_reference' => 'LP-2024-0847291',
            'is_registered_with_opg' => true,
            'completed_at' => '2024-05-20',
        ]);

        // David PF attorneys
        LpaAttorney::create([
            'lasting_power_of_attorney_id' => $davidPf->id,
            'attorney_type' => 'primary',
            'full_name' => $spouse ? $spouse->name : 'Sarah Mitchell',
            'date_of_birth' => $spouse?->date_of_birth,
            'relationship_to_donor' => 'Spouse',
            'sort_order' => 0,
        ]);
        LpaAttorney::create([
            'lasting_power_of_attorney_id' => $davidPf->id,
            'attorney_type' => 'primary',
            'full_name' => 'James Mitchell',
            'date_of_birth' => '1974-08-03',
            'relationship_to_donor' => 'Brother',
            'sort_order' => 1,
        ]);

        // David PF notification person
        LpaNotificationPerson::create([
            'lasting_power_of_attorney_id' => $davidPf->id,
            'full_name' => 'Elizabeth Mitchell',
            'address_line_1' => '8 The Crescent',
            'address_city' => 'Tonbridge',
            'address_postcode' => 'TN9 2AB',
            'sort_order' => 0,
        ]);

        // David: Health & Welfare (registered)
        $davidHw = LastingPowerOfAttorney::create([
            'user_id' => $user->id,
            'lpa_type' => 'health_welfare',
            'status' => 'registered',
            'source' => 'uploaded',
            'donor_full_name' => $user->name,
            'donor_date_of_birth' => $user->date_of_birth,
            'donor_address_line_1' => '14 Oakwood Drive',
            'donor_address_city' => 'Sevenoaks',
            'donor_address_county' => 'Kent',
            'donor_address_postcode' => 'TN13 1QR',
            'attorney_decision_type' => 'jointly_and_severally',
            'life_sustaining_treatment' => 'can_consent',
            'preferences' => 'I would prefer to remain in my own home for as long as possible. If residential care becomes necessary, I would prefer a facility close to my family in Kent. I would like my attorneys to consult with my GP before making decisions about ongoing medication changes.',
            'instructions' => 'My attorneys must consult with my spouse before agreeing to any care placement. If my spouse is unable to be consulted, my attorneys should seek the advice of my GP.',
            'certificate_provider_name' => 'Robert Hartley',
            'certificate_provider_relationship' => 'Family Solicitor',
            'certificate_provider_known_years' => 12,
            'registration_date' => '2024-06-15',
            'opg_reference' => 'LP-2024-0847292',
            'is_registered_with_opg' => true,
            'completed_at' => '2024-05-20',
        ]);

        // David HW attorneys
        LpaAttorney::create([
            'lasting_power_of_attorney_id' => $davidHw->id,
            'attorney_type' => 'primary',
            'full_name' => $spouse ? $spouse->name : 'Sarah Mitchell',
            'date_of_birth' => $spouse?->date_of_birth,
            'relationship_to_donor' => 'Spouse',
            'sort_order' => 0,
        ]);
        LpaAttorney::create([
            'lasting_power_of_attorney_id' => $davidHw->id,
            'attorney_type' => 'primary',
            'full_name' => 'James Mitchell',
            'date_of_birth' => '1974-08-03',
            'relationship_to_donor' => 'Brother',
            'sort_order' => 1,
        ]);

        LpaNotificationPerson::create([
            'lasting_power_of_attorney_id' => $davidHw->id,
            'full_name' => 'Elizabeth Mitchell',
            'address_line_1' => '8 The Crescent',
            'address_city' => 'Tonbridge',
            'address_postcode' => 'TN9 2AB',
            'sort_order' => 0,
        ]);

        // Sarah's LPAs (if spouse exists)
        if ($spouse) {
            // Sarah: Property & Financial Affairs (registered)
            $sarahPf = LastingPowerOfAttorney::create([
                'user_id' => $spouse->id,
                'lpa_type' => 'property_financial',
                'status' => 'registered',
                'source' => 'uploaded',
                'donor_full_name' => $spouse->name,
                'donor_date_of_birth' => $spouse->date_of_birth,
                'donor_address_line_1' => '14 Oakwood Drive',
                'donor_address_city' => 'Sevenoaks',
                'donor_address_county' => 'Kent',
                'donor_address_postcode' => 'TN13 1QR',
                'attorney_decision_type' => 'jointly_and_severally',
                'when_attorneys_can_act' => 'only_when_lost_capacity',
                'preferences' => 'I would prefer my attorneys to maintain my existing investment strategy and consult with our financial adviser before making changes. I would like my household bills to continue being paid by direct debit where possible.',
                'instructions' => 'My attorneys must not sell or remortgage any jointly owned property without first consulting my spouse. Any gifts from my estate must not exceed the annual Inheritance Tax exemption limits.',
                'certificate_provider_name' => 'Robert Hartley',
                'certificate_provider_relationship' => 'Family Solicitor',
                'certificate_provider_known_years' => 12,
                'registration_date' => '2024-09-10',
                'opg_reference' => 'LP-2024-0953104',
                'is_registered_with_opg' => true,
                'completed_at' => '2024-08-15',
            ]);

            LpaAttorney::create([
                'lasting_power_of_attorney_id' => $sarahPf->id,
                'attorney_type' => 'primary',
                'full_name' => $user->name,
                'date_of_birth' => $user->date_of_birth,
                'relationship_to_donor' => 'Spouse',
                'sort_order' => 0,
            ]);
            LpaAttorney::create([
                'lasting_power_of_attorney_id' => $sarahPf->id,
                'attorney_type' => 'primary',
                'full_name' => 'Claire Henderson',
                'date_of_birth' => '1980-11-17',
                'relationship_to_donor' => 'Sister',
                'sort_order' => 1,
            ]);

            LpaNotificationPerson::create([
                'lasting_power_of_attorney_id' => $sarahPf->id,
                'full_name' => 'Patricia Henderson',
                'address_line_1' => '22 Manor Road',
                'address_city' => 'Tunbridge Wells',
                'address_postcode' => 'TN1 1YZ',
                'sort_order' => 0,
            ]);

            // Sarah: Health & Welfare (registered)
            $sarahHw = LastingPowerOfAttorney::create([
                'user_id' => $spouse->id,
                'lpa_type' => 'health_welfare',
                'status' => 'registered',
                'source' => 'uploaded',
                'donor_full_name' => $spouse->name,
                'donor_date_of_birth' => $spouse->date_of_birth,
                'donor_address_line_1' => '14 Oakwood Drive',
                'donor_address_city' => 'Sevenoaks',
                'donor_address_county' => 'Kent',
                'donor_address_postcode' => 'TN13 1QR',
                'attorney_decision_type' => 'jointly_and_severally',
                'life_sustaining_treatment' => 'can_consent',
                'preferences' => 'I would prefer to be cared for at home wherever possible. If I need residential care, I would prefer somewhere within easy visiting distance of my family. I would like to continue attending my local church if my health allows.',
                'instructions' => 'My attorneys must always consult my spouse before making decisions about my care or living arrangements. Life-sustaining treatment decisions must be made jointly by both attorneys.',
                'certificate_provider_name' => 'Robert Hartley',
                'certificate_provider_relationship' => 'Family Solicitor',
                'certificate_provider_known_years' => 12,
                'registration_date' => '2024-09-10',
                'opg_reference' => 'LP-2024-0953105',
                'is_registered_with_opg' => true,
                'completed_at' => '2024-08-15',
            ]);

            LpaAttorney::create([
                'lasting_power_of_attorney_id' => $sarahHw->id,
                'attorney_type' => 'primary',
                'full_name' => $user->name,
                'date_of_birth' => $user->date_of_birth,
                'relationship_to_donor' => 'Spouse',
                'sort_order' => 0,
            ]);
            LpaAttorney::create([
                'lasting_power_of_attorney_id' => $sarahHw->id,
                'attorney_type' => 'primary',
                'full_name' => 'Claire Henderson',
                'date_of_birth' => '1980-11-17',
                'relationship_to_donor' => 'Sister',
                'sort_order' => 1,
            ]);

            LpaNotificationPerson::create([
                'lasting_power_of_attorney_id' => $sarahHw->id,
                'full_name' => 'Patricia Henderson',
                'address_line_1' => '22 Manor Road',
                'address_city' => 'Tunbridge Wells',
                'address_postcode' => 'TN1 1YZ',
                'sort_order' => 0,
            ]);
        }
    }

    /**
     * Create LPAs for widow (Margaret Thompson).
     */
    private function createWidowLpas(User $user): void
    {
        // Property & Financial Affairs (registered)
        $pfLpa = LastingPowerOfAttorney::create([
            'user_id' => $user->id,
            'lpa_type' => 'property_financial',
            'status' => 'registered',
            'source' => 'uploaded',
            'donor_full_name' => $user->name,
            'donor_date_of_birth' => $user->date_of_birth,
            'donor_address_line_1' => '7 Rose Cottage Lane',
            'donor_address_city' => 'Bath',
            'donor_address_county' => 'Somerset',
            'donor_address_postcode' => 'BA1 5NR',
            'attorney_decision_type' => 'jointly',
            'when_attorneys_can_act' => 'only_when_lost_capacity',
            'preferences' => 'I would prefer my son to be the lead attorney for day-to-day financial decisions. I would like my existing charitable donations to the Royal British Legion and Macmillan Cancer Support to continue.',
            'instructions' => 'My attorneys must not sell my main residence at 7 Rose Cottage Lane without the written agreement of both my children. Any investment decisions must be made jointly.',
            'certificate_provider_name' => 'Dr Helen Cross',
            'certificate_provider_relationship' => 'GP',
            'certificate_provider_known_years' => 15,
            'registration_date' => '2023-11-20',
            'opg_reference' => 'LP-2023-0612845',
            'is_registered_with_opg' => true,
            'completed_at' => '2023-10-15',
        ]);

        // Primary attorney: son
        LpaAttorney::create([
            'lasting_power_of_attorney_id' => $pfLpa->id,
            'attorney_type' => 'primary',
            'full_name' => 'Richard Thompson',
            'date_of_birth' => '1982-07-19',
            'relationship_to_donor' => 'Son',
            'sort_order' => 0,
        ]);

        // Replacement attorney: daughter
        LpaAttorney::create([
            'lasting_power_of_attorney_id' => $pfLpa->id,
            'attorney_type' => 'replacement',
            'full_name' => 'Catherine Thompson',
            'date_of_birth' => '1985-02-11',
            'relationship_to_donor' => 'Daughter',
            'sort_order' => 0,
        ]);

        LpaNotificationPerson::create([
            'lasting_power_of_attorney_id' => $pfLpa->id,
            'full_name' => 'Susan Clarke',
            'address_line_1' => '15 Lansdown Road',
            'address_city' => 'Bath',
            'address_postcode' => 'BA1 5EE',
            'sort_order' => 0,
        ]);

        // Health & Welfare (draft — not yet registered)
        $hwLpa = LastingPowerOfAttorney::create([
            'user_id' => $user->id,
            'lpa_type' => 'health_welfare',
            'status' => 'draft',
            'source' => 'created',
            'donor_full_name' => $user->name,
            'donor_date_of_birth' => $user->date_of_birth,
            'donor_address_line_1' => '7 Rose Cottage Lane',
            'donor_address_city' => 'Bath',
            'donor_address_county' => 'Somerset',
            'donor_address_postcode' => 'BA1 5NR',
            'attorney_decision_type' => 'jointly',
            'life_sustaining_treatment' => 'can_consent',
            'preferences' => 'I would prefer to remain in my own home for as long as possible. If I require residential care, I would prefer a facility in Bath or the surrounding area so my friends and family can visit easily.',
            'instructions' => 'My attorneys must consult with my GP, Dr Helen Cross, before making decisions about changes to my medication or treatment plan.',
        ]);

        // Primary attorney: son
        LpaAttorney::create([
            'lasting_power_of_attorney_id' => $hwLpa->id,
            'attorney_type' => 'primary',
            'full_name' => 'Richard Thompson',
            'date_of_birth' => '1982-07-19',
            'relationship_to_donor' => 'Son',
            'sort_order' => 0,
        ]);

        // Replacement attorney: daughter
        LpaAttorney::create([
            'lasting_power_of_attorney_id' => $hwLpa->id,
            'attorney_type' => 'replacement',
            'full_name' => 'Catherine Thompson',
            'date_of_birth' => '1985-02-11',
            'relationship_to_donor' => 'Daughter',
            'sort_order' => 0,
        ]);
    }

    /**
     * Create Will Documents (Will Builder) for specific personas.
     */
    private function createWillDocuments(User $user, ?User $spouse, string $personaId): void
    {
        if ($personaId === 'peak_earners') {
            $this->createMitchellWillDocuments($user, $spouse);
        } elseif ($personaId === 'widow') {
            $this->createWidowWillDocument($user);
        } elseif ($personaId === 'retired_couple') {
            $this->createRetiredCoupleWillDocuments($user, $spouse);
        }
    }

    /**
     * Mirror wills for David & Sarah Mitchell.
     */
    private function createMitchellWillDocuments(User $user, ?User $spouse): void
    {
        $userWill = Will::where('user_id', $user->id)->first();

        $davidDoc = WillDocument::create([
            'user_id' => $user->id,
            'will_id' => $userWill?->id,
            'will_type' => 'mirror',
            'status' => 'complete',
            'testator_full_name' => $user->name,
            'testator_address' => '14 Oakwood Drive, Sevenoaks, Kent, TN13 1QR',
            'testator_date_of_birth' => $user->date_of_birth,
            'testator_occupation' => 'Investment Director',
            'executors' => [
                ['name' => 'Sarah Mitchell', 'address' => '14 Oakwood Drive, Sevenoaks, Kent, TN13 1QR', 'relationship' => 'Spouse'],
                ['name' => 'Barclays Wealth Management', 'address' => '1 Churchill Place, London, E14 5HP', 'relationship' => 'Professional Executor'],
            ],
            'guardians' => [
                ['name' => 'James Mitchell', 'address' => '8 The Crescent, Tonbridge, Kent, TN9 2AB', 'relationship' => 'Brother'],
                ['name' => 'Claire Henderson', 'address' => '22 Manor Road, Tunbridge Wells, Kent, TN1 1YZ', 'relationship' => 'Sister-in-law'],
            ],
            'specific_gifts' => [
                ['beneficiary_name' => 'Cancer Research UK', 'type' => 'cash', 'amount' => 10000, 'description' => null, 'conditions' => null],
            ],
            'residuary_estate' => [
                ['beneficiary_name' => 'Sarah Mitchell', 'percentage' => 100, 'substitution_beneficiary' => 'Children in equal shares'],
            ],
            'funeral_preference' => 'cremation',
            'funeral_wishes_notes' => 'A celebration of life at St Nicholas Church, Sevenoaks. No flowers — donations to Cancer Research UK.',
            'digital_executor_name' => 'Sarah Mitchell',
            'digital_assets_instructions' => 'Password manager master password is in the safe deposit box at Barclays Sevenoaks. Digital photo library on iCloud to be preserved for the children.',
            'survivorship_days' => 28,
            'domicile_confirmed' => 'england_wales',
            'signed_date' => '2024-03-20',
            'witnesses' => [
                ['name' => 'Robert Hartley', 'address' => 'Hartley & Co Solicitors, 12 High Street, Sevenoaks, TN13 1HX', 'occupation' => 'Solicitor', 'date' => '2024-03-20'],
                ['name' => 'Amanda Pearson', 'address' => '9 Station Road, Sevenoaks, TN13 1AQ', 'occupation' => 'Legal Secretary', 'date' => '2024-03-20'],
            ],
            'generated_at' => '2024-03-15 10:30:00',
            'last_edited_at' => '2024-03-15 10:30:00',
        ]);

        if ($spouse) {
            $spouseWill = Will::where('user_id', $spouse->id)->first();

            $sarahDoc = WillDocument::create([
                'user_id' => $spouse->id,
                'will_id' => $spouseWill?->id,
                'will_type' => 'mirror',
                'status' => 'complete',
                'testator_full_name' => $spouse->name,
                'testator_address' => '14 Oakwood Drive, Sevenoaks, Kent, TN13 1QR',
                'testator_date_of_birth' => $spouse->date_of_birth,
                'testator_occupation' => 'NHS Consultant Paediatrician',
                'executors' => [
                    ['name' => 'David Mitchell', 'address' => '14 Oakwood Drive, Sevenoaks, Kent, TN13 1QR', 'relationship' => 'Spouse'],
                    ['name' => 'Barclays Wealth Management', 'address' => '1 Churchill Place, London, E14 5HP', 'relationship' => 'Professional Executor'],
                ],
                'guardians' => [
                    ['name' => 'James Mitchell', 'address' => '8 The Crescent, Tonbridge, Kent, TN9 2AB', 'relationship' => 'Brother-in-law'],
                    ['name' => 'Claire Henderson', 'address' => '22 Manor Road, Tunbridge Wells, Kent, TN1 1YZ', 'relationship' => 'Sister'],
                ],
                'specific_gifts' => [
                    ['beneficiary_name' => 'Cancer Research UK', 'type' => 'cash', 'amount' => 10000, 'description' => null, 'conditions' => null],
                ],
                'residuary_estate' => [
                    ['beneficiary_name' => 'David Mitchell', 'percentage' => 100, 'substitution_beneficiary' => 'Children in equal shares'],
                ],
                'funeral_preference' => 'cremation',
                'funeral_wishes_notes' => 'A celebration of life at St Nicholas Church, Sevenoaks. No flowers — donations to Cancer Research UK.',
                'digital_executor_name' => 'David Mitchell',
                'digital_assets_instructions' => 'Password manager master password is in the safe deposit box at Barclays Sevenoaks.',
                'survivorship_days' => 28,
                'domicile_confirmed' => 'england_wales',
                'signed_date' => '2024-03-20',
                'witnesses' => [
                    ['name' => 'Robert Hartley', 'address' => 'Hartley & Co Solicitors, 12 High Street, Sevenoaks, TN13 1HX', 'occupation' => 'Solicitor', 'date' => '2024-03-20'],
                    ['name' => 'Amanda Pearson', 'address' => '9 Station Road, Sevenoaks, TN13 1AQ', 'occupation' => 'Legal Secretary', 'date' => '2024-03-20'],
                ],
                'generated_at' => '2024-03-15 10:30:00',
                'last_edited_at' => '2024-03-15 10:30:00',
            ]);

            // Link mirror documents
            $davidDoc->update(['mirror_document_id' => $sarahDoc->id]);
            $sarahDoc->update(['mirror_document_id' => $davidDoc->id]);

            // Link will_document_id on wills
            if ($userWill) {
                $userWill->update(['will_document_id' => $davidDoc->id]);
            }
            if ($spouseWill) {
                $spouseWill->update(['will_document_id' => $sarahDoc->id]);
            }
        }
    }

    /**
     * Simple will for Margaret Thompson.
     */
    private function createWidowWillDocument(User $user): void
    {
        $will = Will::where('user_id', $user->id)->first();

        $doc = WillDocument::create([
            'user_id' => $user->id,
            'will_id' => $will?->id,
            'will_type' => 'simple',
            'status' => 'complete',
            'testator_full_name' => $user->name,
            'testator_address' => '7 Rose Cottage Lane, Bath, Somerset, BA1 5NR',
            'testator_date_of_birth' => $user->date_of_birth,
            'testator_occupation' => 'Retired Teacher',
            'executors' => [
                ['name' => 'Andrew Thompson', 'address' => '12 Lansdown Crescent, Bath, BA1 5EX', 'relationship' => 'Son'],
                ['name' => 'Smithson Solicitors LLP', 'address' => '4 Queen Square, Bath, BA1 2HE', 'relationship' => 'Professional Executor'],
            ],
            'guardians' => null,
            'specific_gifts' => [
                ['beneficiary_name' => 'Cotswold Care Hospice', 'type' => 'cash', 'amount' => 25000, 'description' => null, 'conditions' => null],
                ['beneficiary_name' => 'St Lawrence Church, Bourton', 'type' => 'cash', 'amount' => 5000, 'description' => null, 'conditions' => null],
                ['beneficiary_name' => 'Catherine Williams', 'type' => 'item', 'amount' => null, 'description' => 'Engagement ring and pearl necklace from my late husband Harold', 'conditions' => null],
            ],
            'residuary_estate' => [
                ['beneficiary_name' => 'Andrew Thompson', 'percentage' => 40, 'substitution_beneficiary' => 'His children in equal shares'],
                ['beneficiary_name' => 'Catherine Williams', 'percentage' => 40, 'substitution_beneficiary' => 'Her children in equal shares'],
                ['beneficiary_name' => 'Grandchildren Education Trust', 'percentage' => 15, 'substitution_beneficiary' => null],
                ['beneficiary_name' => 'Richard Thompson', 'percentage' => 5, 'substitution_beneficiary' => 'His children in equal shares'],
            ],
            'funeral_preference' => 'burial',
            'funeral_wishes_notes' => 'To be buried alongside my late husband Harold at St Lawrence Churchyard, Bourton-on-the-Water. A traditional Church of England service. Hymns: The Lord Is My Shepherd and Dear Lord and Father of Mankind.',
            'digital_executor_name' => 'Andrew Thompson',
            'digital_assets_instructions' => 'Email and online banking credentials are in the blue folder in the bureau in the front room. Facebook account to be memorialised.',
            'survivorship_days' => 28,
            'domicile_confirmed' => 'england_wales',
            'signed_date' => '2023-06-15',
            'witnesses' => [
                ['name' => 'Dr Helen Cross', 'address' => 'Pulteney Practice, 35 Great Pulteney Street, Bath, BA2 4BY', 'occupation' => 'General Practitioner', 'date' => '2023-06-15'],
                ['name' => 'Mary Jenkins', 'address' => '4 Rose Cottage Lane, Bath, BA1 5NR', 'occupation' => 'Retired Nurse', 'date' => '2023-06-15'],
            ],
            'generated_at' => '2023-06-10 14:00:00',
            'last_edited_at' => '2023-06-10 14:00:00',
        ]);

        if ($will) {
            $will->update(['will_document_id' => $doc->id]);
        }
    }

    /**
     * Mirror wills for Patricia & Harold Bennett.
     */
    private function createRetiredCoupleWillDocuments(User $user, ?User $spouse): void
    {
        $userWill = Will::where('user_id', $user->id)->first();

        $patriciaDoc = WillDocument::create([
            'user_id' => $user->id,
            'will_id' => $userWill?->id,
            'will_type' => 'mirror',
            'status' => 'complete',
            'testator_full_name' => $user->name,
            'testator_address' => '3 Willow Gardens, Cheltenham, Gloucestershire, GL50 2QE',
            'testator_date_of_birth' => $user->date_of_birth,
            'testator_occupation' => 'Retired',
            'executors' => [
                ['name' => 'Mark Bennett', 'address' => '19 Montpellier Walk, Cheltenham, GL50 1SD', 'relationship' => 'Son'],
                ['name' => 'Adams & Co Solicitors', 'address' => '7 Promenade, Cheltenham, GL50 1LN', 'relationship' => 'Professional Executor'],
            ],
            'guardians' => null,
            'specific_gifts' => [
                ['beneficiary_name' => 'Grandchildren Education Fund', 'type' => 'cash', 'amount' => 25000, 'description' => null, 'conditions' => 'To be held in trust until each grandchild reaches 18'],
            ],
            'residuary_estate' => [
                ['beneficiary_name' => 'Harold Bennett', 'percentage' => 100, 'substitution_beneficiary' => 'Children in equal shares'],
            ],
            'funeral_preference' => 'cremation',
            'funeral_wishes_notes' => 'A simple service at Cheltenham Crematorium. Ashes to be scattered at Cleeve Hill.',
            'digital_executor_name' => 'Mark Bennett',
            'digital_assets_instructions' => null,
            'survivorship_days' => 28,
            'domicile_confirmed' => 'england_wales',
            'signed_date' => '2023-08-22',
            'witnesses' => [
                ['name' => 'Jonathan Adams', 'address' => 'Adams & Co Solicitors, 7 Promenade, Cheltenham, GL50 1LN', 'occupation' => 'Solicitor', 'date' => '2023-08-22'],
                ['name' => 'Karen Phillips', 'address' => '7 Promenade, Cheltenham, GL50 1LN', 'occupation' => 'Legal Executive', 'date' => '2023-08-22'],
            ],
            'generated_at' => '2023-08-15 11:00:00',
            'last_edited_at' => '2023-08-15 11:00:00',
        ]);

        if ($spouse) {
            $spouseWill = Will::where('user_id', $spouse->id)->first();

            $haroldDoc = WillDocument::create([
                'user_id' => $spouse->id,
                'will_id' => $spouseWill?->id,
                'will_type' => 'mirror',
                'status' => 'complete',
                'testator_full_name' => $spouse->name,
                'testator_address' => '3 Willow Gardens, Cheltenham, Gloucestershire, GL50 2QE',
                'testator_date_of_birth' => $spouse->date_of_birth,
                'testator_occupation' => 'Retired',
                'executors' => [
                    ['name' => 'Mark Bennett', 'address' => '19 Montpellier Walk, Cheltenham, GL50 1SD', 'relationship' => 'Son'],
                    ['name' => 'Adams & Co Solicitors', 'address' => '7 Promenade, Cheltenham, GL50 1LN', 'relationship' => 'Professional Executor'],
                ],
                'guardians' => null,
                'specific_gifts' => [
                    ['beneficiary_name' => 'Grandchildren Education Fund', 'type' => 'cash', 'amount' => 25000, 'description' => null, 'conditions' => 'To be held in trust until each grandchild reaches 18'],
                ],
                'residuary_estate' => [
                    ['beneficiary_name' => 'Patricia Bennett', 'percentage' => 100, 'substitution_beneficiary' => 'Children in equal shares'],
                ],
                'funeral_preference' => 'cremation',
                'funeral_wishes_notes' => 'A simple service at Cheltenham Crematorium. Ashes to be scattered at Cleeve Hill.',
                'digital_executor_name' => 'Mark Bennett',
                'digital_assets_instructions' => null,
                'survivorship_days' => 28,
                'domicile_confirmed' => 'england_wales',
                'signed_date' => '2023-08-22',
                'witnesses' => [
                    ['name' => 'Jonathan Adams', 'address' => 'Adams & Co Solicitors, 7 Promenade, Cheltenham, GL50 1LN', 'occupation' => 'Solicitor', 'date' => '2023-08-22'],
                    ['name' => 'Karen Phillips', 'address' => '7 Promenade, Cheltenham, GL50 1LN', 'occupation' => 'Legal Executive', 'date' => '2023-08-22'],
                ],
                'generated_at' => '2023-08-15 11:00:00',
                'last_edited_at' => '2023-08-15 11:00:00',
            ]);

            $patriciaDoc->update(['mirror_document_id' => $haroldDoc->id]);
            $haroldDoc->update(['mirror_document_id' => $patriciaDoc->id]);

            if ($userWill) {
                $userWill->update(['will_document_id' => $patriciaDoc->id]);
            }
            if ($spouseWill) {
                $spouseWill->update(['will_document_id' => $haroldDoc->id]);
            }
        }
    }

    /**
     * Create gifts for users.
     * Assigns gifts to spouse if recipient_name contains "Harold" or similar indicators.
     */
    private function createGifts(User $user, ?User $spouse, array $gifts): void
    {
        foreach ($gifts as $gift) {
            // Determine if this gift is from the spouse
            $owner = $user;
            if ($spouse) {
                $recipientName = strtolower($gift['recipient_name'] ?? '');
                $spouseFirstName = strtolower($spouse->first_name ?? '');

                // Check if gift is from spouse (e.g., "Harold to grandchildren")
                if ($spouseFirstName && str_contains($recipientName, $spouseFirstName)) {
                    $owner = $spouse;
                }
            }

            Gift::create([
                'user_id' => $owner->id,
                'gift_date' => $gift['gift_date'] ?? null,
                'recipient' => $gift['recipient_name'] ?? '',
                'gift_type' => $gift['gift_type'] ?? 'pet',
                'gift_value' => $gift['amount'] ?? 0,
                'status' => $gift['status'] ?? 'within_7_years',
                'taper_relief_applicable' => $gift['taper_relief_applicable'] ?? false,
                'notes' => $gift['notes'] ?? null,
            ]);
        }
    }

    /**
     * Create IHT profiles for users.
     * Used primarily for widows/widowers with transferred allowances from deceased spouse.
     */
    private function createIHTProfiles(User $user, ?User $spouse, ?array $ihtData): void
    {
        if (! $ihtData) {
            return;
        }

        // Create IHT profile for primary user
        IHTProfile::create([
            'user_id' => $user->id,
            'marital_status' => $ihtData['marital_status'] ?? $user->marital_status ?? 'single',
            'has_spouse' => ! empty($spouse) || ($ihtData['has_spouse'] ?? false),
            'own_home' => $ihtData['own_home'] ?? true,
            'home_value' => $ihtData['home_value'] ?? null,
            'nrb_transferred_from_spouse' => $ihtData['transferred_nrb'] ?? 0,
            'rnrb_transferred_from_spouse' => $ihtData['transferred_rnrb'] ?? 0,
            'charitable_giving_percent' => $ihtData['charitable_giving_percent'] ?? 0,
        ]);

        // Create IHT profile for spouse if they have IHT data (e.g., married couples)
        if ($spouse && ! empty($ihtData['marital_status']) && $ihtData['marital_status'] === 'married') {
            IHTProfile::create([
                'user_id' => $spouse->id,
                'marital_status' => 'married',
                'has_spouse' => true,
                'own_home' => $ihtData['own_home'] ?? true,
                'home_value' => $ihtData['home_value'] ?? null,
                'nrb_transferred_from_spouse' => 0,
                'rnrb_transferred_from_spouse' => 0,
                'charitable_giving_percent' => $ihtData['charitable_giving_percent'] ?? 0,
            ]);
        }
    }

    /**
     * Create business interests for users.
     */
    private function createBusinessInterests(User $user, array $businesses): void
    {
        foreach ($businesses as $business) {
            BusinessInterest::create([
                'user_id' => $user->id,
                'business_name' => $business['business_name'] ?? '',
                'business_type' => $business['business_type'] ?? 'limited_company',
                'company_number' => $business['company_number'] ?? null,
                'industry_sector' => $business['industry_sector'] ?? null,
                'ownership_type' => $business['ownership_type'] ?? 'individual',
                'ownership_percentage' => $business['ownership_percentage']
                    ?? (in_array($business['ownership_type'] ?? 'individual', ['joint', 'tenants_in_common'], true) ? 50 : 100),
                'current_valuation' => $business['current_valuation'] ?? $business['current_value'] ?? 0,
                'valuation_date' => $business['valuation_date'] ?? null,
                'valuation_method' => $business['valuation_method'] ?? null,
                'annual_revenue' => $business['annual_revenue'] ?? null,
                'annual_profit' => $business['annual_profit'] ?? null,
                'annual_dividend_income' => $business['annual_dividend_income'] ?? null,
                'vat_registered' => $business['vat_registered'] ?? false,
                'vat_number' => $business['vat_number'] ?? null,
                'utr_number' => $business['utr_number'] ?? null,
                'tax_year_end' => $business['tax_year_end'] ?? null,
                'employee_count' => $business['employee_count'] ?? $business['employees'] ?? 0,
                'paye_reference' => $business['paye_reference'] ?? null,
                'trading_status' => $business['trading_status'] ?? 'trading',
                'acquisition_date' => $business['acquisition_date'] ?? null,
                'acquisition_cost' => $business['acquisition_cost'] ?? null,
                'bpr_eligible' => $business['bpr_eligible'] ?? false,
                'description' => $business['description'] ?? null,
                'notes' => $business['notes'] ?? null,
            ]);
        }
    }

    /**
     * Create chattels for the user.
     */
    private function createChattels(User $user, ?User $spouse, array $chattels): void
    {
        foreach ($chattels as $chattel) {
            // Determine owner - check for 'owner' => 'spouse' flag or name matching
            $owner = $user;
            if ($spouse && ($chattel['owner'] ?? null) === 'spouse') {
                $owner = $spouse;
            } elseif ($spouse && ! empty($chattel['name'])) {
                // Check if chattel name contains spouse's first name
                if (stripos($chattel['name'], $spouse->first_name) !== false) {
                    $owner = $spouse;
                }
            }

            // Determine joint owner for joint ownership
            $jointOwnerId = null;
            if (($chattel['ownership_type'] ?? 'individual') === 'joint' && $spouse) {
                $jointOwnerId = ($owner->id === $user->id) ? $spouse->id : $user->id;
            }

            Chattel::create([
                'user_id' => $owner->id,
                'joint_owner_id' => $jointOwnerId,
                'chattel_type' => $chattel['chattel_type'] ?? 'other',
                'name' => $chattel['name'] ?? $chattel['item_name'] ?? 'Unnamed Item',
                'description' => $chattel['description'] ?? null,
                'current_value' => $chattel['current_value'] ?? 0,
                'purchase_price' => $chattel['purchase_price'] ?? null,
                'purchase_date' => $chattel['purchase_date'] ?? null,
                'valuation_date' => $chattel['valuation_date'] ?? now()->toDateString(),
                'ownership_type' => $chattel['ownership_type'] ?? 'individual',
                'ownership_percentage' => $chattel['ownership_percentage']
                    ?? (in_array($chattel['ownership_type'] ?? 'individual', ['joint', 'tenants_in_common'], true) ? 50 : 100),
                'make' => $chattel['make'] ?? null,
                'model' => $chattel['model'] ?? null,
                'year' => $chattel['year'] ?? null,
                'registration_number' => $chattel['registration_number'] ?? null,
                'notes' => $chattel['notes'] ?? null,
            ]);
        }
    }

    /**
     * Create goals for users.
     * Assigns goals to spouse if 'owner' => 'spouse' flag is set.
     */
    private function createGoals(User $user, ?User $spouse, array $goals): void
    {
        foreach ($goals as $goal) {
            // Determine owner
            $owner = $user;
            if ($spouse && ($goal['owner'] ?? null) === 'spouse') {
                $owner = $spouse;
            }

            // Determine joint owner for joint goals
            $jointOwnerId = null;
            if (($goal['ownership_type'] ?? 'individual') === 'joint' && $spouse) {
                $jointOwnerId = ($owner->id === $user->id) ? $spouse->id : $user->id;
            }

            Goal::create([
                'user_id' => $owner->id,
                'goal_name' => $goal['goal_name'] ?? '',
                'goal_type' => $goal['goal_type'] ?? 'custom',
                'custom_goal_type_name' => $goal['custom_goal_type_name'] ?? null,
                'description' => $goal['description'] ?? null,
                'target_amount' => $goal['target_amount'] ?? 0,
                'current_amount' => $goal['current_amount'] ?? 0,
                'target_date' => $goal['target_date'] ?? null,
                'start_date' => $goal['start_date'] ?? now()->toDateString(),
                'assigned_module' => $goal['assigned_module'] ?? null,
                'module_override' => $goal['module_override'] ?? false,
                'priority' => $goal['priority'] ?? 'medium',
                'is_essential' => $goal['is_essential'] ?? false,
                'status' => $goal['status'] ?? 'active',
                'monthly_contribution' => $goal['monthly_contribution'] ?? null,
                'contribution_frequency' => $goal['contribution_frequency'] ?? 'monthly',
                'contribution_streak' => $goal['contribution_streak'] ?? 0,
                'longest_streak' => $goal['longest_streak'] ?? 0,
                'last_contribution_date' => $goal['last_contribution_date'] ?? null,
                'risk_preference' => $goal['risk_preference'] ?? null,
                'use_global_risk_profile' => $goal['use_global_risk_profile'] ?? true,
                'ownership_type' => $goal['ownership_type'] ?? 'individual',
                'joint_owner_id' => $jointOwnerId,
                'ownership_percentage' => $goal['ownership_percentage'] ?? 100,
                // Property-specific fields
                'property_location' => $goal['property_location'] ?? null,
                'property_type' => $goal['property_type'] ?? null,
                'is_first_time_buyer' => $goal['is_first_time_buyer'] ?? false,
                'estimated_property_price' => $goal['estimated_property_price'] ?? null,
                'deposit_percentage' => $goal['deposit_percentage'] ?? null,
                'stamp_duty_estimate' => $goal['stamp_duty_estimate'] ?? null,
                'additional_costs_estimate' => $goal['additional_costs_estimate'] ?? null,
            ]);
        }
    }

    /**
     * Create life events for users.
     * Assigns events to spouse if 'owner' => 'spouse' flag is set.
     */
    private function createLifeEvents(User $user, ?User $spouse, array $lifeEvents): void
    {
        foreach ($lifeEvents as $event) {
            // Determine owner
            $owner = $user;
            if ($spouse && ($event['owner'] ?? null) === 'spouse') {
                $owner = $spouse;
            }

            // Determine joint owner for joint events
            $jointOwnerId = null;
            if (($event['ownership_type'] ?? 'individual') === 'joint' && $spouse) {
                $jointOwnerId = ($owner->id === $user->id) ? $spouse->id : $user->id;
            }

            LifeEvent::create([
                'user_id' => $owner->id,
                'event_name' => $event['event_name'] ?? '',
                'event_type' => $event['event_type'] ?? 'custom_income',
                'description' => $event['description'] ?? null,
                'amount' => $event['amount'] ?? 0,
                'impact_type' => $event['impact_type'] ?? 'income',
                'expected_date' => $event['expected_date'] ?? null,
                'certainty' => $event['certainty'] ?? 'likely',
                'icon' => $event['icon'] ?? null,
                'show_in_projection' => $event['show_in_projection'] ?? true,
                'show_in_household_view' => $event['show_in_household_view'] ?? true,
                'ownership_type' => $event['ownership_type'] ?? 'individual',
                'joint_owner_id' => $jointOwnerId,
                'ownership_percentage' => $event['ownership_percentage'] ?? 100,
                'status' => $event['status'] ?? 'expected',
            ]);
        }
    }

    /**
     * Create goal dependencies for a user based on logical goal relationships.
     *
     * Links goals where one naturally precedes another:
     * - Emergency fund blocks property/wealth goals
     * - Education goals fund from wealth goals
     */
    private function createGoalDependencies(User $user): void
    {
        $goals = Goal::where('user_id', $user->id)->get()->keyBy('goal_type');

        // Emergency fund blocks property and wealth goals
        $emergencyFund = $goals->get('emergency_fund');
        if ($emergencyFund) {
            $blockedTypes = ['property_purchase', 'home_deposit', 'wealth_accumulation'];
            foreach ($blockedTypes as $type) {
                $blockedGoal = $goals->get($type);
                if ($blockedGoal) {
                    $blockedGoal->dependsOn()->syncWithoutDetaching([
                        $emergencyFund->id => ['dependency_type' => 'blocks'],
                    ]);
                }
            }
        }

        // Retirement goals depend on wealth accumulation (funds relationship)
        $wealthGoal = $goals->get('wealth_accumulation');
        $retirementGoal = $goals->get('retirement');
        if ($wealthGoal && $retirementGoal) {
            $retirementGoal->dependsOn()->syncWithoutDetaching([
                $wealthGoal->id => ['dependency_type' => 'funds'],
            ]);
        }

        // Education goals are prerequisite for custom goals (if both exist)
        $educationGoals = Goal::where('user_id', $user->id)
            ->where('goal_type', 'education')
            ->get();
        $customGoals = Goal::where('user_id', $user->id)
            ->where('goal_type', 'custom')
            ->get();

        if ($educationGoals->isNotEmpty() && $customGoals->isNotEmpty()) {
            // Link first education goal as prerequisite for first custom goal
            $customGoals->first()->dependsOn()->syncWithoutDetaching([
                $educationGoals->first()->id => ['dependency_type' => 'prerequisite'],
            ]);
        }
    }

    /**
     * Link goals to savings and investment accounts where appropriate.
     *
     * Matches goals to accounts by module assignment and name patterns.
     */
    private function linkGoalsToAccounts(User $user): void
    {
        $goals = Goal::where('user_id', $user->id)->where('status', 'active')->get();
        $savingsAccounts = SavingsAccount::where('user_id', $user->id)->get();
        $investmentAccounts = InvestmentAccount::where('user_id', $user->id)->get();

        foreach ($goals as $goal) {
            // Link emergency fund to the main savings account (highest balance, non-ISA)
            if ($goal->goal_type === 'emergency_fund' && $savingsAccounts->isNotEmpty()) {
                $savingsAccount = $savingsAccounts
                    ->filter(fn ($a) => ! str_contains(strtolower($a->account_name ?? ''), 'isa')
                        && ! str_contains(strtolower($a->account_name ?? ''), 'current')
                        && ! str_contains(strtolower($a->account_name ?? ''), 'junior'))
                    ->sortByDesc('current_balance')
                    ->first();

                if ($savingsAccount) {
                    $goal->update(['linked_savings_account_id' => $savingsAccount->id]);
                }
            }

            // Link wealth/investment goals to the primary ISA investment account
            if (in_array($goal->goal_type, ['wealth_accumulation', 'retirement']) && $investmentAccounts->isNotEmpty()) {
                $isaAccount = $investmentAccounts
                    ->filter(fn ($a) => str_contains(strtolower($a->account_name ?? ''), 'isa'))
                    ->sortByDesc('current_value')
                    ->first();

                if ($isaAccount && ! $goal->linked_investment_account_id) {
                    $goal->update(['linked_investment_account_id' => $isaAccount->id]);
                }
            }

            // Link education goals to Junior ISA savings accounts
            if ($goal->goal_type === 'education' && $savingsAccounts->isNotEmpty()) {
                $juniorIsa = $savingsAccounts
                    ->filter(fn ($a) => str_contains(strtolower($a->account_name ?? ''), 'junior'))
                    ->first();

                if ($juniorIsa && ! $goal->linked_savings_account_id) {
                    $goal->update(['linked_savings_account_id' => $juniorIsa->id]);
                }
            }
        }
    }

    /**
     * Create letters to spouse for users.
     * Also generates valuable_items_info from chattels if not provided.
     */
    private function createLetterToSpouse(User $user, ?User $spouse, ?array $letterData, array $chattels = []): void
    {
        if (! $letterData) {
            return;
        }

        // Generate valuable_items_info from chattels if not provided in letter data
        $valuableItemsInfo = $letterData['valuable_items_info'] ?? $this->generateValuableItemsFromChattels($chattels);

        // Create letter for primary user
        LetterToSpouse::create([
            'user_id' => $user->id,
            'immediate_actions' => $letterData['immediate_actions'] ?? null,
            'executor_name' => $letterData['executor_name'] ?? null,
            'executor_contact' => $letterData['executor_contact'] ?? null,
            'attorney_name' => $letterData['attorney_name'] ?? null,
            'attorney_contact' => $letterData['attorney_contact'] ?? null,
            'financial_advisor_name' => $letterData['financial_advisor_name'] ?? null,
            'financial_advisor_contact' => $letterData['financial_advisor_contact'] ?? null,
            'accountant_name' => $letterData['accountant_name'] ?? null,
            'accountant_contact' => $letterData['accountant_contact'] ?? null,
            'immediate_funds_access' => $letterData['immediate_funds_access'] ?? null,
            'employer_hr_contact' => $letterData['employer_hr_contact'] ?? null,
            'employer_benefits_info' => $letterData['employer_benefits_info'] ?? null,
            'password_manager_info' => $letterData['password_manager_info'] ?? null,
            'estate_documents_location' => $letterData['estate_documents_location'] ?? null,
            'vehicles_info' => $letterData['vehicles_info'] ?? null,
            'valuable_items_info' => $valuableItemsInfo,
            'cryptocurrency_info' => $letterData['cryptocurrency_info'] ?? null,
            'recurring_bills_info' => $letterData['recurring_bills_info'] ?? null,
            'funeral_preference' => $letterData['funeral_preference'] ?? 'not_specified',
            'funeral_service_details' => $letterData['funeral_service_details'] ?? null,
            'obituary_wishes' => $letterData['obituary_wishes'] ?? null,
            'additional_wishes' => $letterData['additional_wishes'] ?? null,
            'additional_boxes' => $letterData['additional_boxes'] ?? null,
        ]);

        // Create letter for spouse if data provided
        if ($spouse && ! empty($letterData['spouse_letter'])) {
            $spouseLetterData = $letterData['spouse_letter'];

            // Generate spouse valuable items from chattels owned by spouse
            $spouseValuableItems = $spouseLetterData['valuable_items_info']
                ?? $this->generateValuableItemsFromChattels(
                    array_filter($chattels, fn ($c) => ($c['owner'] ?? null) === 'spouse')
                );

            LetterToSpouse::create([
                'user_id' => $spouse->id,
                'immediate_actions' => $spouseLetterData['immediate_actions'] ?? null,
                'executor_name' => $spouseLetterData['executor_name'] ?? null,
                'executor_contact' => $spouseLetterData['executor_contact'] ?? null,
                'attorney_name' => $spouseLetterData['attorney_name'] ?? null,
                'attorney_contact' => $spouseLetterData['attorney_contact'] ?? null,
                'financial_advisor_name' => $spouseLetterData['financial_advisor_name'] ?? null,
                'financial_advisor_contact' => $spouseLetterData['financial_advisor_contact'] ?? null,
                'accountant_name' => $spouseLetterData['accountant_name'] ?? null,
                'accountant_contact' => $spouseLetterData['accountant_contact'] ?? null,
                'immediate_funds_access' => $spouseLetterData['immediate_funds_access'] ?? null,
                'employer_hr_contact' => $spouseLetterData['employer_hr_contact'] ?? null,
                'employer_benefits_info' => $spouseLetterData['employer_benefits_info'] ?? null,
                'password_manager_info' => $spouseLetterData['password_manager_info'] ?? null,
                'estate_documents_location' => $spouseLetterData['estate_documents_location'] ?? null,
                'vehicles_info' => $spouseLetterData['vehicles_info'] ?? null,
                'valuable_items_info' => $spouseValuableItems,
                'cryptocurrency_info' => $spouseLetterData['cryptocurrency_info'] ?? null,
                'recurring_bills_info' => $spouseLetterData['recurring_bills_info'] ?? null,
                'funeral_preference' => $spouseLetterData['funeral_preference'] ?? 'not_specified',
                'funeral_service_details' => $spouseLetterData['funeral_service_details'] ?? null,
                'obituary_wishes' => $spouseLetterData['obituary_wishes'] ?? null,
                'additional_wishes' => $spouseLetterData['additional_wishes'] ?? null,
                'additional_boxes' => $spouseLetterData['additional_boxes'] ?? null,
            ]);
        }
    }

    /**
     * Generate valuable_items_info text from chattels data.
     */
    private function generateValuableItemsFromChattels(array $chattels): ?string
    {
        if (empty($chattels)) {
            return null;
        }

        $info = '';
        foreach ($chattels as $chattel) {
            $name = $chattel['name'] ?? $chattel['item_name'] ?? 'Unnamed Item';
            $value = $chattel['current_value'] ?? 0;
            $notes = $chattel['notes'] ?? $chattel['description'] ?? '';

            $info .= "{$name} (£".number_format((float) $value, 0)."):\n";
            if ($notes) {
                $info .= "- {$notes}\n";
            }
            $info .= "\n";
        }

        return trim($info);
    }

    /**
     * Set journey states and selections for a preview persona.
     */
    private function setJourneyData(User $user, string $personaId): void
    {
        $journeyData = $this->getPersonaJourneyData($personaId);

        if ($journeyData === null) {
            return;
        }

        $user->update([
            'journey_states' => $journeyData['states'],
            'journey_selections' => $journeyData['selections'],
        ]);
    }

    /**
     * Get journey states and selections configuration for each persona.
     */
    private function getPersonaJourneyData(string $personaId): ?array
    {
        $allJourneys = ['budgeting', 'protection', 'investment', 'retirement', 'estate', 'family', 'business', 'goals'];

        $buildStates = function (array $completed, array $inProgress = []) use ($allJourneys): array {
            $states = [];
            foreach ($allJourneys as $journey) {
                if (in_array($journey, $completed, true)) {
                    $states[$journey] = [
                        'status' => 'completed',
                        'started_at' => '2026-01-15T10:00:00',
                        'completed_at' => '2026-01-15T10:30:00',
                    ];
                } elseif (in_array($journey, $inProgress, true)) {
                    $states[$journey] = [
                        'status' => 'in_progress',
                        'started_at' => '2026-02-01T09:00:00',
                        'completed_at' => null,
                    ];
                } else {
                    $states[$journey] = [
                        'status' => 'not_started',
                        'started_at' => null,
                        'completed_at' => null,
                    ];
                }
            }

            return $states;
        };

        return match ($personaId) {
            'young_family' => [
                'selections' => ['protection', 'budgeting', 'goals'],
                'states' => $buildStates(
                    completed: ['protection', 'budgeting'],
                    inProgress: ['goals']
                ),
            ],
            'peak_earners' => [
                'selections' => $allJourneys,
                'states' => $buildStates(completed: $allJourneys),
            ],
            'widow' => [
                'selections' => ['estate', 'protection'],
                'states' => $buildStates(completed: ['estate', 'protection']),
            ],
            'entrepreneur' => [
                'selections' => ['business', 'investment', 'retirement'],
                'states' => $buildStates(
                    completed: ['business', 'investment'],
                    inProgress: ['retirement']
                ),
            ],
            'young_saver' => [
                'selections' => ['budgeting', 'goals'],
                'states' => $buildStates(completed: ['budgeting', 'goals']),
            ],
            'retired_couple' => [
                'selections' => ['retirement', 'estate', 'protection'],
                'states' => $buildStates(completed: ['retirement', 'estate', 'protection']),
            ],
            default => null,
        };
    }
}
