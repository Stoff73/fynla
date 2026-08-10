<?php

declare(strict_types=1);

use App\Models\Household;
use App\Models\Investment\Holding;
use App\Models\Investment\InvestmentAccount;
use App\Models\Investment\RiskProfile;
use App\Models\InvestmentAccountValueSnapshot;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);

    $this->household = Household::factory()->create();

    $this->user = User::factory()->create([
        'household_id' => $this->household->id,
        'date_of_birth' => now()->subYears(40),
        'target_retirement_age' => 67,
    ]);

    $this->actingAs($this->user, 'sanctum');
});

describe('GET /api/investment', function () {
    it('returns investment dashboard data', function () {
        InvestmentAccount::factory()->create([
            'user_id' => $this->user->id,
            'account_name' => 'Test ISA',
            'account_type' => 'isa',
            'current_value' => 50000,
        ]);

        $response = $this->getJson('/api/investment');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'accounts',
                ],
            ]);

        expect($response->json('success'))->toBe(true);
        expect($response->json('data.accounts'))->toHaveCount(1);
    });

    it('returns empty accounts array when no investments exist', function () {
        $response = $this->getJson('/api/investment');

        $response->assertStatus(200);
        expect($response->json('data.accounts'))->toBeEmpty();
    });

    it('returns the canonical holdings performance and drift contract for investments and stocks and shares ISAs', function () {
        RiskProfile::factory()->create([
            'user_id' => $this->user->id,
            'risk_level' => 'medium',
        ]);

        $account = InvestmentAccount::factory()->create([
            'user_id' => $this->user->id,
            'account_name' => 'Entered Portfolio ISA',
            'account_type' => 'isa',
            'isa_type' => 'stocks_and_shares',
            'current_value' => 100000,
            'entered_allocation_baseline' => ['equities' => 60, 'bonds' => 40],
            'entered_allocation_source' => 'user_entered',
            'entered_allocation_effective_at' => '2026-04-06',
        ]);

        Holding::factory()->forAccount($account)->create([
            'security_name' => 'Global shares',
            'asset_type' => 'equity',
            'current_value' => 70000,
            'cost_basis' => 50000,
            'ocf_percent' => 0.20,
        ]);
        Holding::factory()->forAccount($account)->create([
            'security_name' => 'UK gilts',
            'asset_type' => 'bond',
            'current_value' => 30000,
            'cost_basis' => 30000,
            'ocf_percent' => 0.10,
        ]);

        InvestmentAccountValueSnapshot::create([
            'investment_account_id' => $account->id,
            'column_name' => 'current_value',
            'value' => 90000,
            'currency' => 'GBP',
            'value_gbp' => 90000,
            'taken_at' => '2026-01-01 00:00:00',
            'trigger_reason' => 'manual_update',
            'ingest_source' => 'form',
        ]);
        InvestmentAccountValueSnapshot::create([
            'investment_account_id' => $account->id,
            'column_name' => 'current_value',
            'value' => 100000,
            'currency' => 'GBP',
            'value_gbp' => 100000,
            'taken_at' => '2026-08-01 00:00:00',
            'trigger_reason' => 'manual_update',
            'ingest_source' => 'form',
        ]);

        $portfolio = $this->getJson('/api/investment')
            ->assertOk()
            ->json('data.accounts.0.portfolio');

        expect($portfolio['contract_version'])->toBe('financial_portfolio_v1')
            ->and($portfolio['wrapper_type'])->toBe('stocks_and_shares_isa')
            ->and($portfolio['analysis']['coverage_percent'])->toBe(100)
            ->and($portfolio['analysis']['comparisons']['entered']['drift_percentage_points']['equities'])->toBe(10)
            ->and($portfolio['analysis']['comparisons']['recommended']['source'])->toBe('fynla_recommended_asset_allocation')
            ->and($portfolio['holdings'][0]['wrapper_percentage'])->toBe(70)
            ->and($portfolio['holdings'][0]['whole_relevant_portfolio_percentage'])->toBe(70)
            ->and($portfolio['holdings'][0]['performance'])->toMatchArray([
                'available' => true,
                'gain_loss' => 20000,
                'gain_loss_percent' => 40,
                'method' => 'recorded_cost_basis',
            ])
            ->and($portfolio['performance_history']['available'])->toBeTrue()
            ->and($portfolio['performance_history']['points'])->toHaveCount(2);
    });
});

describe('POST /api/investment/accounts', function () {
    it('creates a new investment account', function () {
        $data = [
            'account_type' => 'isa',
            'provider' => 'Vanguard',
            'current_value' => 25000,
            'tax_year' => '2025/26',
            'isa_type' => 'stocks_and_shares',
        ];

        $response = $this->postJson('/api/investment/accounts', $data);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('investment_accounts', [
            'user_id' => $this->user->id,
            'provider' => 'Vanguard',
            'account_type' => 'isa',
        ]);
    });

    it('validates required fields', function () {
        $response = $this->postJson('/api/investment/accounts', []);

        $response->assertStatus(422);
    });

    it('does not 500 when country arrives null in the payload', function () {
        $data = [
            'account_type' => 'gia',
            'provider' => 'Vanguard',
            'current_value' => 850000,
            'country' => null,
        ];

        $response = $this->postJson('/api/investment/accounts', $data);

        $response->assertStatus(201);

        $this->assertDatabaseHas('investment_accounts', [
            'user_id' => $this->user->id,
            'provider' => 'Vanguard',
            'country' => 'United Kingdom',
        ]);
    });

    it('does not 500 when country arrives empty string in the payload', function () {
        $data = [
            'account_type' => 'gia',
            'provider' => 'Vanguard',
            'current_value' => 100,
            'country' => '',
        ];

        $response = $this->postJson('/api/investment/accounts', $data);

        $response->assertStatus(201);

        $this->assertDatabaseHas('investment_accounts', [
            'user_id' => $this->user->id,
            'country' => 'United Kingdom',
        ]);
    });

    it('preserves an explicitly-supplied country', function () {
        $data = [
            'account_type' => 'gia',
            'provider' => 'Schwab',
            'current_value' => 5000,
            'country' => 'United States',
        ];

        $response = $this->postJson('/api/investment/accounts', $data);

        $response->assertStatus(201);

        $this->assertDatabaseHas('investment_accounts', [
            'user_id' => $this->user->id,
            'provider' => 'Schwab',
            'country' => 'United States',
        ]);
    });
});

describe('PUT /api/investment/accounts/{id}', function () {
    it('updates an investment account', function () {
        // Pin account_type/ownership_type: the factory randomises both, and an
        // isa + joint combo makes updateAccount correctly 422 (joint ISAs are
        // illegal). This test exercises the happy-path update only, so the
        // fixture must be deterministic and not hostage to Faker RNG state.
        $account = InvestmentAccount::factory()->create([
            'user_id' => $this->user->id,
            'provider' => 'Original Provider',
            'current_value' => 20000,
            'account_type' => 'gia',
            'ownership_type' => 'individual',
            'ownership_percentage' => 100.00,
        ]);

        $response = $this->putJson("/api/investment/accounts/{$account->id}", [
            'provider' => 'Updated Provider',
            'current_value' => 25000,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('investment_accounts', [
            'id' => $account->id,
            'provider' => 'Updated Provider',
        ]);
    });

    it('does not 500 when country arrives null on update — preserves existing value', function () {
        // Pin account_type/ownership_type: the factory randomises both, and an
        // isa + non-individual combo makes updateAccount correctly 422 (joint
        // ISAs are illegal). This test only exercises the country-null path, so
        // the fixture must be deterministic and not hostage to Faker RNG state.
        $account = InvestmentAccount::factory()->create([
            'user_id' => $this->user->id,
            'account_type' => 'gia',
            'ownership_type' => 'individual',
            'country' => 'United Kingdom',
            'provider' => 'Existing',
            'current_value' => 1000,
        ]);

        $response = $this->putJson("/api/investment/accounts/{$account->id}", [
            'provider' => 'Updated',
            'current_value' => 2000,
            'country' => null,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('investment_accounts', [
            'id' => $account->id,
            'provider' => 'Updated',
            'country' => 'United Kingdom',
        ]);
    });
});

describe('DELETE /api/investment/accounts/{id}', function () {
    it('deletes an investment account', function () {
        $account = InvestmentAccount::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->deleteJson("/api/investment/accounts/{$account->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertSoftDeleted('investment_accounts', [
            'id' => $account->id,
        ]);
    });
});
