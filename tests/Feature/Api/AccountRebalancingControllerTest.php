<?php

declare(strict_types=1);

use App\Models\Investment\InvestmentAccount;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

/**
 * Smoke tests for AccountRebalancingController — the controller split out from
 * RebalancingCalculationController (PR follow-up to #292, tech-debt audit
 * Warnings #1 + #2). The service-level CGT logic is covered by
 * TaxAwareRebalancerCgt{Rate,Allowance}Test; these tests confirm the routes
 * still resolve, the ownership boundary still holds, and the empty-holdings
 * shortcut still returns the canonical shape.
 */
it('returns rebalancing data for an account the user owns (empty-holdings shortcut)', function () {
    $account = InvestmentAccount::factory()->create([
        'user_id' => $this->user->id,
        'account_type' => 'isa',
        'rebalance_threshold_percent' => 10.0,
    ]);

    $response = $this->getJson("/api/investment/accounts/{$account->id}/rebalancing");

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.account_id', $account->id)
        ->assertJsonPath('data.account_type', 'isa')
        ->assertJsonPath('data.is_tax_free', true)
        ->assertJsonPath('data.threshold_percent', 10)
        ->assertJsonStructure([
            'data' => [
                'risk_profile' => [
                    'user_risk_level',
                    'user_risk_label',
                    'has_custom_risk',
                    'effective_risk_level',
                    'effective_risk_label',
                ],
                'current_allocation',
                'target_allocation',
                'drift_analysis' => ['drift_score', 'max_drift', 'needs_rebalancing'],
                'rebalancing_actions',
                'cgt_analysis',
            ],
        ]);
});

it('returns 404 when the account belongs to another user', function () {
    $otherUser = User::factory()->create();
    $account = InvestmentAccount::factory()->create(['user_id' => $otherUser->id]);

    $this->getJson("/api/investment/accounts/{$account->id}/rebalancing")
        ->assertNotFound()
        ->assertJsonPath('success', false);
});

it('returns 404 for an unknown account id', function () {
    $this->getJson('/api/investment/accounts/999999/rebalancing')
        ->assertNotFound()
        ->assertJsonPath('success', false);
});

it('requires authentication for the rebalancing endpoint', function () {
    $this->app = $this->createApplication();
    $account = InvestmentAccount::factory()->create();

    $this->withHeaders(['Accept' => 'application/json'])
        ->getJson("/api/investment/accounts/{$account->id}/rebalancing")
        ->assertStatus(401);
});

it('updates the rebalancing threshold and persists the change', function () {
    $account = InvestmentAccount::factory()->create([
        'user_id' => $this->user->id,
        'rebalance_threshold_percent' => 10.0,
    ]);

    $this->patchJson("/api/investment/accounts/{$account->id}/rebalancing-threshold", [
        'threshold_percent' => 15.0,
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.threshold_percent', 15);

    expect($account->fresh()->rebalance_threshold_percent)->toEqual(15.0);
});

it('returns 404 when patching threshold on another user account', function () {
    $otherUser = User::factory()->create();
    $account = InvestmentAccount::factory()->create([
        'user_id' => $otherUser->id,
        'rebalance_threshold_percent' => 10.0,
    ]);

    $this->patchJson("/api/investment/accounts/{$account->id}/rebalancing-threshold", [
        'threshold_percent' => 15.0,
    ])->assertNotFound();

    expect($account->fresh()->rebalance_threshold_percent)->toEqual(10.0);
});

it('rejects threshold values outside the 1-50 range', function () {
    $account = InvestmentAccount::factory()->create([
        'user_id' => $this->user->id,
        'rebalance_threshold_percent' => 10.0,
    ]);

    $this->patchJson("/api/investment/accounts/{$account->id}/rebalancing-threshold", [
        'threshold_percent' => 0.5,
    ])->assertStatus(422);

    $this->patchJson("/api/investment/accounts/{$account->id}/rebalancing-threshold", [
        'threshold_percent' => 60.0,
    ])->assertStatus(422);

    expect($account->fresh()->rebalance_threshold_percent)->toEqual(10.0);
});

it('honours the account custom risk preference in the effective profile', function () {
    $account = InvestmentAccount::factory()->create([
        'user_id' => $this->user->id,
        'has_custom_risk' => true,
        'risk_preference' => 'high',
    ]);

    $response = $this->getJson("/api/investment/accounts/{$account->id}/rebalancing");

    $response->assertOk()
        ->assertJsonPath('data.risk_profile.has_custom_risk', true)
        ->assertJsonPath('data.risk_profile.account_risk_preference', 'high')
        ->assertJsonPath('data.risk_profile.effective_risk_level', 5)
        ->assertJsonPath('data.risk_profile.effective_risk_label', 'High');
});
