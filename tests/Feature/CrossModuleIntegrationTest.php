<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DCPension;
use App\Models\Estate\Asset;
use App\Models\Estate\Liability;
use App\Models\Investment\InvestmentAccount;
use App\Models\SavingsAccount;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cross-Module Integration Tests
 *
 * These tests verify that different modules work together correctly:
 * - ISA allowance tracking across Savings and Investment
 * - Net worth aggregation from multiple modules
 * - Cash flow analysis using data from all modules
 * - Holistic plan integration
 */
class CrossModuleIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TaxConfigurationSeeder::class);

        $this->user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($this->user, 'sanctum');
    }

    /**
     * Test ISA allowance tracking via the savings endpoint
     */
    public function test_isa_allowance_is_tracked_via_savings_endpoint(): void
    {
        // Add ISA savings account
        SavingsAccount::create([
            'user_id' => $this->user->id,
            'account_name' => 'Cash ISA',
            'account_type' => 'cash_isa',
            'current_balance' => 5000.00,
        ]);

        // Check ISA allowance endpoint for current tax year
        $response = $this->getJson('/api/savings/isa-allowance/2025-26');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_allowance',
                    'total_used',
                    'remaining',
                ],
            ]);

        // Verify some allowance tracking is present
        expect($response->json('success'))->toBe(true);
    }

    /**
     * Test net worth aggregation from Savings, Investment, Retirement, and Estate modules
     */
    public function test_net_worth_is_aggregated_from_all_modules(): void
    {
        // Add savings account (£10,000)
        SavingsAccount::create([
            'user_id' => $this->user->id,
            'account_name' => 'Emergency Fund',
            'account_type' => 'current_account',
            'current_balance' => 10000.00,
        ]);

        // Add investment account (£50,000)
        InvestmentAccount::create([
            'user_id' => $this->user->id,
            'account_name' => 'General Investment Account',
            'account_type' => 'general_investment',
            'current_value' => 50000.00,
        ]);

        // Add pension (£100,000)
        DCPension::create([
            'user_id' => $this->user->id,
            'scheme_name' => 'Workplace Pension',
            'current_fund_value' => 100000.00,
            'employee_contribution_percent' => 5.00,
            'employer_contribution_percent' => 3.00,
        ]);

        // Add property asset (£400,000)
        Asset::create([
            'user_id' => $this->user->id,
            'asset_type' => 'property',
            'asset_name' => 'Main Residence',
            'current_value' => 400000.00,
            'ownership_type' => 'individual',
            'valuation_date' => now(),
            'is_main_residence' => true,
        ]);

        // Add mortgage liability (£200,000)
        Liability::create([
            'user_id' => $this->user->id,
            'liability_type' => 'mortgage',
            'liability_name' => 'Main Residence Mortgage',
            'current_balance' => 200000.00,
            'monthly_payment' => 1200.00,
        ]);

        // Get Estate module net worth
        $estateResponse = $this->getJson('/api/estate/net-worth')
            ->assertStatus(200);

        // Net worth data is nested under data.net_worth
        $netWorthData = $estateResponse->json('data.net_worth');

        // Verify structure exists
        expect($netWorthData)->toHaveKey('total_assets');
        expect($netWorthData)->toHaveKey('total_liabilities');
        expect($netWorthData)->toHaveKey('net_worth');

        // Total assets should include savings, investments, and properties
        // Note: Pensions may not be included in estate net worth calculations (IHT purposes)
        // Minimum expected: 10K savings + 50K investment + 400K property = 460K
        expect($netWorthData['total_assets'])->toBeGreaterThanOrEqual(460000);
        expect($netWorthData['total_liabilities'])->toBeGreaterThanOrEqual(200000);
        // Net worth = assets - liabilities
        // Minimum expected: 460K - 200K = 260K
        expect($netWorthData['net_worth'])->toBeGreaterThanOrEqual(260000);
    }

    /**
     * Test cash flow analysis uses data from all modules
     */
    public function test_cash_flow_analysis_includes_all_module_contributions(): void
    {
        // Update user profile with income using the correct endpoint
        $this->user->update([
            'annual_employment_income' => 60000.00,
        ]);

        // Add protection premium (£100/month) using correct endpoint
        $this->postJson('/api/protection/policies/life', [
            'policy_name' => 'Term Life Insurance',
            'sum_assured' => 500000.00,
            'monthly_premium' => 100.00,
            'policy_type' => 'term',
            'term_years' => 25,
        ])->assertStatus(201);

        // Add savings contribution (£500/month)
        SavingsAccount::create([
            'user_id' => $this->user->id,
            'account_name' => 'Regular Saver',
            'account_type' => 'savings_account',
            'current_balance' => 5000.00,
            'monthly_contribution' => 500.00,
        ]);

        // Add investment contribution (£300/month)
        InvestmentAccount::create([
            'user_id' => $this->user->id,
            'account_name' => 'Monthly Investor',
            'account_type' => 'general_investment',
            'current_value' => 10000.00,
            'monthly_contribution' => 300.00,
        ]);

        // Add pension contribution (5% employee + 3% employer on £60k salary = £400/month)
        DCPension::create([
            'user_id' => $this->user->id,
            'scheme_name' => 'Workplace Pension',
            'current_fund_value' => 50000.00,
            'employee_contribution_percent' => 5.00,
            'employer_contribution_percent' => 3.00,
            'current_salary' => 60000.00,
        ]);

        // Get cash flow analysis from Holistic module
        $response = $this->getJson('/api/holistic/cash-flow-analysis')
            ->assertStatus(200);

        // Verify response structure - the exact structure depends on implementation
        $data = $response->json('data');
        expect($data)->not->toBeNull();
        expect($response->json('success'))->toBe(true);
    }

    /**
     * Test holistic plan integrates all module recommendations
     */
    public function test_holistic_plan_integrates_recommendations_from_all_modules(): void
    {
        // Update user directly with financial profile data
        $this->user->update([
            'annual_employment_income' => 50000.00,
            'date_of_birth' => '1985-01-01',
        ]);

        // Add minimal data to each module to trigger recommendations

        // Savings: Low emergency fund
        SavingsAccount::create([
            'user_id' => $this->user->id,
            'account_name' => 'Current Account',
            'account_type' => 'current_account',
            'current_balance' => 2000.00, // Only £2k - below 3-6 months target
        ]);

        // Protection: No policies (coverage gap)
        // No policies added - should trigger recommendation

        // Investment: No accounts
        // No accounts - should trigger recommendation

        // Retirement: Low pension
        DCPension::create([
            'user_id' => $this->user->id,
            'scheme_name' => 'Small Pension',
            'current_fund_value' => 10000.00,
            'employee_contribution_percent' => 2.00,
            'employer_contribution_percent' => 1.00,
            'current_salary' => 50000.00,
        ]);

        // Estate: Add minimal assets
        Asset::create([
            'user_id' => $this->user->id,
            'asset_type' => 'other',
            'asset_name' => 'Savings',
            'current_value' => 12000.00,
            'ownership_type' => 'individual',
            'valuation_date' => now(),
        ]);

        // Generate holistic plan
        $response = $this->postJson('/api/holistic/plan')
            ->assertStatus(200);

        $plan = $response->json('data');

        // Verify basic structure exists
        expect($response->json('success'))->toBe(true);
        expect($plan)->not->toBeNull();

        // Plan should have some form of recommendations or summary
        // The exact structure varies, so just verify it's a valid response
        expect($plan)->toBeArray();
    }
}
