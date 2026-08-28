<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Chattel;
use App\Models\CriticalIllnessPolicy;
use App\Models\DBPension;
use App\Models\DCPension;
use App\Models\Estate\Bequest;
use App\Models\Estate\Trust;
use App\Models\Estate\Will;
use App\Models\FamilyMember;
use App\Models\IncomeProtectionPolicy;
use App\Models\Investment\Holding;
use App\Models\Investment\InvestmentAccount;
use App\Models\LifeInsurancePolicy;
use App\Models\Mortgage;
use App\Models\PremiumEntitlement;
use App\Models\Property;
use App\Models\SavingsAccount;
use App\Models\SpousePermission;
use App\Models\StatePension;
use App\Models\User;
use App\Services\Expenditure\HouseholdExpenditureWriter;
use App\Support\SharedExpenditure;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * David & Sarah Jones — the `peak_earners` persona, as a REAL premium household.
 *
 * Source of truth: `tests/Persona/peak_earners.md`. Every figure below is that file's,
 * and where the file and the application disagree the file's own correction notes are
 * followed (see Premium Bonds).
 *
 * **Why this is not `PreviewUserSeeder`.** Preview personas are deliberately free-tier
 * and read-blocked: `PremiumEntitlementResolver::resolve()` short-circuits
 * `is_preview_user` straight to free, so a preview persona can never exercise a
 * premium surface. These are ordinary accounts you log into, with an entitlement row.
 *
 * **Premium without the payment gateway** (CSJ direction 2026-08-24). Entitlement is
 * resolved from live provider rows, not from `users.tier` — so setting the column alone
 * would leave the resolver answering "free" and every gate closed. A
 * `premium_entitlements` row is what actually grants it. The columns are set too, for
 * the handful of consumers that read them directly, and the provider reference is
 * stamped `TEST-MANUAL-GRANT` so nobody mistakes it for a real purchase.
 *
 * Idempotent: re-running updates in place rather than duplicating. Safe to run against
 * a seeded database.
 *
 *   php artisan db:seed --class=PremiumTestPersonaSeeder
 *
 * Login: david.jones@example.com / sarah.jones@example.com, password `Password1!`.
 * Local MFA codes come from the database — see CLAUDE.md "Authentication for Testing".
 */
class PremiumTestPersonaSeeder extends Seeder
{
    private const PASSWORD = 'Password1!';

    public function run(): void
    {
        $david = $this->upsertUser([
            'email' => 'david.jones@example.com',
            'first_name' => 'David',
            'surname' => 'Jones',
            'date_of_birth' => '1976-11-08',
            'gender' => 'male',
            'annual_employment_income' => 145000,
            'employment_status' => 'employed',
            'occupation' => 'Finance Director',
            'employer' => 'Global Finance Corp',
        ]);

        $sarah = $this->upsertUser([
            'email' => 'sarah.jones@example.com',
            'first_name' => 'Sarah',
            'surname' => 'Jones',
            'date_of_birth' => '1978-04-22',
            'gender' => 'female',
            'annual_employment_income' => 120000,
            'employment_status' => 'employed',
            'occupation' => 'GP Partner',
            'employer' => 'Surrey NHS Trust',
        ]);

        $this->purgeHouseholdData($david, $sarah);
        $this->linkHousehold($david, $sarah);
        $this->grantPremium($david);
        $this->grantPremium($sarah);

        $this->seedFamily($david, $sarah);
        $this->seedProperties($david, $sarah);
        $this->seedSavings($david, $sarah);
        $this->seedInvestments($david, $sarah);
        $this->seedPensions($david, $sarah);
        $this->seedProtection($david);
        $this->seedTrust($david);
        $this->seedChattels($david, $sarah);
        $this->seedWills($david, $sarah);
        $this->seedExpenditure($david);

        $this->command?->info('Premium test household ready: david.jones@example.com / sarah.jones@example.com (Password1!)');
    }

    /**
     * Clear this household's financial records before rebuilding them.
     *
     * Without it the seeder is not reproducible: an earlier run left rows whose
     * `account_name` was empty and whose address differed by a word, so the
     * `updateOrCreate` keys below missed them and created a second set. The household
     * then reads at DOUBLE the persona — 4 properties, 8 savings accounts, 6
     * investment accounts — which is worse than useless for verifying figures against
     * `tests/Persona/peak_earners.md`.
     *
     * **Scoped to exactly these two accounts, by email**, and force-deleted: a soft
     * delete leaves the row behind and several readers use `forUserOrJoint` reach that
     * would still find it.
     */
    private function purgeHouseholdData(User $david, User $sarah): void
    {
        $ids = [$david->id, $sarah->id];

        foreach ([
            Bequest::class,
            Will::class,
            Trust::class,
            Chattel::class,
            LifeInsurancePolicy::class,
            CriticalIllnessPolicy::class,
            IncomeProtectionPolicy::class,
            DBPension::class,
            StatePension::class,
            SavingsAccount::class,
            FamilyMember::class,
        ] as $model) {
            $model::whereIn('user_id', $ids)->forceDelete();
        }

        // Holdings hang off their account, so they go with it rather than by user id.
        foreach (InvestmentAccount::whereIn('user_id', $ids)->withTrashed()->get() as $account) {
            Holding::where('holdable_type', InvestmentAccount::class)->where('holdable_id', $account->id)->forceDelete();
        }
        foreach (DCPension::whereIn('user_id', $ids)->withTrashed()->get() as $pension) {
            Holding::where('holdable_type', DCPension::class)->where('holdable_id', $pension->id)->forceDelete();
        }

        InvestmentAccount::whereIn('user_id', $ids)->forceDelete();
        DCPension::whereIn('user_id', $ids)->forceDelete();

        // Mortgages before properties — the mortgage names the property.
        Mortgage::whereIn('user_id', $ids)->forceDelete();
        Property::whereIn('user_id', $ids)->forceDelete();
    }

    private function upsertUser(array $attributes): User
    {
        $email = $attributes['email'];
        unset($attributes['email']);

        return User::updateOrCreate(['email' => $email], $attributes + [
            'password' => Hash::make(self::PASSWORD),
            'email_verified_at' => now(),
            'is_preview_user' => false,
            'marital_status' => 'married',
            'domicile_status' => 'uk_domiciled',
            'country_of_birth' => 'United Kingdom',
            'target_retirement_age' => 60,
            'onboarding_completed' => true,
            'onboarding_fyn_step' => null,
            'address_line_1' => 'The Willows, 15 Chestnut Lane',
            'city' => 'Guildford',
            'county' => 'Surrey',
            'postcode' => 'GU1 4RH',
        ]);
    }

    /**
     * Reciprocal link plus the consent row a real acceptance leaves behind.
     *
     * W-0347 — `requested_at` AND `responded_at`, one row for the couple. A row with
     * `requested_at` NULL is the forged shape the re-ask migration exists to convert,
     * and seeding it would recreate the population that migration cleans up.
     */
    private function linkHousehold(User $david, User $sarah): void
    {
        $david->update(['spouse_id' => $sarah->id]);
        $sarah->update(['spouse_id' => $david->id]);

        SpousePermission::where(function ($q) use ($david, $sarah) {
            $q->where('user_id', $david->id)->where('spouse_id', $sarah->id);
        })->orWhere(function ($q) use ($david, $sarah) {
            $q->where('user_id', $sarah->id)->where('spouse_id', $david->id);
        })->delete();

        SpousePermission::create([
            'user_id' => $david->id,
            'spouse_id' => $sarah->id,
            'status' => 'accepted',
            'requested_at' => now()->subMonths(6),
            'responded_at' => now()->subMonths(6)->addHour(),
        ]);
    }

    private function grantPremium(User $user): void
    {
        $user->update(['tier' => 'premium', 'plan' => 'premium']);

        PremiumEntitlement::updateOrCreate(
            ['user_id' => $user->id, 'provider' => PremiumEntitlement::PROVIDER_APPLE],
            [
                'provider_reference' => 'TEST-MANUAL-GRANT-'.$user->id,
                'product_id' => 'org.fynla.premium.annual',
                'status' => PremiumEntitlement::STATUS_ACTIVE,
                'will_renew' => true,
                'period_start' => now()->subMonth(),
                'period_end' => now()->addYears(5),
                'last_verified_at' => now(),
                'provider_metadata' => ['granted_by' => 'PremiumTestPersonaSeeder', 'reason' => 'testing, no payment taken'],
            ]
        );
    }

    private function seedFamily(User $david, User $sarah): void
    {
        foreach ([$david, $sarah] as $parent) {
            foreach ([
                ['William', '2007-09-15', 'male'],
                ['Charlotte', '2010-02-28', 'female'],
            ] as [$firstName, $dob, $gender]) {
                FamilyMember::updateOrCreate(
                    ['user_id' => $parent->id, 'relationship' => 'child', 'first_name' => $firstName],
                    [
                        'last_name' => 'Jones',
                        'name' => $firstName.' Jones',
                        'date_of_birth' => $dob,
                        'gender' => $gender,
                        'is_dependent' => true,
                        'education_status' => 'secondary',
                    ]
                );
            }
        }

        // Each spouse's card on the other's account, carrying the link.
        foreach ([[$david, $sarah], [$sarah, $david]] as [$owner, $partner]) {
            FamilyMember::updateOrCreate(
                ['user_id' => $owner->id, 'relationship' => 'spouse'],
                [
                    'first_name' => $partner->first_name,
                    'last_name' => $partner->surname,
                    'name' => $partner->first_name.' '.$partner->surname,
                    'date_of_birth' => $partner->date_of_birth,
                    'gender' => $partner->gender,
                    'linked_user_id' => $partner->id,
                    'annual_income' => $partner->annual_employment_income,
                    'is_dependent' => false,
                ]
            );
        }
    }

    private function seedProperties(User $david, User $sarah): void
    {
        $willows = Property::updateOrCreate(
            ['user_id' => $david->id, 'address_line_1' => '15 Chestnut Lane'],
            [
                'joint_owner_id' => $sarah->id,
                'property_type' => 'main_residence',
                'ownership_type' => 'joint',
                'ownership_percentage' => 50,
                'city' => 'Guildford', 'county' => 'Surrey', 'postcode' => 'GU1 4RH',
                'country' => 'UK',
                'purchase_date' => '2012-04-01', 'purchase_price' => 625000,
                'current_value' => 850000, 'valuation_date' => now()->toDateString(),
                'monthly_council_tax' => 320, 'monthly_gas' => 95, 'monthly_electricity' => 70,
                'monthly_water' => 40, 'monthly_building_insurance' => 45,
                'monthly_contents_insurance' => 30, 'monthly_maintenance_reserve' => 100,
            ]
        );

        $flat = Property::updateOrCreate(
            ['user_id' => $david->id, 'address_line_1' => 'Flat 42, Riverside Apartments'],
            [
                'joint_owner_id' => $sarah->id,
                'property_type' => 'buy_to_let',
                'ownership_type' => 'joint',
                'ownership_percentage' => 50,
                'city' => 'London', 'postcode' => 'SE1 8XX', 'country' => 'UK',
                'purchase_date' => '2018-10-15', 'purchase_price' => 340000,
                'current_value' => 425000, 'valuation_date' => now()->toDateString(),
                'monthly_rental_income' => 1800,
                'tenant_name' => 'Mr & Mrs Johnson',
                'lease_start_date' => '2024-03-01', 'lease_end_date' => '2025-02-28',
                'monthly_building_insurance' => 35, 'monthly_service_charge' => 285,
                'monthly_maintenance_reserve' => 100, 'other_monthly_costs' => 150,
            ]
        );

        // Tenants in common with a third party who has no account here — David holds
        // 40%. The other 60% is a stranger's and must never reach this household's
        // estate (the shape W-0331 and W-0475 both turn on).
        $manchester = Property::updateOrCreate(
            ['user_id' => $david->id, 'address_line_1' => 'Unit 12, Victoria Mill'],
            [
                'joint_owner_id' => null,
                'joint_owner_name' => 'Mike Barrett',
                'property_type' => 'buy_to_let',
                'ownership_type' => 'tenants_in_common',
                'ownership_percentage' => 40,
                'city' => 'Manchester', 'county' => 'Ancoats', 'postcode' => 'M4 6AG',
                'country' => 'UK',
                'purchase_date' => '2021-09-15', 'purchase_price' => 240000,
                'current_value' => 295000, 'valuation_date' => now()->toDateString(),
                'monthly_rental_income' => 1350,
                'tenant_name' => 'Ms Rachel Green',
                'lease_start_date' => '2024-06-01', 'lease_end_date' => '2025-05-31',
                'monthly_building_insurance' => 28, 'monthly_service_charge' => 195,
                'monthly_maintenance_reserve' => 85, 'other_monthly_costs' => 120,
            ]
        );

        $this->mortgage($willows, $david, $sarah, [
            'lender_name' => 'HSBC', 'outstanding_balance' => 65000, 'original_loan_amount' => 450000,
            'mortgage_type' => 'repayment', 'interest_rate' => 4.29, 'rate_type' => 'fixed',
            'fixed_interest_rate' => 4.29, 'rate_fix_end_date' => '2027-04-01',
            'monthly_payment' => 550, 'remaining_term_months' => 156,
            'ownership_type' => 'joint', 'ownership_percentage' => 50,
        ]);

        $this->mortgage($flat, $david, $sarah, [
            'lender_name' => 'Barclays', 'outstanding_balance' => 180000, 'original_loan_amount' => 272000,
            'mortgage_type' => 'interest_only', 'interest_rate' => 5.19, 'rate_type' => 'variable',
            'variable_interest_rate' => 5.19,
            'monthly_payment' => 650, 'remaining_term_months' => 180,
            'ownership_type' => 'joint', 'ownership_percentage' => 50,
        ]);

        $this->mortgage($manchester, $david, null, [
            'lender_name' => 'NatWest', 'outstanding_balance' => 120000, 'original_loan_amount' => 168000,
            'mortgage_type' => 'repayment', 'interest_rate' => 5.49, 'rate_type' => 'fixed',
            'fixed_interest_rate' => 5.49, 'rate_fix_end_date' => '2026-09-15',
            'monthly_payment' => 750, 'remaining_term_months' => 216,
            'ownership_type' => 'joint', 'ownership_percentage' => 40,
            'joint_owner_name' => 'Mike Barrett',
        ]);
    }

    private function mortgage(Property $property, User $owner, ?User $jointOwner, array $attributes): void
    {
        Mortgage::updateOrCreate(
            ['property_id' => $property->id, 'lender_name' => $attributes['lender_name']],
            $attributes + [
                'user_id' => $owner->id,
                'joint_owner_id' => $jointOwner?->id,
                'country' => 'UK',
                'start_date' => $property->purchase_date,
            ]
        );
    }

    private function seedSavings(User $david, User $sarah): void
    {
        $accounts = [
            [$david, 'HSBC Current Account', 'HSBC', 'current_account', 25000, 'individual', 100, null, null, null],
            [$sarah, 'Barclays Current Account', 'Barclays', 'current_account', 6280, 'individual', 100, null, null, null],
            [$david, 'Joint Current Account', 'Nationwide', 'current_account', 4500, 'joint', 50, $sarah->id, null, null],
            [$david, "David's Cash ISA", 'Nationwide', 'cash_isa', 22500, 'individual', 100, null, 4.25, 10000],
            [$sarah, "Sarah's Cash ISA", 'Nationwide', 'cash_isa', 22500, 'individual', 100, null, 4.25, 10000],
            // Persona-file note 2026-08-21: Premium Bonds cannot be held jointly — an
            // individual-only product, like an ISA. Held as David's, per that note.
            [$david, 'Premium Bonds', 'NS&I', 'premium_bonds', 50000, 'individual', 100, null, null, null],
        ];

        foreach ($accounts as [$owner, $name, $institution, $type, $balance, $ownership, $percentage, $jointOwnerId, $rate, $isaSubscription]) {
            SavingsAccount::updateOrCreate(
                ['user_id' => $owner->id, 'account_name' => $name],
                [
                    'institution' => $institution,
                    'account_type' => $type,
                    'current_balance' => $balance,
                    'ownership_type' => $ownership,
                    'ownership_percentage' => $percentage,
                    'joint_owner_id' => $jointOwnerId,
                    // NOT NULL on this column — a current account earning nothing is
                    // zero, not "unknown".
                    'interest_rate' => $rate ?? 0,
                    'is_isa' => $isaSubscription !== null,
                    'isa_type' => $isaSubscription !== null ? 'cash_isa' : null,
                    'isa_subscription_amount' => $isaSubscription,
                    'isa_subscription_year' => $isaSubscription !== null ? '2026/27' : null,
                    'country' => 'UK',
                    'access_type' => 'immediate',
                ]
            );
        }
    }

    private function seedInvestments(User $david, User $sarah): void
    {
        $davidIsa = $this->investmentAccount($david, null, "David's Stocks & Shares ISA", 'Hargreaves Lansdown', 'isa', 95000, 'individual', 100, 0.45, 0.75, 'high');
        $this->holdings($davidIsa, [
            ['Fundsmith Equity', 'FUND', 'GB00B41YBW71', 'fund', 351, 85.50, 99.86, 0.95],
            ['Scottish Mortgage Investment Trust', 'SMT', 'GB00BLDYK618', 'uk_equity', 2500, 8.40, 10.00, 0.34],
            ['Vanguard FTSE All-World', 'VWRL', 'IE00B3RBWM25', 'etf', 318, 93.00, 109.99, 0.22],
        ]);

        $sarahIsa = $this->investmentAccount($sarah, null, "Sarah's Stocks & Shares ISA", 'Hargreaves Lansdown', 'isa', 85000, 'individual', 100, 0.45, 0.75, 'medium');
        $this->holdings($sarahIsa, [
            ['Vanguard LifeStrategy 80', 'VGLS80', 'GB00B4PQW151', 'fund', 333, 225.00, 255.00, 0.22],
        ]);

        $gia = $this->investmentAccount($david, $sarah, 'Joint General Investment Account', 'AJ Bell', 'gia', 95000, 'joint', 50, 0.25, 0.75, 'medium');
        $this->holdings($gia, [
            ['iShares Core MSCI World', 'SWDA', 'IE00B4L5Y983', 'etf', 625, 68.00, 80.00, 0.20],
            ['Vanguard UK Government Bond', 'VGOV', 'IE00B42WWV65', 'bond', 1316, 19.50, 19.00, 0.12],
            ['iShares Physical Gold', 'SGLN', 'IE00B4ND3602', 'alternative', 84, 195.00, 238.00, 0.12],
        ]);

        $this->investmentAccount($david, null, 'Venture Capital Trust Holdings', 'Various', 'vct', 30000, 'individual', 100, null, 0.75, 'high');
    }

    private function investmentAccount(User $owner, ?User $jointOwner, string $name, string $provider, string $type, float $value, string $ownership, float $percentage, ?float $platformFee, ?float $adviserFee, string $risk): InvestmentAccount
    {
        return InvestmentAccount::updateOrCreate(
            ['user_id' => $owner->id, 'account_name' => $name],
            [
                'provider' => $provider,
                'account_type' => $type,
                'current_value' => $value,
                'ownership_type' => $ownership,
                'ownership_percentage' => $percentage,
                'joint_owner_id' => $jointOwner?->id,
                'platform_fee_percent' => $platformFee,
                'advisor_fee_percent' => $adviserFee,
                'risk_preference' => $risk,
                'country' => 'UK',
                'isa_type' => $type === 'isa' ? 'stocks_and_shares_isa' : null,
            ]
        );
    }

    private function holdings(InvestmentAccount $account, array $rows): void
    {
        foreach ($rows as [$name, $ticker, $isin, $assetType, $units, $cost, $price, $ocf]) {
            Holding::updateOrCreate(
                ['holdable_id' => $account->id, 'holdable_type' => InvestmentAccount::class, 'ticker' => $ticker],
                [
                    'security_name' => $name,
                    'isin' => $isin,
                    'asset_type' => $assetType,
                    'quantity' => $units,
                    'purchase_price' => $cost,
                    'current_price' => $price,
                    'current_value' => round($units * $price, 2),
                    'cost_basis' => round($units * $cost, 2),
                    'ocf_percent' => $ocf,
                ]
            );
        }
    }

    private function seedPensions(User $david, User $sarah): void
    {
        DCPension::updateOrCreate(
            ['user_id' => $david->id, 'scheme_name' => 'Global Finance Corp Pension'],
            [
                'provider' => 'Fidelity',
                'pension_type' => 'occupational',
                'current_fund_value' => 180000,
                'annual_salary' => 145000,
                'employee_contribution_percent' => 8,
                'employer_contribution_percent' => 8,
                'employer_matching_limit' => 8,
                'retirement_age' => 60,
                'platform_fee_percent' => 0.35,
                'risk_preference' => 'upper_medium',
            ]
        );

        $sipp = DCPension::updateOrCreate(
            ['user_id' => $david->id, 'scheme_name' => "David's SIPP"],
            [
                'provider' => 'AJ Bell',
                'pension_type' => 'sipp',
                'current_fund_value' => 320000,
                'retirement_age' => 60,
                'platform_fee_percent' => 0.25,
                'risk_preference' => 'upper_medium',
            ]
        );

        $this->holdings2($sipp, [
            ['Vanguard Global Equity', 'VHVG', 'IE00BKX55S42', 'fund', 4211, 32.50, 38.00, 0.23],
            ['BlackRock Corporate Bond', 'SLXX', 'IE0032895942', 'bond', 800, 125.00, 120.00, 0.18],
            ['L&G UK Property', 'LGUKP', 'GB00BK35DT11', 'property', 50000, 1.35, 1.28, 0.68],
        ]);

        DBPension::updateOrCreate(
            ['user_id' => $sarah->id, 'scheme_name' => 'NHS Pension Scheme'],
            [
                'scheme_type' => 'public_sector',
                'accrued_annual_pension' => 35000,
                'normal_retirement_age' => 60,
                'inflation_protection' => 'cpi',
                'lump_sum_entitlement' => 105000,
                'spouse_pension_percent' => 50,
                'pensionable_service_years' => 18,
            ]
        );

        foreach ([$david, $sarah] as $user) {
            StatePension::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'state_pension_forecast_annual' => 11502,
                    'state_pension_age' => 67,
                    'ni_years_completed' => 30,
                    'ni_years_required' => 35,
                    'already_receiving' => false,
                ]
            );
        }
    }

    private function holdings2(DCPension $pension, array $rows): void
    {
        foreach ($rows as [$name, $ticker, $isin, $assetType, $units, $cost, $price, $ocf]) {
            Holding::updateOrCreate(
                ['holdable_id' => $pension->id, 'holdable_type' => DCPension::class, 'ticker' => $ticker],
                [
                    'security_name' => $name,
                    'isin' => $isin,
                    'asset_type' => $assetType,
                    'quantity' => $units,
                    'purchase_price' => $cost,
                    'current_price' => $price,
                    'current_value' => round($units * $price, 2),
                    'cost_basis' => round($units * $cost, 2),
                    'ocf_percent' => $ocf,
                ]
            );
        }
    }

    private function seedProtection(User $david): void
    {
        LifeInsurancePolicy::updateOrCreate(
            ['user_id' => $david->id, 'policy_number' => 'VIT-LT-456789'],
            [
                'policy_type' => 'level_term',
                'provider' => 'Vitality',
                'sum_assured' => 500000,
                'premium_amount' => 85,
                'premium_frequency' => 'monthly',
                'policy_start_date' => '2020-01-01',
                'policy_end_date' => '2040-01-01',
                'in_trust' => true,
                'joint_life' => true,
                // Stored as free text, not JSON — matches every existing row.
                'beneficiaries' => 'Sarah Jones (spouse), William Jones (child), Charlotte Jones (child)',
            ]
        );

        CriticalIllnessPolicy::updateOrCreate(
            ['user_id' => $david->id, 'policy_number' => 'LG-CI-789123'],
            [
                'policy_type' => 'standalone',
                'provider' => 'Legal & General',
                'sum_assured' => 200000,
                'premium_amount' => 125,
                'premium_frequency' => 'monthly',
                'policy_start_date' => '2020-01-01',
                'policy_end_date' => '2040-01-01',
            ]
        );
    }

    private function seedTrust(User $david): void
    {
        Trust::updateOrCreate(
            ['user_id' => $david->id, 'trust_name' => "Jones Children's Education Trust"],
            [
                'trust_type' => 'discretionary',
                'country' => 'UK',
                'trust_creation_date' => '2020-09-01',
                'initial_value' => 150000,
                'current_value' => 185000,
                'total_asset_value' => 185000,
                'last_valuation_date' => now()->toDateString(),
                'is_relevant_property_trust' => true,
                'settlor' => 'David Jones',
                'beneficiaries' => 'William Jones, Charlotte Jones',
                'trustees' => 'David Jones, Sarah Jones, Barclays Trustee Services',
                'purpose' => 'Education funding including university fees, accommodation and living expenses',
                'is_active' => true,
            ]
        );
    }

    private function seedChattels(User $david, User $sarah): void
    {
        $items = [
            [$david, 'Contemporary Art Collection', 'art', 35000, 22000, 'joint', 50, $sarah->id],
            [$david, '1967 Jaguar E-Type Series 1', 'vehicle', 85000, 45000, 'individual', 100, null],
            [$sarah, "Sarah's Engagement Ring", 'jewelry', 18000, 12000, 'individual', 100, null],
            [$david, 'Georgian Writing Desk', 'antique', 8500, 6200, 'joint', 50, $sarah->id],
            [$david, 'First Edition Book Collection', 'collectible', 4500, 2800, 'individual', 100, null],
            [$david, 'BMW X5 xDrive40i', 'vehicle', 42000, 65000, 'joint', 50, $sarah->id],
        ];

        foreach ($items as [$owner, $name, $type, $value, $cost, $ownership, $percentage, $jointOwnerId]) {
            Chattel::updateOrCreate(
                ['user_id' => $owner->id, 'name' => $name],
                [
                    'chattel_type' => $type,
                    'current_value' => $value,
                    'purchase_price' => $cost,
                    'ownership_type' => $ownership,
                    'ownership_percentage' => $percentage,
                    'joint_owner_id' => $jointOwnerId,
                    'country' => 'UK',
                    'valuation_date' => now()->toDateString(),
                ]
            );
        }
    }

    private function seedWills(User $david, User $sarah): void
    {
        foreach ([[$david, $sarah], [$sarah, $david]] as [$testator, $partner]) {
            $will = Will::updateOrCreate(
                ['user_id' => $testator->id],
                [
                    'has_will' => true,
                    'spouse_primary_beneficiary' => true,
                    'spouse_bequest_percentage' => 100,
                    'executor_name' => $partner->first_name.' '.$partner->surname.' & Barclays Wealth',
                    'executor_notes' => 'Mirror wills prepared by Henderson & Co Solicitors. Life insurance policies held in trust outside the estate.',
                    'will_last_updated' => '2022-03-15',
                ]
            );

            $bequests = [
                ['William Jones', 'individual', 'percentage', 50, null, 2, 'Receive at age 25, held in trust'],
                ['Charlotte Jones', 'individual', 'percentage', 50, null, 2, 'Receive at age 25, held in trust'],
                ['Cancer Research UK', 'charity', 'specific_amount', null, 10000, 1, null],
            ];

            foreach ($bequests as [$name, $beneficiaryType, $bequestType, $percentage, $amount, $priority, $conditions]) {
                Bequest::updateOrCreate(
                    ['will_id' => $will->id, 'beneficiary_name' => $name],
                    [
                        'user_id' => $testator->id,
                        'beneficiary_type' => $beneficiaryType,
                        'bequest_type' => $bequestType,
                        'percentage_of_estate' => $percentage,
                        'specific_amount' => $amount,
                        'priority_order' => $priority,
                        'conditions' => $conditions,
                    ]
                );
            }
        }
    }

    /**
     * £2,500 is the HOUSEHOLD's monthly spending, written through the one writer that
     * knows the sharing rule — so each account ends up holding its half, exactly as a
     * real joint household does, rather than both rows holding the whole figure.
     */
    private function seedExpenditure(User $david): void
    {
        app(HouseholdExpenditureWriter::class)->write($david->fresh(), [
            'expenditure_sharing_mode' => SharedExpenditure::MODE_JOINT,
            'food_groceries' => 450,
            'transport_fuel' => 150,
            'healthcare_medical' => 100,
            'insurance' => 100,
            'mobile_phones' => 50,
            'internet_tv' => 40,
            'subscriptions' => 30,
            'clothing_personal_care' => 100,
            'entertainment_dining' => 100,
            'holidays_travel' => 100,
            'school_fees' => 1000,
            'school_lunches' => 50,
            'school_extras' => 80,
            'children_activities' => 100,
            'gifts_charity' => 50,
            'monthly_expenditure' => 2500,
            'annual_expenditure' => 30000,
        ]);
    }
}
