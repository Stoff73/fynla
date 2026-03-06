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
});

describe('PUT /api/investment/accounts/{id}', function () {
    it('updates an investment account', function () {
        $account = InvestmentAccount::factory()->create([
            'user_id' => $this->user->id,
            'provider' => 'Original Provider',
            'current_value' => 20000,
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
