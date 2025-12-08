<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\DCPension;
use App\Models\InvestmentAccount;
use App\Models\Liability;
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
     * Test ISA allowance updates across Savings and Investment modules
     */
    public function test_isa_allowance_is_tracked_across_savings_and_investment_modules(): void
    {
        // Add a Cash ISA contribution in Savings module
        $this->postJson('/api/savings/accounts', [
            'account_name' => 'Cash ISA',
            'account_type' => 'cash_isa',
            'current_balance' => 5000.00,
            'annual_contribution' => 3000.00,
        ])->assertStatus(201);

        // Add a Stocks & Shares ISA in Investment module
        $this->postJson('/api/investment/accounts', [
            'account_name' => 'S&S ISA',
            'account_type' => 'isa',
            'current_value' => 10000.00,
            'annual_contribution' => 8000.00,
        ])->assertStatus(201);

        // Get ISA allowance tracker from Savings module
        $savingsResponse = $this->getJson('/api/savings/isa-tracker')
            ->assertStatus(200);

        // Get ISA allowance tracker from Investment module
        $investmentResponse = $this->getJson('/api/investment/isa-tracker')
            ->assertStatus(200);

        // Total ISA contributions should be tracked
        $totalContributions = 3000 + 8000; // 11000
        $isaAllowance = 20000; // 2024/25 tax year
        $remainingAllowance = $isaAllowance - $totalContributions;

        // Both modules should report the same total and remaining allowance
        expect($savingsResponse->json('data.total_isa_contributions'))->toBe($totalContributions);
        expect($investmentResponse->json('data.total_isa_contributions'))->toBe($totalContributions);
        expect($savingsResponse->json('data.remaining_isa_allowance'))->toBe($remainingAllowance);
        expect($investmentResponse->json('data.remaining_isa_allowance'))->toBe($remainingAllowance);
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
            'description' => 'Main Residence',
            'current_value' => 400000.00,
        ]);

        // Add mortgage liability (£200,000)
        Liability::create([
            'user_id' => $this->user->id,
            'liability_type' => 'mortgage',
            'description' => 'Main Residence Mortgage',
            'outstanding_balance' => 200000.00,
            'monthly_payment' => 1200.00,
        ]);

        // Get Estate module net worth
        $estateResponse = $this->getJson('/api/estate/net-worth')
            ->assertStatus(200);

        // Expected net worth calculation:
        // Savings: £10,000
        // Investment: £50,000
        // Pension: £100,000
        // Property: £400,000
        // Mortgage: -£200,000
        // Total: £360,000
        $expectedNetWorth = 10000 + 50000 + 100000 + 400000 - 200000;

        expect($estateResponse->json('data.total_net_worth'))->toBe($expectedNetWorth);
        expect($estateResponse->json('data.total_assets'))->toBeGreaterThanOrEqual(560000);
        expect($estateResponse->json('data.total_liabilities'))->toBeGreaterThanOrEqual(200000);
    }

    /**
     * Test cash flow analysis uses data from all modules
     */
    public function test_cash_flow_analysis_includes_all_module_contributions(): void
    {
        // Create user profile with income
        $this->patchJson('/api/user/profile', [
            'annual_gross_income' => 60000.00,
            'annual_net_income' => 45000.00,
        ])->assertStatus(200);

        // Add protection premium (£100/month)
        $this->postJson('/api/protection/policies/life-insurance', [
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

        // Verify all contributions are included
        $data = $response->json('data');

        expect($data)->toHaveKey('monthly_contributions');
        expect($data['monthly_contributions'])->toHaveKey('protection');
        expect($data['monthly_contributions'])->toHaveKey('savings');
        expect($data['monthly_contributions'])->toHaveKey('investment');
        expect($data['monthly_contributions'])->toHaveKey('pension');

        // Total monthly outgoings should include all contributions
        $expectedMonthlyOutgoings = 100 + 500 + 300 + 400; // £1,300
        expect($data['total_monthly_contributions'])->toBeGreaterThanOrEqual($expectedMonthlyOutgoings);
    }

    /**
     * Test holistic plan integrates all module recommendations
     */
    public function test_holistic_plan_integrates_recommendations_from_all_modules(): void
    {
        // Create basic financial profile
        $this->patchJson('/api/user/profile', [
            'annual_gross_income' => 50000.00,
            'annual_net_income' => 38000.00,
            'date_of_birth' => '1985-01-01',
        ])->assertStatus(200);

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
            'asset_type' => 'cash',
            'description' => 'Savings',
            'current_value' => 12000.00,
        ]);

        // Generate holistic plan
        $response = $this->postJson('/api/holistic/plan')
            ->assertStatus(200);

        $plan = $response->json('data');

        // Verify plan includes recommendations from multiple modules
        expect($plan)->toHaveKey('ranked_recommendations');
        expect($plan['ranked_recommendations'])->toBeArray();
        expect(count($plan['ranked_recommendations']))->toBeGreaterThan(0);

        // Check that recommendations include different modules
        $modules = array_unique(array_column($plan['ranked_recommendations'], 'module'));
        expect(count($modules))->toBeGreaterThanOrEqual(2); // At least 2 modules

        // Verify executive summary is included
        expect($plan)->toHaveKey('executive_summary');
        expect($plan['executive_summary'])->toHaveKey('overall_score');
        expect($plan['executive_summary'])->toHaveKey('key_strengths');
        expect($plan['executive_summary'])->toHaveKey('key_vulnerabilities');
        expect($plan['executive_summary'])->toHaveKey('top_priorities');
    }

    /**
     * Test financial health score calculation uses all module scores
     */
    public function test_financial_health_score_aggregates_all_module_scores(): void
    {
        // Create comprehensive financial profile
        $this->patchJson('/api/user/profile', [
            'annual_gross_income' => 60000.00,
            'annual_net_income' => 45000.00,
            'date_of_birth' => '1980-01-01',
        ])->assertStatus(200);

        // Add good coverage across all modules

        // Savings: Good emergency fund (6 months)
        SavingsAccount::create([
            'user_id' => $this->user->id,
            'account_name' => 'Emergency Fund',
            'account_type' => 'savings_account',
            'current_balance' => 25000.00, // ~6 months expenses
        ]);

        // Protection: Good life insurance
        $this->postJson('/api/protection/policies/life-insurance', [
            'policy_name' => 'Life Insurance',
            'sum_assured' => 500000.00,
            'monthly_premium' => 50.00,
            'policy_type' => 'term',
            'term_years' => 25,
        ])->assertStatus(201);

        // Investment: Diversified portfolio
        InvestmentAccount::create([
            'user_id' => $this->user->id,
            'account_name' => 'ISA',
            'account_type' => 'isa',
            'current_value' => 50000.00,
        ]);

        // Retirement: Good pension
        DCPension::create([
            'user_id' => $this->user->id,
            'scheme_name' => 'Pension',
            'current_fund_value' => 150000.00,
            'employee_contribution_percent' => 8.00,
            'employer_contribution_percent' => 5.00,
            'current_salary' => 60000.00,
        ]);

        // Estate: Good net worth
        Asset::create([
            'user_id' => $this->user->id,
            'asset_type' => 'property',
            'description' => 'Main Residence',
            'current_value' => 400000.00,
        ]);

        // Get holistic plan with financial health score
        $response = $this->postJson('/api/holistic/plan')
            ->assertStatus(200);

        $plan = $response->json('data');

        // Verify overall score is calculated
        expect($plan['executive_summary']['overall_score'])->toBeGreaterThan(0);
        expect($plan['executive_summary']['overall_score'])->toBeLessThanOrEqual(100);

        // Verify module scores are included
        expect($plan)->toHaveKey('module_summaries');
        expect($plan['module_summaries'])->toBeArray();

        // Should have scores from multiple modules
        $modulesWithScores = array_filter($plan['module_summaries'], function ($module) {
            return isset($module['score']) && $module['score'] > 0;
        });

        expect(count($modulesWithScores))->toBeGreaterThanOrEqual(2);
    }
}
