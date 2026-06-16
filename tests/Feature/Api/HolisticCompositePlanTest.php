<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RetirementActionDefinitionSeeder;
use Database\Seeders\TaxConfigurationSeeder;

/**
 * Phase 5.2 — the cross-module composite plan (CompositePlanService) is exposed
 * over HTTP at GET /api/holistic/composite-plan, feeding the /holistic-plan
 * composite view. Read-only; gated with the rest of the holistic group.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(RetirementActionDefinitionSeeder::class);
});

it('returns the cross-module composite plan for an authenticated user', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

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
