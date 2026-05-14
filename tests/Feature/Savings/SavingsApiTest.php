<?php

declare(strict_types=1);

use App\Models\ExpenditureProfile;
use App\Models\SavingsAccount;
use App\Models\SavingsGoal;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Laravel\Sanctum\Sanctum;

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

        it('does not 500 when country arrives null in the payload', function () {
            $user = User::factory()->create();
            $data = [
                'account_type' => 'easy_access',
                'institution' => 'Test Bank',
                'current_balance' => 10000,
                'country' => null,
            ];

            $response = $this->actingAs($user)->postJson('/api/savings/accounts', $data);

            $response->assertCreated();

            $this->assertDatabaseHas('savings_accounts', [
                'user_id' => $user->id,
                'country' => 'United Kingdom',
            ]);
        });

        it('does not 500 when country arrives empty string in the payload', function () {
            $user = User::factory()->create();
            $data = [
                'account_type' => 'easy_access',
                'institution' => 'Test Bank',
                'current_balance' => 100,
                'country' => '',
            ];

            $response = $this->actingAs($user)->postJson('/api/savings/accounts', $data);

            $response->assertCreated();

            $this->assertDatabaseHas('savings_accounts', [
                'user_id' => $user->id,
                'country' => 'United Kingdom',
            ]);
        });

        it('preserves an explicitly-supplied country on create', function () {
            $user = User::factory()->create();
            $data = [
                'account_type' => 'easy_access',
                'institution' => 'Bank of Ireland',
                'current_balance' => 5000,
                'country' => 'Ireland',
            ];

            $response = $this->actingAs($user)->postJson('/api/savings/accounts', $data);

            $response->assertCreated();

            $this->assertDatabaseHas('savings_accounts', [
                'user_id' => $user->id,
                'country' => 'Ireland',
            ]);
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

        it('does not 500 when country arrives null on update — preserves existing value', function () {
            $user = User::factory()->create();
            $account = SavingsAccount::factory()->create([
                'user_id' => $user->id,
                'country' => 'United Kingdom',
            ]);

            $response = $this->actingAs($user)->putJson("/api/savings/accounts/{$account->id}", [
                'current_balance' => 22000,
                'country' => null,
            ]);

            $response->assertOk();

            $this->assertDatabaseHas('savings_accounts', [
                'id' => $account->id,
                'country' => 'United Kingdom',
            ]);
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

    describe('SavingsStore integration via HTTP', function () {
        it('HTTP POST /api/savings/accounts persists via SavingsStore with IngestSource::FORM', function () {
            $user = User::factory()->create(['is_preview_user' => false]);
            Sanctum::actingAs($user);

            $response = $this->postJson('/api/savings/accounts', [
                'account_name' => 'Halifax Easy Saver',
                'account_type' => 'easy_access',
                'institution' => 'Halifax',
                'current_balance' => 12000,
                'interest_rate' => 4.2,
                'is_isa' => false,
            ]);

            $response->assertCreated();
            $this->assertDatabaseHas('savings_accounts', [
                'user_id' => $user->id,
                'account_name' => 'Halifax Easy Saver',
                'ownership_type' => 'individual',
                'country' => 'United Kingdom',
            ]);
            $account = SavingsAccount::where('user_id', $user->id)->where('account_name', 'Halifax Easy Saver')->firstOrFail();
            expect((float) $account->ownership_percentage)->toBe(100.0);
            expect((float) $account->current_balance)->toBe(12000.0);
        });

        it('HTTP POST infers UK country and 50/50 split for joint ISA', function () {
            $user = User::factory()->create();
            $spouse = User::factory()->create();
            Sanctum::actingAs($user);

            $this->postJson('/api/savings/accounts', [
                'account_name' => 'Joint Cash ISA',
                'account_type' => 'cash_isa',
                'current_balance' => 8000,
                'is_isa' => true,
                'ownership_type' => 'joint',
                'joint_owner_id' => $spouse->id,
            ])->assertCreated();

            $this->assertDatabaseHas('savings_accounts', [
                'account_name' => 'Joint Cash ISA',
                'country' => 'United Kingdom',
            ]);
            $account = SavingsAccount::where('account_name', 'Joint Cash ISA')->firstOrFail();
            expect((float) $account->ownership_percentage)->toBe(50.0);
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
