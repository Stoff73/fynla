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

describe('Savings API', function () {
    describe('GET /api/savings', function () {
        it('returns savings data for authenticated user', function () {
            $user = User::factory()->create();
            $account = SavingsAccount::factory()->create(['user_id' => $user->id]);
            $goal = SavingsGoal::factory()->create(['user_id' => $user->id]);

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
        });

        it('does not return other users data', function () {
            $user = User::factory()->create();
            $otherUser = User::factory()->create();
            SavingsAccount::factory()->create(['user_id' => $otherUser->id]);

            $response = $this->actingAs($user)->getJson('/api/savings');

            $response->assertOk()
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'accounts' => [],
                    ],
                ]);
        });
    });

    describe('POST /api/savings/accounts', function () {
        it('creates a new savings account', function () {
            $user = User::factory()->create();
            $data = [
                'account_type' => 'easy_access',
                'institution' => 'Test Bank',
                'current_balance' => 10000,
                'interest_rate' => 0.045,
                'access_type' => 'immediate',
                'is_isa' => false,
            ];

            $response = $this->actingAs($user)->postJson('/api/savings/accounts', $data);

            $response->assertCreated()
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'id',
                        'account_type',
                        'provider',
                    ],
                ]);

            expect(SavingsAccount::where('user_id', $user->id)->count())->toBe(1);
        });

        it('accepts empty request with defaults', function () {
            // All fields are nullable - account can be created with defaults
            $user = User::factory()->create();
            $response = $this->actingAs($user)->postJson('/api/savings/accounts', []);

            $response->assertCreated();
            expect(SavingsAccount::where('user_id', $user->id)->count())->toBe(1);
        });

        it('creates ISA account with proper fields', function () {
            $user = User::factory()->create();
            $data = [
                'account_type' => 'cash_isa',
                'institution' => 'Test Bank',
                'current_balance' => 5000,
                'interest_rate' => 0.05,
                'access_type' => 'immediate',
                'is_isa' => true,
                'isa_type' => 'cash',
                'isa_subscription_year' => '2025/26',
                'isa_subscription_amount' => 5000,
            ];

            $response = $this->actingAs($user)->postJson('/api/savings/accounts', $data);

            $response->assertCreated();

            $account = SavingsAccount::where('user_id', $user->id)->first();
            expect($account->is_isa)->toBeTrue();
            expect($account->isa_type)->toBe('cash');
        });
    });

    describe('PUT /api/savings/accounts/{id}', function () {
        it('updates an existing account', function () {
            $user = User::factory()->create();
            $account = SavingsAccount::factory()->create(['user_id' => $user->id]);

            $response = $this->actingAs($user)->putJson("/api/savings/accounts/{$account->id}", [
                'current_balance' => 15000,
            ]);

            $response->assertOk()
                ->assertJson([
                    'success' => true,
                    'message' => 'Savings account updated successfully',
                ]);

            expect($account->fresh()->current_balance)->toBe('15000.00');
        });

        it('prevents updating other users accounts', function () {
            $user = User::factory()->create();
            $otherUser = User::factory()->create();
            $account = SavingsAccount::factory()->create(['user_id' => $otherUser->id]);

            $response = $this->actingAs($user)->putJson("/api/savings/accounts/{$account->id}", [
                'current_balance' => 15000,
            ]);

            $response->assertNotFound();
        });
    });

    describe('DELETE /api/savings/accounts/{id}', function () {
        it('deletes an account', function () {
            $user = User::factory()->create();
            $account = SavingsAccount::factory()->create(['user_id' => $user->id]);

            $response = $this->actingAs($user)->deleteJson("/api/savings/accounts/{$account->id}");

            $response->assertOk()
                ->assertJson([
                    'success' => true,
                    'message' => 'Savings account deleted successfully',
                ]);

            expect(SavingsAccount::find($account->id))->toBeNull();
        });

        it('prevents deleting other users accounts', function () {
            $user = User::factory()->create();
            $otherUser = User::factory()->create();
            $account = SavingsAccount::factory()->create(['user_id' => $otherUser->id]);

            $response = $this->actingAs($user)->deleteJson("/api/savings/accounts/{$account->id}");

            $response->assertNotFound();
        });
    });

    describe('POST /api/savings/analyze', function () {
        it('returns savings analysis', function () {
            $user = User::factory()->create();
            SavingsAccount::factory()->create([
                'user_id' => $user->id,
                'current_balance' => 10000,
            ]);

            ExpenditureProfile::create([
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
        });
    });

    describe('GET /api/savings/isa-allowance/{taxYear}', function () {
        it('returns ISA allowance status', function () {
            $user = User::factory()->create();
            // Use hyphen format instead of slash
            $taxYear = '2025-26';
            $response = $this->actingAs($user)->getJson("/api/savings/isa-allowance/{$taxYear}");

            $response->assertOk()
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'cash_isa_used',
                        'stocks_shares_isa_used',
                        'lisa_used',
                        'total_used',
                        'total_allowance',
                        'remaining',
                        'percentage_used',
                    ],
                ]);
        });
    });
});
