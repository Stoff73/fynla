<?php

declare(strict_types=1);

use App\Models\Household;
use App\Models\Investment\InvestmentAccount;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);

    $this->household = Household::factory()->create();

    $this->user = User::factory()->create([
        'household_id' => $this->household->id,
        'date_of_birth' => now()->subYears(40),
        'target_retirement_age' => 67,
    ]);

    $this->actingAs($this->user, 'sanctum');
});

describe('GET /api/investment', function () {
    test('returns investment dashboard data', function () {
        InvestmentAccount::factory()->create([
            'user_id' => $this->user->id,
            'account_name' => 'Test ISA',
            'account_type' => 'stocks_and_shares_isa',
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

    test('returns empty accounts array when no investments exist', function () {
        $response = $this->getJson('/api/investment');

        $response->assertStatus(200);
        expect($response->json('data.accounts'))->toBeEmpty();
    });
});

describe('POST /api/investment/accounts', function () {
    test('creates a new investment account', function () {
        $data = [
            'account_name' => 'My New ISA',
            'account_type' => 'stocks_and_shares_isa',
            'provider' => 'Vanguard',
            'current_value' => 25000,
            'risk_level' => 'medium',
        ];

        $response = $this->postJson('/api/investment/accounts', $data);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('investment_accounts', [
            'user_id' => $this->user->id,
            'account_name' => 'My New ISA',
            'account_type' => 'stocks_and_shares_isa',
        ]);
    });

    test('validates required fields', function () {
        $response = $this->postJson('/api/investment/accounts', []);

        $response->assertStatus(422);
    });
});

describe('GET /api/investment/accounts/{id}', function () {
    test('returns a specific investment account', function () {
        $account = InvestmentAccount::factory()->create([
            'user_id' => $this->user->id,
            'account_name' => 'Test Account',
            'current_value' => 30000,
        ]);

        $response = $this->getJson("/api/investment/accounts/{$account->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        expect($response->json('data.account_name'))->toBe('Test Account');
    });

    test('returns 404 for non-existent account', function () {
        $response = $this->getJson('/api/investment/accounts/99999');

        $response->assertStatus(404);
    });

    test('returns 403 for account belonging to another user', function () {
        $otherUser = User::factory()->create([
            'household_id' => $this->household->id,
        ]);

        $account = InvestmentAccount::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this->getJson("/api/investment/accounts/{$account->id}");

        $response->assertStatus(403);
    });
});

describe('PUT /api/investment/accounts/{id}', function () {
    test('updates an investment account', function () {
        $account = InvestmentAccount::factory()->create([
            'user_id' => $this->user->id,
            'account_name' => 'Original Name',
            'current_value' => 20000,
        ]);

        $response = $this->putJson("/api/investment/accounts/{$account->id}", [
            'account_name' => 'Updated Name',
            'current_value' => 25000,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('investment_accounts', [
            'id' => $account->id,
            'account_name' => 'Updated Name',
            'current_value' => 25000,
        ]);
    });
});

describe('DELETE /api/investment/accounts/{id}', function () {
    test('deletes an investment account', function () {
        $account = InvestmentAccount::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->deleteJson("/api/investment/accounts/{$account->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseMissing('investment_accounts', [
            'id' => $account->id,
        ]);
    });
});

describe('GET /api/investment/summary', function () {
    test('returns investment summary with totals', function () {
        InvestmentAccount::factory()->create([
            'user_id' => $this->user->id,
            'current_value' => 50000,
        ]);

        InvestmentAccount::factory()->create([
            'user_id' => $this->user->id,
            'current_value' => 30000,
        ]);

        $response = $this->getJson('/api/investment/summary');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
            ]);
    });
});
