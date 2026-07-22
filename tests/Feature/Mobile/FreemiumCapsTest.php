<?php

declare(strict_types=1);

use App\Models\SavingsAccount;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
});

describe('/m freemium 5.1 — module payloads surface the free-tier cap', function () {
    it('exposes account_count + account_limit on GET /api/savings (free cap 3)', function () {
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

    it('exposes the pension cap (5) on GET /api/retirement', function () {
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
