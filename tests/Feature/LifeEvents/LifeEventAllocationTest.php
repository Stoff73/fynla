<?php

declare(strict_types=1);

use App\Models\LifeEvent;
use App\Models\LifeEventAllocation;
use App\Models\SavingsAccount;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * Wiring test for the life-event tax-optimised allocation endpoints.
 *
 * The controller + service + table already existed; only the routes were
 * unwired (the tab 404'd). These tests pin the HTTP contract the frontend
 * (goalsService.js allocations methods) depends on, plus user-scoping and
 * the preview-user no-persist guard.
 */
function makeExpenseEvent(User $user, float $amount = 10000): LifeEvent
{
    SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'account_type' => 'easy_access',
        'current_balance' => 50000,
        'is_isa' => false,
    ]);

    return LifeEvent::factory()->create([
        'user_id' => $user->id,
        'impact_type' => 'expense',
        'amount' => $amount,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
    ]);
}

it('generates and persists allocations on the first GET for a real user', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    $event = makeExpenseEvent($user);

    $response = $this->getJson("/api/life-events/{$event->id}/allocations");

    $response->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                'allocations',
                'summary' => ['event_amount', 'total_allocated', 'allocation_count', 'enabled_count'],
            ],
        ]);

    expect((float) $response->json('data.summary.event_amount'))->toBe(10000.0);
    // A cash-funded expense produces at least one allocation row, and it is persisted.
    expect(LifeEventAllocation::where('life_event_id', $event->id)->count())
        ->toBeGreaterThan(0)
        ->toBe((int) $response->json('data.summary.allocation_count'));
});

it('does not persist allocations for a preview user (generate-on-read is rolled back)', function () {
    $user = User::factory()->create(['is_preview_user' => true]);
    Sanctum::actingAs($user);
    $event = makeExpenseEvent($user);

    $response = $this->getJson("/api/life-events/{$event->id}/allocations");

    $response->assertOk();
    // Preview users see the computed suggestions but nothing is written (Rule 1).
    expect(LifeEventAllocation::where('life_event_id', $event->id)->count())->toBe(0);
});

it('updates an allocation amount and enabled flag', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    $event = LifeEvent::factory()->create(['user_id' => $user->id, 'impact_type' => 'expense', 'amount' => 5000]);
    $allocation = LifeEventAllocation::create([
        'life_event_id' => $event->id,
        'user_id' => $user->id,
        'allocation_type' => 'expense',
        'allocation_step' => 1,
        'account_type' => 'savings_account',
        'suggested_amount' => 5000,
        'amount' => 5000,
        'enabled' => true,
        'display_order' => 0,
    ]);

    $response = $this->putJson("/api/life-events/{$event->id}/allocations/{$allocation->id}", [
        'amount' => 2500,
        'enabled' => false,
    ]);

    $response->assertOk();
    expect((float) $allocation->fresh()->amount)->toBe(2500.0);
    expect($allocation->fresh()->enabled)->toBeFalse();
});

it('validates the update payload', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    $event = LifeEvent::factory()->create(['user_id' => $user->id, 'impact_type' => 'expense', 'amount' => 5000]);
    $allocation = LifeEventAllocation::create([
        'life_event_id' => $event->id, 'user_id' => $user->id, 'allocation_type' => 'expense',
        'allocation_step' => 1, 'suggested_amount' => 5000, 'amount' => 5000, 'enabled' => true, 'display_order' => 0,
    ]);

    $this->putJson("/api/life-events/{$event->id}/allocations/{$allocation->id}", ['amount' => -5])
        ->assertStatus(422);
});

it('cannot update an allocation on another user\'s life event (IDOR)', function () {
    $owner = User::factory()->create();
    $attacker = User::factory()->create();
    $event = LifeEvent::factory()->create(['user_id' => $owner->id, 'impact_type' => 'expense', 'amount' => 5000]);
    $allocation = LifeEventAllocation::create([
        'life_event_id' => $event->id, 'user_id' => $owner->id, 'allocation_type' => 'expense',
        'allocation_step' => 1, 'suggested_amount' => 5000, 'amount' => 5000, 'enabled' => true, 'display_order' => 0,
    ]);

    Sanctum::actingAs($attacker);
    $this->putJson("/api/life-events/{$event->id}/allocations/{$allocation->id}", [
        'amount' => 1, 'enabled' => false,
    ])->assertNotFound();

    // Unchanged.
    expect((float) $allocation->fresh()->amount)->toBe(5000.0);
});

it('returns 404 fetching allocations for another user\'s life event', function () {
    $owner = User::factory()->create();
    $event = LifeEvent::factory()->create(['user_id' => $owner->id, 'impact_type' => 'expense', 'amount' => 5000]);

    Sanctum::actingAs(User::factory()->create());
    $this->getJson("/api/life-events/{$event->id}/allocations")->assertNotFound();
});

it('regenerates allocations', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    $event = makeExpenseEvent($user);

    $this->getJson("/api/life-events/{$event->id}/allocations")->assertOk();

    $this->postJson("/api/life-events/{$event->id}/allocations/regenerate")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data' => ['allocations', 'summary']]);
});

it('requires authentication', function () {
    $event = LifeEvent::factory()->create(['impact_type' => 'expense', 'amount' => 5000]);
    $this->getJson("/api/life-events/{$event->id}/allocations")->assertUnauthorized();
});
