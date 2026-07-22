<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\AI\Cost\AiCostAttributionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * CoALA Phase 5 item 8 (FR-M15) — admin cost-attribution dashboard data.
 */
beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true, 'email_verified_at' => now()]);
    Sanctum::actingAs($this->admin);

    $svc = app(AiCostAttributionService::class);
    $svc->record(['stage' => 'reasoner', 'model' => 'grok-4.3', 'session_mode' => 'advice', 'action_type' => 'reason', 'gbp_cost' => 0.01, 'gbp_cost_priced' => true]);
    $svc->record(['stage' => 'reasoner', 'model' => 'grok-4.3', 'session_mode' => 'advice', 'action_type' => 'reason', 'gbp_cost' => 0.02, 'gbp_cost_priced' => true]);
    $svc->record(['stage' => 'reasoner', 'model' => 'grok-4.3', 'session_mode' => 'onboarding', 'action_type' => 'ground', 'gbp_cost' => 0.03, 'gbp_cost_priced' => false]);
});

it('returns aggregated cost attribution for an admin', function () {
    $response = $this->getJson('/api/admin/ai-cost-dashboard');

    $response->assertOk()
        ->assertJsonPath('data.total_calls', 3)
        ->assertJsonPath('data.unpriced_calls', 1);

    expect((float) $response->json('data.total_gbp'))->toBe(0.06);

    $byAction = collect($response->json('data.by_action_type'));
    expect((int) $byAction->firstWhere('action_type', 'reason')['calls'])->toBe(2)
        ->and((int) $byAction->firstWhere('action_type', 'ground')['calls'])->toBe(1);

    $byMode = collect($response->json('data.by_session_mode'));
    expect($byMode->pluck('session_mode')->sort()->values()->all())->toBe(['advice', 'onboarding']);
});

it('honours the days range filter', function () {
    $response = $this->getJson('/api/admin/ai-cost-dashboard?days=7');

    $response->assertOk()->assertJsonPath('data.range_days', 7);
});

it('forbids a non-admin', function () {
    Sanctum::actingAs(User::factory()->create(['is_admin' => false, 'email_verified_at' => now()]));

    $this->getJson('/api/admin/ai-cost-dashboard')->assertForbidden();
});
