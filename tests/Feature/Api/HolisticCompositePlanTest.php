<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RetirementActionDefinitionSeeder;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;

/**
 * Phase 5.2 — the cross-module composite plan (CompositePlanService) is exposed
 * over HTTP at GET /api/holistic/composite-plan, feeding the /holistic-plan
 * composite view. Read-only; premium-gated (TeaserGate holistic_plan) with the
 * rest of the holistic group, so the tier config the gate reads must be seeded.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
    $this->seed(RetirementActionDefinitionSeeder::class);
});

it('returns the cross-module composite plan for a premium (Tier 2+) user', function () {
    // The holistic group is now premium-gated (TeaserGate holistic_plan, /m
    // freemium 5.3): a free user gets a 403 upgrade_required, so the engine is
    // exercised as a Tier 2 user who can reach it.
    $user = User::factory()->create(['is_preview_user' => false, 'tier' => 'tier2']);

    $response = $this->actingAs($user)->getJson('/api/holistic/composite-plan');

    $response->assertOk()
        ->assertJson(['success' => true])
        ->assertJsonStructure([
            'success',
            'data' => [
                'items',
                'by_module',
                'locked',
                'available_monthly_surplus',
                'goal_commitments',
                'effective_surplus',
                'near_term_capital',
                'goals',
            ],
        ]);
});

it('requires authentication for the composite plan', function () {
    $this->getJson('/api/holistic/composite-plan')->assertUnauthorized();
});
