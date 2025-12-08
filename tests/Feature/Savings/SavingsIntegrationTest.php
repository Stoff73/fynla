<?php

declare(strict_types=1);

use App\Models\ExpenditureProfile;
use App\Models\SavingsAccount;
use App\Models\SavingsGoal;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

describe('Savings Integration Tests', function () {
    describe('Fetch savings data and display', function () {
        it('returns complete savings data for authenticated user', function () {
            $user = User::factory()->create();

            // Create test data
            $account = SavingsAccount::factory()->create([
                'user_id' => $user->id,
                'account_type' => 'easy_access',
                'institution' => 'Test Bank',
                'current_balance' => 10000,
                'interest_rate' => 0.045,
            ]);

            $goal = SavingsGoal::factory()->create([
                'user_id' => $user->id,
                'goal_name' => 'House Deposit',
                'target_amount' => 50000,
                'current_saved' => 10000,
                'target_date' => now()->addMonths(24),
            ]);

            $expenditure = ExpenditureProfile::create([
                'user_id' => $user->id,
                'monthly_housing' => 1000,
                'monthly_utilities' => 200,
                'monthly_food' => 300,
                'monthly_transport' => 200,
                'monthly_insurance' => 100,
                'monthly_loans' => 0,
                'monthly_discretionary' => 200,
                'total_monthly_expenditure' => 2000,
            ]);

            // Fetch savings data
            $response = $this->actingAs($user)->getJson('/api/savings');

            $response->assertOk()
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'accounts',
                        'goals',
                        'expenditure_profile',
                    ],
                ]);

            // Verify data integrity
            $data = $response->json('data');
            expect($data['accounts'])->toHaveCount(1);
            expect($data['goals'])->toHaveCount(1);
            expect($data['expenditure_profile'])->not->toBeNull();
            expect($data['accounts'][0]['institution'])->toBe('Test Bank');
            expect($data['goals'][0]['goal_name'])->toBe('House Deposit');
        });
    });

    describe('Analyze savings flow', function () {
        it('analyzes user savings and returns recommendations', function () {
            $user = User::factory()->create();

            // Create savings account with some balance
            SavingsAccount::factory()->create([
                'user_id' => $user->id,
                'current_balance' => 15000,
                'account_type' => 'easy_access',
            ]);

            // Create expenditure profile
            ExpenditureProfile::create([
                'user_id' => $user->id,
                'monthly_housing' => 1200,
                'monthly_utilities' => 150,
                'monthly_food' => 400,
                'monthly_transport' => 250,
                'monthly_insurance' => 200,
                'monthly_loans' => 0,
                'monthly_discretionary' => 300,
                'total_monthly_expenditure' => 2500,
            ]);

            // Analyze savings
            $response = $this->actingAs($user)->postJson('/api/savings/analyze');

            $response->assertOk()
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'summary',
                        'emergency_fund',
                        'isa_allowance',
                        'liquidity',
                        'goals',
                    ],
                ]);

            // Verify emergency fund calculation
            $data = $response->json('data');
            expect($data['emergency_fund']['runway_months'])->toBeInt();
            // £15,000 / £2,500 = 6 months runway
            expect($data['emergency_fund']['runway_months'])->toBe(6);
        });
    });

    describe('Create account flow', function () {
        it('creates savings account and updates data', function () {
            $user = User::factory()->create();

            // Create account
            $accountData = [
                'account_type' => 'fixed_1_year',
                'institution' => 'New Bank',
                'current_balance' => 20000,
                'interest_rate' => 0.055,
                'access_type' => 'fixed',
                'is_isa' => false,
                'maturity_date' => now()->addYear()->format('Y-m-d'),
            ];

            $createResponse = $this->actingAs($user)->postJson('/api/savings/accounts', $accountData);

            $createResponse->assertCreated()
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => ['id', 'account_type', 'institution'],
                ]);

            $accountId = $createResponse->json('data.id');

            // Verify account exists
            expect(SavingsAccount::find($accountId))->not->toBeNull();

            // Fetch all accounts to verify
            $fetchResponse = $this->actingAs($user)->getJson('/api/savings');
            $fetchResponse->assertOk();

            $accounts = $fetchResponse->json('data.accounts');
            expect($accounts)->toHaveCount(1);
            expect($accounts[0]['institution'])->toBe('New Bank');
        });

        it('creates ISA account and updates ISA allowance', function () {
            $user = User::factory()->create();

            // Create ISA account
            $isaData = [
                'account_type' => 'cash_isa',
                'institution' => 'ISA Provider',
                'current_balance' => 8000,
                'interest_rate' => 0.05,
                'access_type' => 'immediate',
                'is_isa' => true,
                'isa_type' => 'cash',
                'isa_subscription_year' => '2024-25',
                'isa_subscription_amount' => 8000,
            ];

            $response = $this->actingAs($user)->postJson('/api/savings/accounts', $isaData);

            $response->assertCreated();

            // Check ISA allowance
            $allowanceResponse = $this->actingAs($user)->getJson('/api/savings/isa-allowance/2024-25');
            $allowanceResponse->assertOk();

            $allowance = $allowanceResponse->json('data');
            expect($allowance['cash_isa_used'])->toBe(8000);
            expect($allowance['remaining'])->toBe(12000);
            expect($allowance['percentage_used'])->toBe(40);
        });
    });

    describe('Update account flow', function () {
        it('updates account balance and reflects in analysis', function () {
            $user = User::factory()->create();

            $account = SavingsAccount::factory()->create([
                'user_id' => $user->id,
                'current_balance' => 10000,
            ]);

            // Update balance
            $updateResponse = $this->actingAs($user)->putJson("/api/savings/accounts/{$account->id}", [
                'current_balance' => 15000,
            ]);

            $updateResponse->assertOk()
                ->assertJson([
                    'success' => true,
                    'message' => 'Savings account updated successfully',
                ]);

            // Verify update
            expect($account->fresh()->current_balance)->toBe('15000.00');

            // Check that analysis reflects new balance
            ExpenditureProfile::create([
                'user_id' => $user->id,
                'total_monthly_expenditure' => 3000,
                'monthly_housing' => 1200,
                'monthly_utilities' => 200,
                'monthly_food' => 400,
                'monthly_transport' => 200,
                'monthly_insurance' => 300,
                'monthly_loans' => 0,
                'monthly_discretionary' => 700,
            ]);

            $analysisResponse = $this->actingAs($user)->postJson('/api/savings/analyze');
            $analysisResponse->assertOk();

            $summary = $analysisResponse->json('data.summary');
            expect($summary['total_savings'])->toBe(15000);
        });
    });

    describe('Delete account flow', function () {
        it('deletes account and updates totals', function () {
            $user = User::factory()->create();

            $account1 = SavingsAccount::factory()->create([
                'user_id' => $user->id,
                'current_balance' => 10000,
            ]);

            $account2 = SavingsAccount::factory()->create([
                'user_id' => $user->id,
                'current_balance' => 5000,
            ]);

            // Delete first account
            $deleteResponse = $this->actingAs($user)->deleteJson("/api/savings/accounts/{$account1->id}");

            $deleteResponse->assertOk()
                ->assertJson([
                    'success' => true,
                    'message' => 'Savings account deleted successfully',
                ]);

            // Verify deletion
            expect(SavingsAccount::find($account1->id))->toBeNull();
            expect(SavingsAccount::find($account2->id))->not->toBeNull();

            // Verify only one account remains
            $fetchResponse = $this->actingAs($user)->getJson('/api/savings');
            $accounts = $fetchResponse->json('data.accounts');
            expect($accounts)->toHaveCount(1);
            expect($accounts[0]['id'])->toBe($account2->id);
        });
    });

    describe('Create goal flow', function () {
        it('creates savings goal and tracks progress', function () {
            $user = User::factory()->create();

            $goalData = [
                'goal_name' => 'Emergency Fund',
                'target_amount' => 18000,
                'current_saved' => 0,
                'target_date' => now()->addMonths(12)->format('Y-m-d'),
                'priority' => 'high',
            ];

            $createResponse = $this->actingAs($user)->postJson('/api/savings/goals', $goalData);

            $createResponse->assertCreated()
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => ['id', 'goal_name', 'target_amount'],
                ]);

            $goalId = $createResponse->json('data.id');

            // Verify goal exists
            $goal = SavingsGoal::find($goalId);
            expect($goal)->not->toBeNull();
            expect($goal->goal_name)->toBe('Emergency Fund');
            expect($goal->target_amount)->toBe('18000.00');
        });
    });

    describe('Update goal progress', function () {
        it('updates goal progress and recalculates status', function () {
            $user = User::factory()->create();

            $goal = SavingsGoal::factory()->create([
                'user_id' => $user->id,
                'goal_name' => 'Holiday Fund',
                'target_amount' => 5000,
                'current_saved' => 1000,
                'target_date' => now()->addMonths(10),
            ]);

            // Update progress (sets the amount, not adds to it)
            $updateResponse = $this->actingAs($user)->patchJson("/api/savings/goals/{$goal->id}/progress", [
                'amount' => 1500,
            ]);

            $updateResponse->assertOk()
                ->assertJson([
                    'success' => true,
                    'message' => 'Goal progress updated successfully',
                ]);

            // Verify update
            $goal->refresh();
            expect($goal->current_saved)->toBe('1500.00');

            // Verify in full data fetch
            $fetchResponse = $this->actingAs($user)->getJson('/api/savings');
            $goals = $fetchResponse->json('data.goals');

            $updatedGoal = collect($goals)->firstWhere('id', $goal->id);
            // API returns floats for currency
            expect((float) $updatedGoal['current_saved'])->toBe(1500.0);
        });
    });

    describe('Authorization checks', function () {
        it('prevents users from accessing other users data', function () {
            $user1 = User::factory()->create();
            $user2 = User::factory()->create();

            $account = SavingsAccount::factory()->create(['user_id' => $user1->id]);
            $goal = SavingsGoal::factory()->create(['user_id' => $user1->id]);

            // User 2 tries to access user 1's data
            $accountResponse = $this->actingAs($user2)->putJson("/api/savings/accounts/{$account->id}", [
                'current_balance' => 99999,
            ]);
            $accountResponse->assertNotFound();

            $goalResponse = $this->actingAs($user2)->patchJson("/api/savings/goals/{$goal->id}/progress", [
                'amount' => 9999,
            ]);
            $goalResponse->assertNotFound();

            // Verify data not modified
            expect($account->fresh()->current_balance)->toBe($account->current_balance);
            expect($goal->fresh()->current_saved)->toBe($goal->current_saved);
        });
    });

    describe('Complete user journey', function () {
        it('handles complete savings management workflow', function () {
            $user = User::factory()->create();

            // Step 1: Create expenditure profile
            $expenditure = ExpenditureProfile::create([
                'user_id' => $user->id,
                'monthly_housing' => 1500,
                'monthly_utilities' => 200,
                'monthly_food' => 500,
                'monthly_transport' => 300,
                'monthly_insurance' => 200,
                'monthly_loans' => 0,
                'monthly_discretionary' => 300,
                'total_monthly_expenditure' => 3000,
            ]);

            // Step 2: Create savings accounts
            $account1 = $this->actingAs($user)->postJson('/api/savings/accounts', [
                'account_type' => 'easy_access',
                'institution' => 'Bank A',
                'current_balance' => 10000,
                'interest_rate' => 0.04,
                'access_type' => 'immediate',
                'is_isa' => false,
            ]);
            $account1->assertCreated();

            $account2 = $this->actingAs($user)->postJson('/api/savings/accounts', [
                'account_type' => 'cash_isa',
                'institution' => 'Bank B',
                'current_balance' => 5000,
                'interest_rate' => 0.05,
                'access_type' => 'immediate',
                'is_isa' => true,
                'isa_type' => 'cash',
                'isa_subscription_year' => '2025/26', // Current tax year
                'isa_subscription_amount' => 5000,
            ]);
            $account2->assertCreated();

            // Step 3: Create savings goals
            $goal1 = $this->actingAs($user)->postJson('/api/savings/goals', [
                'goal_name' => 'Emergency Fund',
                'target_amount' => 18000,
                'current_saved' => 10000,
                'target_date' => now()->addMonths(12)->format('Y-m-d'),
                'priority' => 'high',
            ]);
            $goal1->assertCreated();

            $goal2 = $this->actingAs($user)->postJson('/api/savings/goals', [
                'goal_name' => 'House Deposit',
                'target_amount' => 50000,
                'current_saved' => 5000,
                'target_date' => now()->addMonths(36)->format('Y-m-d'),
                'priority' => 'high',
            ]);
            $goal2->assertCreated();

            // Step 4: Analyze savings
            $analysis = $this->actingAs($user)->postJson('/api/savings/analyze');
            $analysis->assertOk();

            $analysisData = $analysis->json('data');
            expect($analysisData['summary']['total_savings'])->toBe(15000);
            expect($analysisData['emergency_fund']['runway_months'])->toBe(5);
            expect($analysisData['isa_allowance']['cash_isa_used'])->toBe(5000);
            expect($analysisData['isa_allowance']['remaining'])->toBe(15000);

            // Step 5: Update goal progress (sets amount to 12000)
            $goalId = $goal1->json('data.id');
            $progressUpdate = $this->actingAs($user)->patchJson("/api/savings/goals/{$goalId}/progress", [
                'amount' => 12000,
            ]);
            $progressUpdate->assertOk();

            // Step 6: Verify final state
            $finalData = $this->actingAs($user)->getJson('/api/savings');
            $finalData->assertOk();

            $final = $finalData->json('data');
            expect($final['accounts'])->toHaveCount(2);
            expect($final['goals'])->toHaveCount(2);

            // Verify goal progress updated
            $updatedGoal = collect($final['goals'])->firstWhere('goal_name', 'Emergency Fund');
            expect((float) $updatedGoal['current_saved'])->toBe(12000.0);
        });
    });
});
