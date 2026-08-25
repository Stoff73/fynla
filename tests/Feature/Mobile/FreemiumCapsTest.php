<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\Investment\InvestmentAccount;
use App\Models\SavingsAccount;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
});

describe('/m freemium 5.1 — module payloads surface the free-tier cap', function () {
    it('exposes account_count + account_limit on GET /api/savings (free cap 2)', function () {
        $user = User::factory()->create(['tier' => 'free']);
        SavingsAccount::factory()->count(2)->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')->getJson('/api/savings')
            ->assertOk()
            ->assertJsonPath('data.account_count', 2)
            ->assertJsonPath('data.account_limit', 2);
    });

    it('exposes the investment cap (2) on GET /api/investment', function () {
        $user = User::factory()->create(['tier' => 'free']);

        $this->actingAs($user, 'sanctum')->getJson('/api/investment')
            ->assertOk()
            ->assertJsonPath('data.account_limit', 2);
    });

    it('exposes the pension cap (2) on GET /api/retirement', function () {
        $user = User::factory()->create(['tier' => 'free']);

        $this->actingAs($user, 'sanctum')->getJson('/api/retirement')
            ->assertOk()
            ->assertJsonPath('data.account_limit', 2);
    });

    it('reports an unlimited tier as null (no nudge) on GET /api/savings', function () {
        $user = User::factory()->withActivePremiumSubscription()->create();

        $this->actingAs($user, 'sanctum')->getJson('/api/savings')
            ->assertOk()
            ->assertJsonPath('data.account_limit', null);
    });
});

describe('authoritative financial creation limits', function () {
    it('returns the typed subscription destination when the savings cap is reached', function () {
        $user = User::factory()->create(['tier' => 'free']);
        SavingsAccount::factory()->count(2)->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')->postJson('/api/savings/accounts', [
            'account_type' => 'easy_access',
            'institution' => 'Third Bank',
            'current_balance' => 1000,
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
            'country' => 'United Kingdom',
        ])->assertForbidden()
            ->assertJsonPath('error', 'tier_limit_reached')
            ->assertJsonPath('action', 'subscription_options')
            ->assertJsonPath('destination.screen', 'subscription')
            ->assertJsonPath('destination.fallback', 'savings')
            ->assertJsonPath('entity_key', 'savings_account')
            ->assertJsonPath('current_count', 2)
            ->assertJsonPath('hard_limit', 2);

        expect(SavingsAccount::where('user_id', $user->id)->count())->toBe(2);
    });

    it('returns the same typed limit contract for investment accounts and pensions', function (string $route, array $payload, string $model, string $entityKey, string $fallback) {
        $user = User::factory()->create(['tier' => 'free']);
        $model::factory()->count(2)->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')->postJson($route, $payload)
            ->assertForbidden()
            ->assertJsonPath('error', 'tier_limit_reached')
            ->assertJsonPath('action', 'subscription_options')
            ->assertJsonPath('destination.screen', 'subscription')
            ->assertJsonPath('destination.fallback', $fallback)
            ->assertJsonPath('entity_key', $entityKey)
            ->assertJsonPath('current_count', 2)
            ->assertJsonPath('hard_limit', 2);

        expect($model::where('user_id', $user->id)->count())->toBe(2);
    })->with([
        'investment account' => [
            '/api/investment/accounts',
            [
                'account_type' => 'gia',
                'account_name' => 'Third Investment',
                'provider' => 'Third Provider',
                'current_value' => 1000,
                'ownership_type' => 'individual',
                'ownership_percentage' => 100,
            ],
            InvestmentAccount::class,
            'investment',
            'investment',
        ],
        'DC pension' => [
            '/api/retirement/pensions/dc',
            [
                'scheme_name' => 'Third Pension',
                'pension_type' => 'personal',
                'provider' => 'Third Provider',
                'current_fund_value' => 1000,
            ],
            DCPension::class,
            'pension_account',
            'retirement',
        ],
    ]);

    it('keeps an existing over-limit savings account readable and editable', function () {
        $user = User::factory()->create(['tier' => 'free']);
        $account = SavingsAccount::factory()->count(3)->create(['user_id' => $user->id])->last();

        $this->actingAs($user, 'sanctum')->getJson('/api/savings/accounts/'.$account->id)
            ->assertOk();

        $this->actingAs($user, 'sanctum')->putJson('/api/savings/accounts/'.$account->id, [
            'current_balance' => 4321,
        ])->assertOk();

        expect((float) $account->fresh()->current_balance)->toBe(4321.0);
    });

    it('returns the typed limit contract from legacy onboarding asset capture', function () {
        $user = User::factory()->create([
            'tier' => 'free',
            'life_stage' => 'estate',
        ]);
        SavingsAccount::factory()->count(2)->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')->postJson('/api/onboarding/step', [
            'step_name' => 'assets',
            'data' => [
                'cash' => [[
                    'institution' => 'Third Bank',
                    'account_type' => 'easy_access',
                    'current_balance' => 1000,
                    'ownership_type' => 'individual',
                ]],
            ],
        ])->assertForbidden()
            ->assertJsonPath('error', 'tier_limit_reached')
            ->assertJsonPath('destination.screen', 'subscription')
            ->assertJsonPath('destination.fallback', 'dashboard');

        expect(SavingsAccount::where('user_id', $user->id)->count())->toBe(2);
    });
});

describe('/m freemium 5.3 — Holistic Plan is gated to Premium', function () {
    it('returns a structured upgrade_required 403 for a free user', function () {
        $user = User::factory()->create(['tier' => 'free']);

        $this->actingAs($user, 'sanctum')->getJson('/api/holistic/composite-plan')
            ->assertStatus(403)
            ->assertJsonPath('error', 'capability_denied');
    });

    it('does not hit the upgrade gate for a Premium user', function () {
        $user = User::factory()->withActivePremiumSubscription()->create();

        // Premium passes the gate (no 403). We assert "not 403" rather than 200 so the
        // check is about the gate, not the composite-plan engine's downstream output.
        $status = $this->actingAs($user, 'sanctum')->getJson('/api/holistic/composite-plan')->status();
        expect($status)->not->toBe(403);
    });
});
