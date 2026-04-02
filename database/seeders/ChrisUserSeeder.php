<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\BusinessInterest;
use App\Models\Chattel;
use App\Models\DCPension;
use App\Models\FamilyMember;
use App\Models\Goal;
use App\Models\Investment\Holding;
use App\Models\Investment\InvestmentAccount;
use App\Models\Investment\RiskProfile;
use App\Models\ISAAllowanceTracking;
use App\Models\LifeEvent;
use App\Models\Mortgage;
use App\Models\Property;
use App\Models\ProtectionProfile;
use App\Models\RetirementProfile;
use App\Models\Role;
use App\Models\SavingsAccount;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds the chris@fynla.org user with data matching the production account.
 * Used for local testing so local and production data stay in sync.
 */
class ChrisUserSeeder extends Seeder
{
    public function run(): void
    {
        // ── User ──────────────────────────────────────────────
        $chris = User::updateOrCreate(
            ['email' => 'chris@fynla.org'],
            [
                'first_name' => 'Chris',
                'surname' => 'Jones',
                'password' => Hash::make('Password1!'),
                'role_id' => Role::findByName(Role::ROLE_USER)?->id,
                'is_admin' => true,
                'is_advisor' => false,
                'is_preview_user' => false,
                'plan' => 'standard',
                'date_of_birth' => '1992-06-15',
                'gender' => 'male',
                'marital_status' => 'single',
                'life_stage' => 'early_career',
                'onboarding_completed' => false,
                'onboarding_current_step' => 'goals',
                'is_primary_account' => true,
                'address_line_1' => '42 Oak Lane',
                'city' => 'Bristol',
                'county' => 'Avon',
                'postcode' => 'BS1 4QR',
                'phone' => '07700123456',
                'employer' => 'Tech Corp',
                'industry' => 'Technology',
                'employment_status' => 'employed',
                'health_status' => 'yes',
                'smoking_status' => 'never',
                'target_retirement_age' => 65,
                'annual_employment_income' => 35000.00,
                'annual_self_employment_income' => 0.00,
                'annual_rental_income' => 5400.00,
                'annual_dividend_income' => 3500.00,
                'annual_interest_income' => 0.00,
                'annual_other_income' => 0.00,
                'monthly_expenditure' => 1300.00,
                'annual_expenditure' => 15600.00,
                'food_groceries' => 300.00,
                'transport_fuel' => 100.00,
                'expenditure_entry_mode' => 'category',
                'expenditure_sharing_mode' => 'joint',
                'ai_chat_enabled' => true,
                'info_guide_enabled' => true,
            ]
        );

        $userId = $chris->id;

        // ── Subscription (Pro, yearly, active) ────────────────
        Subscription::updateOrCreate(
            ['user_id' => $userId, 'plan' => 'pro'],
            [
                'billing_cycle' => 'yearly',
                'status' => 'active',
                'trial_started_at' => $chris->created_at,
                'trial_ends_at' => null,
                'current_period_start' => now(),
                'current_period_end' => now()->addYear(),
                'amount' => 20000.00,
            ]
        );

        // ── Property 1: Main Residence ────────────────────────
        $mainResidence = Property::updateOrCreate(
            ['user_id' => $userId, 'address_line_1' => '14 Maple Avenue'],
            [
                'property_type' => 'main_residence',
                'ownership_type' => 'individual',
                'tenure_type' => 'freehold',
                'country' => 'United Kingdom',
                'ownership_percentage' => 100.00,
                'city' => 'Unknown',
                'postcode' => 'N/A',
                'purchase_date' => '2018-06-01',
                'purchase_price' => 280000.00,
                'current_value' => 350000.00,
                'valuation_date' => '2026-03-25',
                'outstanding_mortgage' => 200000.00,
            ]
        );

        Mortgage::updateOrCreate(
            ['property_id' => $mainResidence->id, 'lender_name' => 'Halifax'],
            [
                'user_id' => $userId,
                'country' => 'United Kingdom',
                'mortgage_type' => 'repayment',
                'outstanding_balance' => 200000.00,
                'interest_rate' => 4.5000,
                'rate_type' => 'fixed',
                'monthly_payment' => 1100.00,
                'start_date' => '2026-03-25',
                'maturity_date' => '2051-03-25',
                'remaining_term_months' => 300,
                'ownership_type' => 'individual',
                'ownership_percentage' => 100.00,
            ]
        );

        // ── Property 2: Buy-to-Let (joint with "wife") ───────
        $btl = Property::updateOrCreate(
            ['user_id' => $userId, 'address_line_1' => '19 Worth Court'],
            [
                'property_type' => 'buy_to_let',
                'ownership_type' => 'joint',
                'joint_owner_name' => 'wife',
                'tenure_type' => 'freehold',
                'country' => 'United Kingdom',
                'ownership_percentage' => 50.00,
                'city' => 'Unknown',
                'postcode' => 'N/A',
                'current_value' => 180000.00,
                'valuation_date' => '2026-04-01',
                'monthly_rental_income' => 900.00,
                'outstanding_mortgage' => 3500.00,
            ]
        );

        Mortgage::updateOrCreate(
            ['property_id' => $btl->id, 'lender_name' => 'To be completed'],
            [
                'user_id' => $userId,
                'country' => 'United Kingdom',
                'mortgage_type' => 'repayment',
                'outstanding_balance' => 3500.00,
                'interest_rate' => 0.0000,
                'rate_type' => 'fixed',
                'monthly_payment' => 0.00,
                'start_date' => '2026-04-01',
                'maturity_date' => '2051-04-01',
                'remaining_term_months' => 300,
                'ownership_type' => 'joint',
                'ownership_percentage' => 50.00,
            ]
        );

        // ── Savings: Cash ISA ─────────────────────────────────
        SavingsAccount::updateOrCreate(
            ['user_id' => $userId, 'institution' => 'Nationwide', 'account_type' => 'cash_isa'],
            [
                'ownership_type' => 'individual',
                'ownership_percentage' => 100.00,
                'current_balance' => 18500.00,
                'interest_rate' => 4.1000,
                'access_type' => 'immediate',
                'is_isa' => true,
                'isa_subscription_year' => '2025/26',
                'country' => 'United Kingdom',
            ]
        );

        // ── DC Pension: Scottish Widows ───────────────────────
        DCPension::updateOrCreate(
            ['user_id' => $userId, 'scheme_name' => 'Scottish Widows Workplace Pension'],
            [
                'scheme_type' => 'workplace',
                'provider' => 'Scottish Widows',
                'pension_type' => 'occupational',
                'current_fund_value' => 85000.00,
                'annual_salary' => 55000.00,
                'employee_contribution_percent' => 3.00,
                'employer_contribution_percent' => 5.00,
                'platform_fee_percent' => 0.8000,
                'platform_fee_type' => 'percentage',
                'platform_fee_frequency' => 'annually',
                'advisor_fee_percent' => 1.0000,
                'retirement_age' => 67,
                'risk_preference' => 'upper_medium',
                'has_custom_risk' => false,
                'has_flexibly_accessed' => false,
            ]
        );

        // ── Investment Account: Vanguard S&S ISA ──────────────
        $investmentAccount = InvestmentAccount::updateOrCreate(
            ['user_id' => $userId, 'provider' => 'Vanguard', 'account_type' => 'isa'],
            [
                'ownership_type' => 'individual',
                'ownership_percentage' => 100.00,
                'company_country' => 'United Kingdom',
                'country' => 'United Kingdom',
                'investment_currency' => 'GBP',
                'has_voting_rights' => true,
                'has_dividend_rights' => true,
                'holding_structure' => 'direct',
                'company_status' => 'active',
                'current_value' => 95000.00,
                'monthly_contribution_amount' => 0.00,
                'contribution_frequency' => 'monthly',
                'platform_fee_percent' => 0.5000,
                'platform_fee_type' => 'percentage',
                'platform_fee_frequency' => 'annually',
                'advisor_fee_percent' => 0.0000,
                'isa_type' => 'stocks_and_shares',
                'risk_preference' => 'upper_medium',
                'has_custom_risk' => false,
                'rebalance_threshold_percent' => 10.00,
                'include_in_retirement' => false,
                'scheme_status' => 'active',
                'grant_currency' => 'GBP',
            ]
        );

        // Holdings (only active — skip soft-deleted)
        Holding::updateOrCreate(
            ['holdable_id' => $investmentAccount->id, 'holdable_type' => InvestmentAccount::class, 'security_name' => 'FTSE Global All Cap Index', 'deleted_at' => null],
            [
                'asset_type' => 'fund',
                'allocation_percent' => 70.00,
                'current_value' => 66500.00,
                'dividend_yield' => 0.0000,
                'ocf_percent' => 1.5000,
            ]
        );

        Holding::updateOrCreate(
            ['holdable_id' => $investmentAccount->id, 'holdable_type' => InvestmentAccount::class, 'security_name' => 'UK Gilts', 'deleted_at' => null],
            [
                'asset_type' => 'bond',
                'allocation_percent' => 30.00,
                'current_value' => 28500.00,
                'dividend_yield' => 0.0000,
                'ocf_percent' => 0.7500,
            ]
        );

        // ── Risk Profile ──────────────────────────────────────
        RiskProfile::updateOrCreate(
            ['user_id' => $userId],
            [
                'risk_level' => 'upper_medium',
                'knowledge_level' => 'experienced',
                'esg_preference' => false,
                'risk_assessed_at' => now(),
                'is_self_assessed' => false,
            ]
        );

        // ── ISA Allowance Tracking ────────────────────────────
        ISAAllowanceTracking::updateOrCreate(
            ['user_id' => $userId, 'tax_year' => '2025/26'],
            [
                'cash_isa_used' => 0.00,
                'stocks_shares_isa_used' => 0.00,
                'lisa_used' => 0.00,
                'total_used' => 0.00,
                'total_allowance' => 20000.00,
            ]
        );

        // ── Business Interest: Jones Consulting ───────────────
        BusinessInterest::updateOrCreate(
            ['user_id' => $userId, 'business_name' => 'Jones Consulting'],
            [
                'business_type' => 'sole_trader',
                'ownership_type' => 'individual',
                'ownership_percentage' => 100.00,
                'country' => 'United Kingdom',
                'vat_registered' => false,
                'employee_count' => 0,
                'trading_status' => 'trading',
                'bpr_eligible' => false,
                'industry_sector' => 'Consulting',
                'current_valuation' => 150000.00,
                'valuation_date' => '2026-03-25',
                'annual_profit' => 60000.00,
            ]
        );

        // ── Chattel: Rolex Submariner ─────────────────────────
        Chattel::updateOrCreate(
            ['user_id' => $userId, 'name' => 'vintage Rolex Submariner watch'],
            [
                'chattel_type' => 'jewelry',
                'ownership_type' => 'individual',
                'country' => 'United Kingdom',
                'ownership_percentage' => 100.00,
                'purchase_price' => 8000.00,
                'current_value' => 15000.00,
                'valuation_date' => '2026-03-25',
            ]
        );

        // ── Goal 1: House Deposit ─────────────────────────────
        Goal::updateOrCreate(
            ['user_id' => $userId, 'goal_name' => 'House Deposit'],
            [
                'goal_type' => 'home_deposit',
                'target_amount' => 50000.00,
                'current_amount' => 0.00,
                'target_date' => '2028-12-31',
                'start_date' => '2026-03-25',
                'assigned_module' => 'property',
                'priority' => 'high',
                'status' => 'active',
                'contribution_frequency' => 'monthly',
                'ownership_type' => 'individual',
                'ownership_percentage' => 100.00,
                'deposit_percentage' => 10.00,
                'is_first_time_buyer' => false,
                'show_in_projection' => true,
                'show_in_household_view' => true,
                'use_global_risk_profile' => true,
            ]
        );

        // ── Goal 2: Emergency Fund ────────────────────────────
        Goal::updateOrCreate(
            ['user_id' => $userId, 'goal_name' => 'Emergency Fund'],
            [
                'goal_type' => 'emergency_fund',
                'target_amount' => 10000.00,
                'current_amount' => 0.00,
                'target_date' => '2027-06-01',
                'start_date' => '2026-03-27',
                'assigned_module' => 'savings',
                'priority' => 'medium',
                'status' => 'active',
                'contribution_frequency' => 'monthly',
                'ownership_type' => 'individual',
                'ownership_percentage' => 100.00,
                'show_in_projection' => true,
                'show_in_household_view' => true,
                'use_global_risk_profile' => true,
            ]
        );

        // ── Life Event: Inheritance ───────────────────────────
        LifeEvent::updateOrCreate(
            ['user_id' => $userId, 'event_name' => "Parents' Estate Inheritance"],
            [
                'event_type' => 'inheritance',
                'description' => "Inheritance of about £150,000 from parents' estate, expected within the next 5 years.",
                'amount' => 150000.00,
                'impact_type' => 'income',
                'expected_date' => '2028-07-01',
                'certainty' => 'likely',
                'show_in_projection' => true,
                'show_in_household_view' => true,
                'ownership_type' => 'individual',
                'ownership_percentage' => 100.00,
                'status' => 'expected',
            ]
        );

        // ── Family Member: Emma (daughter) ────────────────────
        FamilyMember::updateOrCreate(
            ['user_id' => $userId, 'first_name' => 'Emma', 'relationship' => 'child'],
            [
                'last_name' => 'Jones',
                'name' => 'Emma Jones',
                'date_of_birth' => '2015-03-20',
                'gender' => 'female',
                'is_dependent' => true,
                'education_status' => 'primary',
                'receives_child_benefit' => true,
            ]
        );

        // ── Protection Profile ────────────────────────────────
        ProtectionProfile::updateOrCreate(
            ['user_id' => $userId],
            [
                'retirement_age' => 67,
                'health_status' => 'good',
                'smoker_status' => false,
            ]
        );

        // ── Retirement Profile ────────────────────────────────
        RetirementProfile::updateOrCreate(
            ['user_id' => $userId],
            [
                'current_age' => 33,
                'target_retirement_age' => 65,
            ]
        );

        $this->command->info("Chris Jones (chris@fynla.org) seeded with production-matching data.");
    }
}
