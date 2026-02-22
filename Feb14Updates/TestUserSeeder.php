<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\BusinessInterest;
use App\Models\Chattel;
use App\Models\CriticalIllnessPolicy;
use App\Models\DBPension;
use App\Models\DCPension;
use App\Models\Estate\Bequest;
use App\Models\Estate\Gift;
use App\Models\Estate\IHTProfile;
use App\Models\Estate\Liability;
use App\Models\Estate\Trust;
use App\Models\Estate\Will;
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
use App\Models\StatePension;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates test users chris@fynla.org and c.jones@csjones.co (Angela)
     * Based on peak_earners persona but with modifications:
     * - NOT preview users (real data for local testing)
     * - Higher surplus income
     * - Additional properties
     * - Onboarding not complete
     * - Admin flag set for Chris
     */
    public function run(): void
    {
        // Check if test users already exist and delete if so
        $existingChris = User::where('email', 'chris@fynla.org')->first();
        if ($existingChris) {
            $this->command->info('Deleting existing test user chris@fynla.org...');
            $this->deleteUserData($existingChris);
            $existingChris->delete();
        }

        $existingAngela = User::where('email', 'c.jones@csjones.co')->first();
        if ($existingAngela) {
            $this->command->info('Deleting existing test user c.jones@csjones.co...');
            $this->deleteUserData($existingAngela);
            $existingAngela->delete();
        }

        $this->command->info('Creating test users...');

        // Create Chris (primary user)
        $chris = $this->createChris();

        // Create Angela (spouse)
        $angela = $this->createAngela($chris);

        // Create family members
        $this->createFamilyMembers($chris);

        // Create properties (5 total - more than Mitchells)
        $propertyMap = $this->createProperties($chris, $angela);

        // Create mortgages
        $this->createMortgages($chris, $angela, $propertyMap);

        // Create savings accounts
        $this->createSavingsAccounts($chris, $angela);

        // Create investment accounts
        $this->createInvestmentAccounts($chris, $angela);

        // Create pensions
        $this->createDCPensions($chris, $angela);
        $this->createDBPensions($chris, $angela);
        $this->createStatePension($chris, $angela);

        // Create insurance policies
        $this->createLifeInsurancePolicies($chris);
        $this->createCriticalIllnessPolicies($chris);

        // Create estate planning data
        $this->createRiskProfiles($chris, $angela);
        $this->createRetirementProfiles($chris, $angela);
        $this->createWills($chris, $angela);
        $this->createTrusts($chris);
        $this->createChattels($chris, $angela);

        // Create goals and life events
        $this->createGoals($chris, $angela);
        $this->createLifeEvents($chris, $angela);

        $this->command->info("✓ Created test user: Chris Jones (chris@fynla.org) - ADMIN");
        $this->command->info("✓ Created spouse: Angela Jones (c.jones@csjones.co)");
        $this->command->info("✓ Onboarding: NOT COMPLETE (setup button will show)");
        $this->command->info("✓ Total properties: 5");
    }

    /**
     * Create Chris (primary test user).
     */
    private function createChris(): User
    {
        $user = new User;

        // Basic info
        $user->first_name = 'Chris';
        $user->middle_name = 'James';
        $user->surname = 'Jones';
        $user->email = 'chris@fynla.org';
        $user->password = Hash::make('Password1!');

        // Admin flag
        $user->is_admin = true;

        // NOT preview user - this is real test data
        $user->is_preview_user = false;
        $user->preview_persona_id = null;

        // Profile info - High earner with surplus income
        $user->date_of_birth = '1975-06-15';
        $user->gender = 'male';
        $user->marital_status = 'married';
        $user->employment_status = 'employed';
        $user->occupation = 'Chief Technology Officer';
        $user->employer = 'FinTech Solutions Ltd';
        $user->annual_employment_income = 175000; // Higher than Mitchells
        $user->annual_trust_income = 8000;
        $user->payday_day_of_month = 28;
        $user->target_retirement_age = 58;
        $user->health_status = 'yes';
        $user->smoking_status = 'never';
        $user->education_level = 'postgraduate';

        // Lower expenditure for surplus income (Mitchells: 2500 each, Chris: 1800 each)
        $user->monthly_expenditure = 1800;
        $user->food_groceries = 350;
        $user->transport_fuel = 120;
        $user->healthcare_medical = 60;
        $user->insurance = 80;
        $user->mobile_phones = 40;
        $user->internet_tv = 35;
        $user->subscriptions = 25;
        $user->clothing_personal_care = 90;
        $user->entertainment_dining = 110;
        $user->holidays_travel = 150;
        $user->pets = 0;
        $user->childcare = 0;
        $user->school_fees = 600;
        $user->school_lunches = 40;
        $user->school_extras = 50;
        $user->university_fees = 0;
        $user->children_activities = 80;
        $user->gifts_charity = 60;
        $user->regular_savings = 0;
        $user->other_expenditure = 0;
        $user->rent = 0;
        $user->utilities = 110;

        // Address
        $user->address_line_1 = 'Oak Manor, 28 Riverside Drive';
        $user->address_line_2 = null;
        $user->city = 'Bath';
        $user->county = 'Somerset';
        $user->postcode = 'BA2 6PL';

        // Domicile
        $user->country_of_birth = 'United Kingdom';
        $user->domicile_status = 'uk_domiciled';

        // Onboarding NOT complete
        $user->onboarding_completed = false;

        $user->save();

        return $user;
    }

    /**
     * Create Angela (spouse).
     */
    private function createAngela(User $chris): User
    {
        $angela = new User;

        // Basic info
        $angela->first_name = 'Angela';
        $angela->middle_name = 'Marie';
        $angela->surname = 'Jones';
        $angela->email = 'c.jones@csjones.co';
        $angela->password = Hash::make('Password1!');

        // NOT preview user
        $angela->is_preview_user = false;
        $angela->preview_persona_id = null;
        $angela->is_admin = false;

        // Profile info
        $angela->date_of_birth = '1977-09-22';
        $angela->gender = 'female';
        $angela->marital_status = 'married';
        $angela->employment_status = 'employed';
        $angela->occupation = 'Consultant Surgeon';
        $angela->employer = 'Royal United Hospital Bath';
        $angela->annual_employment_income = 135000; // Higher than Sarah Mitchell
        $angela->target_retirement_age = 60;
        $angela->health_status = 'yes';
        $angela->smoking_status = 'never';
        $angela->education_level = 'postgraduate';

        // Lower expenditure for surplus income
        $angela->monthly_expenditure = 1800;
        $angela->food_groceries = 350;
        $angela->transport_fuel = 120;
        $angela->healthcare_medical = 60;
        $angela->insurance = 80;
        $angela->mobile_phones = 40;
        $angela->internet_tv = 35;
        $angela->subscriptions = 25;
        $angela->clothing_personal_care = 90;
        $angela->entertainment_dining = 110;
        $angela->holidays_travel = 150;
        $angela->pets = 0;
        $angela->childcare = 0;
        $angela->school_fees = 600;
        $angela->school_lunches = 40;
        $angela->school_extras = 50;
        $angela->university_fees = 0;
        $angela->children_activities = 80;
        $angela->gifts_charity = 60;
        $angela->regular_savings = 0;
        $angela->other_expenditure = 0;
        $angela->rent = 0;
        $angela->utilities = 110;

        // Domicile
        $angela->country_of_birth = 'United Kingdom';
        $angela->domicile_status = 'uk_domiciled';

        // Onboarding NOT complete
        $angela->onboarding_completed = false;

        $angela->save();

        // Link spouses
        $chris->spouse_id = $angela->id;
        $chris->save();

        $angela->spouse_id = $chris->id;
        $angela->save();

        return $angela;
    }

    /**
     * Create family members.
     */
    private function createFamilyMembers(User $chris): void
    {
        FamilyMember::create([
            'user_id' => $chris->id,
            'name' => 'Oliver Jones',
            'first_name' => 'Oliver',
            'last_name' => 'Jones',
            'relationship' => 'child',
            'date_of_birth' => '2008-03-12',
            'is_dependent' => true,
        ]);

        FamilyMember::create([
            'user_id' => $chris->id,
            'name' => 'Sophie Jones',
            'first_name' => 'Sophie',
            'last_name' => 'Jones',
            'relationship' => 'child',
            'date_of_birth' => '2011-07-08',
            'is_dependent' => true,
        ]);
    }

    /**
     * Create properties (5 total - 2 more than Mitchells).
     */
    private function createProperties(User $chris, User $angela): array
    {
        $propertyMap = [];

        // Property 1: Main residence (higher value)
        $property1 = Property::create([
            'user_id' => $chris->id,
            'property_type' => 'main_residence',
            'current_value' => 950000,
            'purchase_price' => 685000,
            'purchase_date' => '2013-06-01',
            'ownership_type' => 'joint',
            'ownership_percentage' => 50,
            'monthly_rental_income' => null,
            'joint_owner_id' => $angela->id,
            'address_line_1' => 'Oak Manor, 28 Riverside Drive',
            'city' => 'Bath',
            'county' => 'Somerset',
            'postcode' => 'BA2 6PL',
            'monthly_council_tax' => 340,
            'monthly_gas' => 105,
            'monthly_electricity' => 80,
            'monthly_water' => 45,
            'monthly_building_insurance' => 55,
            'monthly_contents_insurance' => 35,
            'monthly_service_charge' => 0,
            'monthly_maintenance_reserve' => 120,
            'other_monthly_costs' => 0,
        ]);
        $propertyMap[1] = $property1->id;

        // Property 2: BTL London
        $property2 = Property::create([
            'user_id' => $chris->id,
            'property_type' => 'buy_to_let',
            'current_value' => 485000,
            'purchase_price' => 365000,
            'purchase_date' => '2017-11-20',
            'ownership_type' => 'joint',
            'ownership_percentage' => 50,
            'monthly_rental_income' => 2100,
            'joint_owner_id' => $angela->id,
            'address_line_1' => 'Flat 18, Waterside Heights',
            'city' => 'London',
            'postcode' => 'E14 9RG',
            'monthly_council_tax' => 0,
            'monthly_gas' => 0,
            'monthly_electricity' => 0,
            'monthly_water' => 0,
            'monthly_building_insurance' => 40,
            'monthly_contents_insurance' => 0,
            'monthly_service_charge' => 295,
            'monthly_maintenance_reserve' => 110,
            'other_monthly_costs' => 160,
            'tenant_name' => 'Mr. James Thompson',
            'lease_start_date' => '2024-04-01',
            'lease_end_date' => '2025-03-31',
        ]);
        $propertyMap[2] = $property2->id;

        // Property 3: BTL Manchester (tenants in common with colleague)
        $property3 = Property::create([
            'user_id' => $chris->id,
            'property_type' => 'buy_to_let',
            'current_value' => 320000,
            'purchase_price' => 255000,
            'purchase_date' => '2020-08-10',
            'ownership_type' => 'tenants_in_common',
            'ownership_percentage' => 45,
            'joint_owner_name' => 'Sarah Williams',
            'monthly_rental_income' => 1450,
            'address_line_1' => 'Unit 8, Northern Quarter Apartments',
            'city' => 'Manchester',
            'postcode' => 'M4 5JB',
            'monthly_council_tax' => 0,
            'monthly_building_insurance' => 30,
            'monthly_service_charge' => 205,
            'monthly_maintenance_reserve' => 90,
            'other_monthly_costs' => 130,
            'tenant_name' => 'Ms. Emily Clarke',
            'lease_start_date' => '2024-07-01',
            'lease_end_date' => '2025-06-30',
        ]);
        $propertyMap[3] = $property3->id;

        // Property 4: BTL Edinburgh (additional property)
        $property4 = Property::create([
            'user_id' => $chris->id,
            'property_type' => 'buy_to_let',
            'current_value' => 365000,
            'purchase_price' => 285000,
            'purchase_date' => '2019-05-15',
            'ownership_type' => 'joint',
            'ownership_percentage' => 50,
            'monthly_rental_income' => 1650,
            'joint_owner_id' => $angela->id,
            'address_line_1' => 'Flat 22, Castle View',
            'city' => 'Edinburgh',
            'postcode' => 'EH1 2NG',
            'monthly_council_tax' => 0,
            'monthly_building_insurance' => 35,
            'monthly_service_charge' => 240,
            'monthly_maintenance_reserve' => 95,
            'other_monthly_costs' => 140,
            'tenant_name' => 'Dr. Robert MacLeod',
            'lease_start_date' => '2024-02-01',
            'lease_end_date' => '2025-01-31',
        ]);
        $propertyMap[4] = $property4->id;

        // Property 5: Holiday let Cornwall (additional property)
        $property5 = Property::create([
            'user_id' => $chris->id,
            'property_type' => 'secondary_residence',
            'current_value' => 425000,
            'purchase_price' => 335000,
            'purchase_date' => '2021-03-20',
            'ownership_type' => 'joint',
            'ownership_percentage' => 50,
            'monthly_rental_income' => 1200, // Part holiday let, part personal use
            'joint_owner_id' => $angela->id,
            'address_line_1' => 'Sea View Cottage, Harbour Lane',
            'city' => 'St Ives',
            'county' => 'Cornwall',
            'postcode' => 'TR26 2DS',
            'monthly_council_tax' => 180,
            'monthly_gas' => 0,
            'monthly_electricity' => 60,
            'monthly_water' => 35,
            'monthly_building_insurance' => 45,
            'monthly_contents_insurance' => 25,
            'monthly_service_charge' => 0,
            'monthly_maintenance_reserve' => 100,
            'other_monthly_costs' => 80,
        ]);
        $propertyMap[5] = $property5->id;

        return $propertyMap;
    }

    /**
     * Create mortgages.
     */
    private function createMortgages(User $chris, User $angela, array $propertyMap): void
    {
        // Mortgage 1: Main residence (small remaining balance)
        Mortgage::create([
            'user_id' => $chris->id,
            'property_id' => $propertyMap[1],
            'lender_name' => 'Nationwide',
            'outstanding_balance' => 48000,
            'original_loan_amount' => 480000,
            'mortgage_type' => 'repayment',
            'interest_rate' => 4.19,
            'rate_type' => 'fixed',
            'rate_fix_end_date' => '2028-06-01',
            'monthly_payment' => 480,
            'remaining_term_months' => 132,
            'start_date' => '2013-06-01',
            'ownership_type' => 'joint',
            'ownership_percentage' => 50,
            'joint_owner_id' => $angela->id,
        ]);

        // Mortgage 2: BTL London
        Mortgage::create([
            'user_id' => $chris->id,
            'property_id' => $propertyMap[2],
            'lender_name' => 'Santander',
            'outstanding_balance' => 205000,
            'original_loan_amount' => 292000,
            'mortgage_type' => 'interest_only',
            'interest_rate' => 5.09,
            'rate_type' => 'tracker',
            'monthly_payment' => 720,
            'remaining_term_months' => 192,
            'start_date' => '2017-11-20',
            'ownership_type' => 'joint',
            'ownership_percentage' => 50,
            'joint_owner_id' => $angela->id,
        ]);

        // Mortgage 3: BTL Manchester
        Mortgage::create([
            'user_id' => $chris->id,
            'property_id' => $propertyMap[3],
            'lender_name' => 'Halifax',
            'outstanding_balance' => 135000,
            'original_loan_amount' => 178500,
            'mortgage_type' => 'repayment',
            'interest_rate' => 5.39,
            'rate_type' => 'fixed',
            'rate_fix_end_date' => '2027-08-10',
            'monthly_payment' => 820,
            'remaining_term_months' => 228,
            'start_date' => '2020-08-10',
            'ownership_type' => 'tenants_in_common',
            'ownership_percentage' => 45,
            'joint_owner_name' => 'Sarah Williams',
        ]);

        // Mortgage 4: BTL Edinburgh
        Mortgage::create([
            'user_id' => $chris->id,
            'property_id' => $propertyMap[4],
            'lender_name' => 'Lloyds',
            'outstanding_balance' => 155000,
            'original_loan_amount' => 199500,
            'mortgage_type' => 'interest_only',
            'interest_rate' => 4.99,
            'rate_type' => 'fixed',
            'rate_fix_end_date' => '2026-05-15',
            'monthly_payment' => 630,
            'remaining_term_months' => 180,
            'start_date' => '2019-05-15',
            'ownership_type' => 'joint',
            'ownership_percentage' => 50,
            'joint_owner_id' => $angela->id,
        ]);

        // Mortgage 5: Holiday let Cornwall
        Mortgage::create([
            'user_id' => $chris->id,
            'property_id' => $propertyMap[5],
            'lender_name' => 'NatWest',
            'outstanding_balance' => 175000,
            'original_loan_amount' => 234500,
            'mortgage_type' => 'repayment',
            'interest_rate' => 5.29,
            'rate_type' => 'tracker',
            'monthly_payment' => 880,
            'remaining_term_months' => 216,
            'start_date' => '2021-03-20',
            'ownership_type' => 'joint',
            'ownership_percentage' => 50,
            'joint_owner_id' => $angela->id,
        ]);
    }

    /**
     * Create savings accounts.
     */
    private function createSavingsAccounts(User $chris, User $angela): void
    {
        // Chris's current account
        SavingsAccount::create([
            'user_id' => $chris->id,
            'institution' => 'Nationwide',
            'account_type' => 'current_account',
            'current_balance' => 12500,
            'interest_rate' => 0,
            'is_isa' => false,
            'access_type' => 'immediate',
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
        ]);

        // Angela's current account
        SavingsAccount::create([
            'user_id' => $angela->id,
            'institution' => 'HSBC',
            'account_type' => 'current_account',
            'current_balance' => 9800,
            'interest_rate' => 0,
            'is_isa' => false,
            'access_type' => 'immediate',
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
        ]);

        // Joint current account
        SavingsAccount::create([
            'user_id' => $chris->id,
            'institution' => 'Nationwide',
            'account_type' => 'current_account',
            'current_balance' => 6200,
            'interest_rate' => 0,
            'is_isa' => false,
            'access_type' => 'immediate',
            'ownership_type' => 'joint',
            'ownership_percentage' => 50,
            'joint_owner_id' => $angela->id,
        ]);

        // Chris's Cash ISA
        SavingsAccount::create([
            'user_id' => $chris->id,
            'institution' => 'Nationwide',
            'account_type' => 'cash_isa',
            'current_balance' => 28500,
            'interest_rate' => 4.35,
            'is_isa' => true,
            'access_type' => 'immediate',
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
        ]);

        // Angela's Cash ISA
        SavingsAccount::create([
            'user_id' => $angela->id,
            'institution' => 'Nationwide',
            'account_type' => 'cash_isa',
            'current_balance' => 28500,
            'interest_rate' => 4.35,
            'is_isa' => true,
            'access_type' => 'immediate',
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
        ]);

        // Premium Bonds (joint)
        SavingsAccount::create([
            'user_id' => $chris->id,
            'institution' => 'NS&I',
            'account_type' => 'premium_bonds',
            'current_balance' => 50000,
            'interest_rate' => 0,
            'is_isa' => false,
            'access_type' => 'immediate',
            'ownership_type' => 'joint',
            'ownership_percentage' => 50,
            'joint_owner_id' => $angela->id,
        ]);

        // Emergency fund (high interest saver)
        SavingsAccount::create([
            'user_id' => $chris->id,
            'institution' => 'Chase',
            'account_type' => 'instant_access',
            'current_balance' => 35000,
            'interest_rate' => 4.75,
            'is_isa' => false,
            'access_type' => 'immediate',
            'ownership_type' => 'joint',
            'ownership_percentage' => 50,
            'joint_owner_id' => $angela->id,
        ]);
    }

    /**
     * Create investment accounts.
     */
    private function createInvestmentAccounts(User $chris, User $angela): void
    {
        // Chris's S&S ISA
        $chrisIsa = InvestmentAccount::create([
            'user_id' => $chris->id,
            'account_name' => "Chris's Stocks & Shares ISA",
            'provider' => 'Interactive Investor',
            'account_type' => 'isa',
            'current_value' => 115000,
            'contributions_ytd' => 0,
            'monthly_contribution_amount' => 0,
            'contribution_frequency' => 'monthly',
            'isa_subscription_current_year' => 0,
            'tax_year' => '2025/26',
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
            'platform_fee_percent' => 0.25,
            'advisor_fee_percent' => 0.75,
            'risk_preference' => 'high',
            'has_custom_risk' => true,
        ]);

        Holding::create([
            'holdable_type' => InvestmentAccount::class,
            'holdable_id' => $chrisIsa->id,
            'security_name' => 'Vanguard FTSE Global All Cap',
            'ticker' => 'VWRP',
            'isin' => 'IE00BK5BQT80',
            'asset_type' => 'etf',
            'quantity' => 510,
            'purchase_price' => 95.00,
            'current_price' => 112.50,
            'current_value' => 57375,
            'cost_basis' => 48450,
            'allocation_percent' => 50,
            'ocf_percent' => 0.23,
        ]);

        Holding::create([
            'holdable_type' => InvestmentAccount::class,
            'holdable_id' => $chrisIsa->id,
            'security_name' => 'Scottish Mortgage IT',
            'ticker' => 'SMT',
            'isin' => 'GB00BLDYK618',
            'asset_type' => 'uk_equity',
            'quantity' => 2800,
            'purchase_price' => 8.20,
            'current_price' => 10.30,
            'current_value' => 28840,
            'cost_basis' => 22960,
            'allocation_percent' => 25,
            'ocf_percent' => 0.34,
        ]);

        Holding::create([
            'holdable_type' => InvestmentAccount::class,
            'holdable_id' => $chrisIsa->id,
            'security_name' => 'Baillie Gifford American',
            'ticker' => 'USA',
            'isin' => 'GB00B877KX19',
            'asset_type' => 'fund',
            'quantity' => 900,
            'purchase_price' => 28.50,
            'current_price' => 32.00,
            'current_value' => 28800,
            'cost_basis' => 25650,
            'allocation_percent' => 25,
            'ocf_percent' => 0.51,
        ]);

        // Angela's S&S ISA
        $angelaIsa = InvestmentAccount::create([
            'user_id' => $angela->id,
            'account_name' => "Angela's Stocks & Shares ISA",
            'provider' => 'Interactive Investor',
            'account_type' => 'isa',
            'current_value' => 98000,
            'contributions_ytd' => 0,
            'monthly_contribution_amount' => 0,
            'contribution_frequency' => 'monthly',
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
            'platform_fee_percent' => 0.25,
            'advisor_fee_percent' => 0.75,
            'risk_preference' => 'medium',
        ]);

        Holding::create([
            'holdable_type' => InvestmentAccount::class,
            'holdable_id' => $angelaIsa->id,
            'security_name' => 'Vanguard LifeStrategy 80',
            'ticker' => 'VGLS80',
            'isin' => 'GB00B4PQW151',
            'asset_type' => 'fund',
            'quantity' => 380,
            'purchase_price' => 230.00,
            'current_price' => 257.89,
            'current_value' => 97998,
            'cost_basis' => 87400,
            'allocation_percent' => 100,
            'ocf_percent' => 0.22,
        ]);

        // Joint GIA
        $jointGia = InvestmentAccount::create([
            'user_id' => $chris->id,
            'account_name' => 'Joint General Investment Account',
            'provider' => 'Interactive Investor',
            'account_type' => 'gia',
            'current_value' => 125000,
            'contributions_ytd' => 0,
            'monthly_contribution_amount' => 0,
            'contribution_frequency' => 'monthly',
            'ownership_type' => 'joint',
            'ownership_percentage' => 50,
            'joint_owner_id' => $angela->id,
            'platform_fee_percent' => 0.25,
            'advisor_fee_percent' => 0.75,
            'risk_preference' => 'upper_medium',
        ]);

        Holding::create([
            'holdable_type' => InvestmentAccount::class,
            'holdable_id' => $jointGia->id,
            'security_name' => 'iShares Core MSCI World',
            'ticker' => 'SWDA',
            'isin' => 'IE00B4L5Y983',
            'asset_type' => 'etf',
            'quantity' => 750,
            'purchase_price' => 70.00,
            'current_price' => 82.50,
            'current_value' => 61875,
            'cost_basis' => 52500,
            'allocation_percent' => 50,
            'ocf_percent' => 0.2,
        ]);

        Holding::create([
            'holdable_type' => InvestmentAccount::class,
            'holdable_id' => $jointGia->id,
            'security_name' => 'Vanguard UK Gilt',
            'ticker' => 'VGOV',
            'isin' => 'IE00B42WWV65',
            'asset_type' => 'bond',
            'quantity' => 1500,
            'purchase_price' => 19.00,
            'current_price' => 19.25,
            'current_value' => 28875,
            'cost_basis' => 28500,
            'allocation_percent' => 23,
            'ocf_percent' => 0.12,
        ]);

        Holding::create([
            'holdable_type' => InvestmentAccount::class,
            'holdable_id' => $jointGia->id,
            'security_name' => 'iShares Physical Gold',
            'ticker' => 'SGLN',
            'isin' => 'IE00B4ND3602',
            'asset_type' => 'alternative',
            'quantity' => 140,
            'purchase_price' => 200.00,
            'current_price' => 244.64,
            'current_value' => 34250,
            'cost_basis' => 28000,
            'allocation_percent' => 27,
            'ocf_percent' => 0.12,
        ]);
    }

    /**
     * Create DC pensions.
     */
    private function createDCPensions(User $chris, User $angela): void
    {
        // Chris's workplace pension
        DCPension::create([
            'user_id' => $chris->id,
            'scheme_name' => 'FinTech Solutions Ltd Pension',
            'provider' => 'Scottish Widows',
            'pension_type' => 'occupational',
            'scheme_type' => 'workplace',
            'current_fund_value' => 215000,
            'employee_contribution_percent' => 10,
            'employer_contribution_percent' => 10,
            'employer_matching_limit' => 10,
            'annual_salary' => 175000,
            'retirement_age' => 58,
            'risk_preference' => 'upper_medium',
            'platform_fee_percent' => 0.3,
        ]);

        // Chris's SIPP
        $chrisSipp = DCPension::create([
            'user_id' => $chris->id,
            'scheme_name' => "Chris's Self-Invested Personal Pension (SIPP)",
            'provider' => 'Interactive Investor',
            'pension_type' => 'sipp',
            'scheme_type' => 'sipp',
            'current_fund_value' => 385000,
            'monthly_contribution_amount' => 0,
            'retirement_age' => 58,
            'risk_preference' => 'upper_medium',
            'platform_fee_percent' => 0.25,
        ]);

        Holding::create([
            'holdable_type' => DCPension::class,
            'holdable_id' => $chrisSipp->id,
            'security_name' => 'Vanguard Global Equity',
            'ticker' => 'VHVG',
            'isin' => 'IE00BKX55S42',
            'asset_type' => 'fund',
            'quantity' => 5000,
            'purchase_price' => 33.00,
            'current_price' => 38.50,
            'current_value' => 192500,
            'cost_basis' => 165000,
            'allocation_percent' => 50,
            'ocf_percent' => 0.23,
        ]);

        Holding::create([
            'holdable_type' => DCPension::class,
            'holdable_id' => $chrisSipp->id,
            'security_name' => 'BlackRock Corporate Bond',
            'ticker' => 'SLXX',
            'isin' => 'IE0032895942',
            'asset_type' => 'bond',
            'quantity' => 950,
            'purchase_price' => 122.00,
            'current_price' => 121.05,
            'current_value' => 114998,
            'cost_basis' => 115900,
            'allocation_percent' => 30,
            'ocf_percent' => 0.18,
        ]);

        Holding::create([
            'holdable_type' => DCPension::class,
            'holdable_id' => $chrisSipp->id,
            'security_name' => 'L&G UK Property',
            'ticker' => 'LGUKP',
            'isin' => 'GB00BK35DT11',
            'asset_type' => 'property',
            'quantity' => 60000,
            'purchase_price' => 1.32,
            'current_price' => 1.29,
            'current_value' => 77400,
            'cost_basis' => 79200,
            'allocation_percent' => 20,
            'ocf_percent' => 0.68,
        ]);
    }

    /**
     * Create DB pensions.
     */
    private function createDBPensions(User $chris, User $angela): void
    {
        // Angela's NHS pension
        DBPension::create([
            'user_id' => $angela->id,
            'scheme_name' => 'NHS Pension Scheme',
            'scheme_type' => 'public_sector',
            'accrued_annual_pension' => 42000,
            'normal_retirement_age' => 60,
            'inflation_protection' => 'cpi',
            'lump_sum_entitlement' => 126000,
        ]);
    }

    /**
     * Create state pensions.
     */
    private function createStatePension(User $chris, User $angela): void
    {
        StatePension::create([
            'user_id' => $chris->id,
            'ni_years_completed' => 28,
            'ni_years_required' => 35,
            'state_pension_forecast_annual' => 11502,
            'state_pension_age' => 67,
            'already_receiving' => false,
        ]);

        StatePension::create([
            'user_id' => $angela->id,
            'ni_years_completed' => 26,
            'ni_years_required' => 35,
            'state_pension_forecast_annual' => 11502,
            'state_pension_age' => 67,
            'already_receiving' => false,
        ]);
    }

    /**
     * Create life insurance policies.
     */
    private function createLifeInsurancePolicies(User $chris): void
    {
        LifeInsurancePolicy::create([
            'user_id' => $chris->id,
            'policy_type' => 'level_term',
            'provider' => 'Aviva',
            'sum_assured' => 600000,
            'premium_amount' => 95,
            'premium_frequency' => 'monthly',
            'policy_start_date' => '2019-06-01',
            'policy_end_date' => '2039-06-01',
            'in_trust' => true,
            'policy_number' => 'AVA-LT-789456',
            'beneficiaries' => 'Angela Jones (spouse), Oliver Jones (child), Sophie Jones (child)',
        ]);
    }

    /**
     * Create critical illness policies.
     */
    private function createCriticalIllnessPolicies(User $chris): void
    {
        CriticalIllnessPolicy::create([
            'user_id' => $chris->id,
            'policy_type' => 'standalone',
            'provider' => 'Legal & General',
            'sum_assured' => 250000,
            'premium_amount' => 145,
            'premium_frequency' => 'monthly',
            'policy_start_date' => '2019-06-01',
            'policy_end_date' => '2039-06-01',
            'policy_number' => 'LG-CI-654321',
        ]);
    }

    /**
     * Create risk profiles.
     */
    private function createRiskProfiles(User $chris, User $angela): void
    {
        RiskProfile::create([
            'user_id' => $chris->id,
            'risk_level' => 'upper_medium',
            'risk_tolerance' => 'adventurous',
            'capacity_for_loss_percent' => 50,
            'time_horizon_years' => 10,
            'knowledge_level' => 'experienced',
            'attitude_to_volatility' => 'comfortable',
            'esg_preference' => false,
            'risk_assessed_at' => now(),
            'is_self_assessed' => true,
        ]);

        RiskProfile::create([
            'user_id' => $angela->id,
            'risk_level' => 'medium',
            'risk_tolerance' => 'balanced',
            'capacity_for_loss_percent' => 35,
            'time_horizon_years' => 10,
            'knowledge_level' => 'intermediate',
            'attitude_to_volatility' => 'comfortable',
            'esg_preference' => false,
            'risk_assessed_at' => now(),
            'is_self_assessed' => true,
        ]);
    }

    /**
     * Create retirement profiles.
     */
    private function createRetirementProfiles(User $chris, User $angela): void
    {
        RetirementProfile::create([
            'user_id' => $chris->id,
            'current_age' => 49,
            'target_retirement_age' => 58,
            'current_annual_salary' => 175000,
            'target_retirement_income' => 85000,
        ]);

        RetirementProfile::create([
            'user_id' => $angela->id,
            'current_age' => 47,
            'target_retirement_age' => 60,
            'current_annual_salary' => 135000,
            'target_retirement_income' => 62000,
        ]);
    }

    /**
     * Create wills.
     */
    private function createWills(User $chris, User $angela): void
    {
        // Chris's will
        $chrisWill = Will::create([
            'user_id' => $chris->id,
            'has_will' => true,
            'spouse_primary_beneficiary' => true,
            'spouse_bequest_percentage' => 100,
            'executor_name' => 'Angela Jones & Barclays Wealth',
            'executor_notes' => 'Mirror wills prepared by Smith & Partners Solicitors. Life insurance in trust.',
            'will_last_updated' => '2023-08-10',
        ]);

        Bequest::create([
            'will_id' => $chrisWill->id,
            'user_id' => $chris->id,
            'beneficiary_name' => 'Oliver Jones',
            'bequest_type' => 'percentage',
            'percentage_of_estate' => 50,
            'priority_order' => 2,
            'conditions' => 'To receive share at age 25, held in trust until then',
        ]);

        Bequest::create([
            'will_id' => $chrisWill->id,
            'user_id' => $chris->id,
            'beneficiary_name' => 'Sophie Jones',
            'bequest_type' => 'percentage',
            'percentage_of_estate' => 50,
            'priority_order' => 2,
            'conditions' => 'To receive share at age 25, held in trust until then',
        ]);

        Bequest::create([
            'will_id' => $chrisWill->id,
            'user_id' => $chris->id,
            'beneficiary_name' => 'Cancer Research UK',
            'bequest_type' => 'specific_amount',
            'specific_amount' => 15000,
            'priority_order' => 1,
        ]);

        // Angela's will
        $angelaWill = Will::create([
            'user_id' => $angela->id,
            'has_will' => true,
            'spouse_primary_beneficiary' => true,
            'spouse_bequest_percentage' => 100,
            'executor_name' => 'Chris Jones & Barclays Wealth',
            'executor_notes' => 'Mirror will matching Chris. NHS pension has separate nomination.',
            'will_last_updated' => '2023-08-10',
        ]);

        Bequest::create([
            'will_id' => $angelaWill->id,
            'user_id' => $angela->id,
            'beneficiary_name' => 'Oliver Jones',
            'bequest_type' => 'percentage',
            'percentage_of_estate' => 50,
            'priority_order' => 2,
            'conditions' => 'To receive share at age 25, held in trust until then',
        ]);

        Bequest::create([
            'will_id' => $angelaWill->id,
            'user_id' => $angela->id,
            'beneficiary_name' => 'Sophie Jones',
            'bequest_type' => 'percentage',
            'percentage_of_estate' => 50,
            'priority_order' => 2,
            'conditions' => 'To receive share at age 25, held in trust until then',
        ]);

        Bequest::create([
            'will_id' => $angelaWill->id,
            'user_id' => $angela->id,
            'beneficiary_name' => 'British Heart Foundation',
            'bequest_type' => 'specific_amount',
            'specific_amount' => 12000,
            'priority_order' => 1,
        ]);
    }

    /**
     * Create trusts.
     */
    private function createTrusts(User $chris): void
    {
        Trust::create([
            'user_id' => $chris->id,
            'trust_name' => 'Jones Children Education Trust',
            'trust_type' => 'discretionary',
            'trust_creation_date' => '2021-04-15',
            'initial_value' => 175000,
            'current_value' => 215000,
            'settlor' => 'Chris Jones',
            'beneficiaries' => 'Oliver Jones, Sophie Jones',
            'trustees' => 'Chris Jones, Angela Jones, Barclays Trustee Services',
            'purpose' => 'Education funding for Oliver and Sophie including university fees and accommodation',
            'is_relevant_property_trust' => true,
            'is_active' => true,
            'notes' => 'Set up when Oliver started secondary school. Growth of £40k since inception.',
        ]);
    }

    /**
     * Create chattels.
     */
    private function createChattels(User $chris, User $angela): void
    {
        Chattel::create([
            'user_id' => $chris->id,
            'chattel_type' => 'vehicle',
            'name' => '1972 Porsche 911T',
            'description' => 'Fully restored classic car in Tangerine',
            'current_value' => 110000,
            'purchase_price' => 62000,
            'purchase_date' => '2016-09-10',
            'valuation_date' => '2024-11-20',
            'make' => 'Porsche',
            'model' => '911T Coupe',
            'year' => 1972,
            'registration_number' => 'XYZ 789L',
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
            'notes' => 'CGT exempt - wasting asset. Garaged and fully maintained.',
        ]);

        Chattel::create([
            'user_id' => $chris->id,
            'chattel_type' => 'art',
            'name' => 'Modern British Art Collection',
            'description' => 'Collection including Banksy print and David Hockney lithograph',
            'current_value' => 45000,
            'purchase_price' => 28000,
            'purchase_date' => '2019-03-20',
            'valuation_date' => '2024-10-01',
            'ownership_type' => 'joint',
            'ownership_percentage' => 50,
            'joint_owner_id' => $angela->id,
            'notes' => 'Professionally valued by Sotheby\'s.',
        ]);

        Chattel::create([
            'user_id' => $angela->id,
            'chattel_type' => 'jewelry',
            'name' => "Angela's Engagement Ring",
            'description' => '3 carat diamond solitaire in white gold',
            'current_value' => 22000,
            'purchase_price' => 15000,
            'purchase_date' => '2003-05-15',
            'valuation_date' => '2024-02-10',
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
        ]);

        Chattel::create([
            'user_id' => $chris->id,
            'chattel_type' => 'vehicle',
            'name' => 'Tesla Model S',
            'description' => 'Daily driver - family car',
            'current_value' => 38000,
            'purchase_price' => 72000,
            'purchase_date' => '2021-09-01',
            'valuation_date' => '2024-12-01',
            'make' => 'Tesla',
            'model' => 'Model S Long Range',
            'year' => 2021,
            'registration_number' => 'CJ21 ABC',
            'ownership_type' => 'joint',
            'ownership_percentage' => 50,
            'joint_owner_id' => $angela->id,
            'notes' => 'CGT exempt - wasting asset.',
        ]);

        Chattel::create([
            'user_id' => $chris->id,
            'chattel_type' => 'collectible',
            'name' => 'Vintage Watch Collection',
            'description' => 'Rolex, Omega, and Patek Philippe timepieces',
            'current_value' => 28000,
            'purchase_price' => 18000,
            'purchase_date' => '2017-06-01',
            'valuation_date' => '2024-08-15',
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
        ]);
    }

    /**
     * Create goals.
     */
    private function createGoals(User $chris, User $angela): void
    {
        Goal::create([
            'user_id' => $chris->id,
            'goal_name' => 'Max Pension Contributions',
            'goal_type' => 'retirement',
            'description' => 'Maximise annual pension allowance for tax efficiency',
            'target_amount' => 60000,
            'current_amount' => 52000,
            'target_date' => '2026-04-05',
            'start_date' => '2024-04-06',
            'assigned_module' => 'retirement',
            'priority' => 'high',
            'monthly_contribution' => 3000,
            'ownership_type' => 'individual',
        ]);

        Goal::create([
            'user_id' => $chris->id,
            'goal_name' => 'Early Retirement Bridge Fund',
            'goal_type' => 'retirement',
            'description' => 'Build accessible fund to retire at 58 before pensions',
            'target_amount' => 250000,
            'current_amount' => 125000,
            'target_date' => '2032-06-01',
            'start_date' => '2021-01-01',
            'assigned_module' => 'investment',
            'priority' => 'critical',
            'is_essential' => true,
            'monthly_contribution' => 1800,
            'ownership_type' => 'joint',
            'joint_owner_id' => $angela->id,
        ]);

        Goal::create([
            'user_id' => $chris->id,
            'goal_name' => "Oliver's House Deposit Fund",
            'goal_type' => 'custom',
            'custom_goal_type_name' => 'Child Support',
            'description' => 'Help Oliver with first house deposit',
            'target_amount' => 50000,
            'current_amount' => 35000,
            'target_date' => '2028-09-01',
            'start_date' => '2023-06-01',
            'assigned_module' => 'savings',
            'priority' => 'medium',
            'monthly_contribution' => 600,
            'ownership_type' => 'joint',
            'joint_owner_id' => $angela->id,
        ]);
    }

    /**
     * Create life events.
     */
    private function createLifeEvents(User $chris, User $angela): void
    {
        LifeEvent::create([
            'user_id' => $chris->id,
            'event_name' => 'Annual Bonus',
            'event_type' => 'bonus',
            'description' => "Chris's typical annual bonus from FinTech Solutions",
            'amount' => 45000,
            'impact_type' => 'income',
            'expected_date' => '2026-04-01',
            'certainty' => 'likely',
            'show_in_projection' => true,
            'show_in_household_view' => true,
            'ownership_type' => 'individual',
        ]);

        LifeEvent::create([
            'user_id' => $chris->id,
            'event_name' => 'Kitchen Extension',
            'event_type' => 'home_improvement',
            'description' => 'Major kitchen renovation and extension at main residence',
            'amount' => 95000,
            'impact_type' => 'expense',
            'expected_date' => '2027-06-01',
            'certainty' => 'likely',
            'show_in_projection' => true,
            'show_in_household_view' => true,
            'ownership_type' => 'joint',
            'joint_owner_id' => $angela->id,
        ]);

        LifeEvent::create([
            'user_id' => $chris->id,
            'event_name' => "Sophie's University Costs",
            'event_type' => 'education_fees',
            'description' => "Sophie's university tuition and living costs over 3 years",
            'amount' => 50000,
            'impact_type' => 'expense',
            'expected_date' => '2029-09-01',
            'certainty' => 'likely',
            'show_in_projection' => true,
            'show_in_household_view' => true,
            'ownership_type' => 'joint',
            'joint_owner_id' => $angela->id,
        ]);
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

        // Delete estate data
        Liability::where('user_id', $user->id)->delete();
        Gift::where('user_id', $user->id)->delete();
        Trust::where('user_id', $user->id)->delete();
        BusinessInterest::where('user_id', $user->id)->delete();
        Chattel::where('user_id', $user->id)->delete();
        Goal::where('user_id', $user->id)->delete();
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
    }
}
