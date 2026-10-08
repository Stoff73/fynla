<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\BusinessInterest;
use App\Models\Chattel;
use App\Models\Goal;
use App\Models\Investment\InvestmentAccount;
use App\Models\LifeEvent;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Laravel\Sanctum\Sanctum;

/*
 * CSJ 2026-10-08: "for joint accounts, both parties have ownership", and "joint
 * should extend to cover joint goals, chattels and business interests". Either
 * owner changes or removes the record, which stays the primary owner's; a
 * co-owner named on a form must be the editor's linked spouse.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
    $this->owner = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium']);
    $this->partner = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium', 'spouse_id' => $this->owner->id]);
    $this->owner->update(['spouse_id' => $this->partner->id]);
    $this->stranger = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium']);
});

function jointWith(User $owner, User $partner, int $share = 60): array
{
    return ['user_id' => $owner->id, 'joint_owner_id' => $partner->id, 'ownership_type' => 'joint', 'ownership_percentage' => $share];
}

it('lets the joint owner change a joint chattel, storing their share as the other side of the split', function () {
    $chattel = Chattel::factory()->create(jointWith($this->owner, $this->partner) + ['current_value' => 20000]);
    Sanctum::actingAs($this->partner);

    $this->putJson("/api/chattels/{$chattel->id}", [
        'current_value' => 21000, 'ownership_type' => 'joint', 'ownership_percentage' => 30, 'joint_owner_id' => $this->owner->id,
    ])->assertOk();

    $fresh = $chattel->fresh();
    expect((float) $fresh->current_value)->toBe(21000.0)
        ->and((float) $fresh->ownership_percentage)->toBe(70.0)
        ->and($fresh->user_id)->toBe($this->owner->id)
        ->and($fresh->joint_owner_id)->toBe($this->partner->id);
});

it('lets the joint owner remove a joint chattel', function () {
    $chattel = Chattel::factory()->create(jointWith($this->owner, $this->partner));
    Sanctum::actingAs($this->partner);

    $this->deleteJson("/api/chattels/{$chattel->id}")->assertOk();

    expect(Chattel::find($chattel->id))->toBeNull();
});

it('lets the joint owner change a joint business interest, which keeps its owner', function () {
    $business = BusinessInterest::factory()->create(jointWith($this->owner, $this->partner, 50) + ['current_valuation' => 100000]);
    Sanctum::actingAs($this->partner);

    $response = $this->putJson("/api/business-interests/{$business->id}", ['current_valuation' => 120000]);

    $response->assertOk();
    $fresh = $business->fresh();
    expect((float) $fresh->current_valuation)->toBe(120000.0)
        ->and($fresh->user_id)->toBe($this->owner->id)
        ->and($fresh->joint_owner_id)->toBe($this->partner->id)
        ->and($response->json('data.business.is_primary_owner'))->toBeFalse();
});

it('lets the joint owner change and remove a joint goal', function () {
    $goal = Goal::factory()->create(jointWith($this->owner, $this->partner, 50) + ['target_amount' => 10000]);
    Sanctum::actingAs($this->partner);

    $this->putJson("/api/goals/{$goal->id}", ['target_amount' => 12000])->assertOk();
    expect((float) $goal->fresh()->target_amount)->toBe(12000.0)
        ->and($goal->fresh()->user_id)->toBe($this->owner->id);

    $this->deleteJson("/api/goals/{$goal->id}")->assertOk();
    expect(Goal::find($goal->id))->toBeNull();
});

it('still refuses someone who owns no part of the record', function () {
    $chattel = Chattel::factory()->create(jointWith($this->owner, $this->partner));
    Sanctum::actingAs($this->stranger);

    $this->putJson("/api/chattels/{$chattel->id}", ['current_value' => 1])->assertNotFound();
});

it('refuses a co-owner who is not the editor\'s linked spouse, from either owner', function () {
    $account = InvestmentAccount::factory()->gia()->create(jointWith($this->owner, $this->partner, 50));

    Sanctum::actingAs($this->partner);
    $this->putJson("/api/investment/accounts/{$account->id}", ['ownership_type' => 'joint', 'joint_owner_id' => $this->stranger->id])
        ->assertUnprocessable()->assertJsonValidationErrors('joint_owner_id');

    Sanctum::actingAs($this->owner);
    $this->putJson("/api/investment/accounts/{$account->id}", ['ownership_type' => 'joint', 'joint_owner_id' => $this->stranger->id])
        ->assertUnprocessable()->assertJsonValidationErrors('joint_owner_id');

    expect($account->fresh()->joint_owner_id)->toBe($this->partner->id);
});

it('lets Fyn change a joint goal for the joint owner, and nothing outside the ruling', function () {
    $goal = Goal::factory()->create(jointWith($this->owner, $this->partner, 50) + ['target_amount' => 10000]);
    $event = LifeEvent::factory()->create(jointWith($this->owner, $this->partner, 50));
    $agent = app(CoordinatingAgent::class);

    $goalResult = $agent->executeTool('update_record', ['entity_type' => 'goal', 'entity_id' => $goal->id, 'fields' => ['target_amount' => 15000]], $this->partner);
    $eventResult = $agent->executeTool('update_record', ['entity_type' => 'life_event', 'entity_id' => $event->id, 'fields' => ['event_name' => 'Changed']], $this->partner);

    expect($goalResult['success'] ?? false)->toBeTrue()
        ->and((float) $goal->fresh()->target_amount)->toBe(15000.0)
        ->and($eventResult['error'] ?? false)->toBeTrue();
});

it('keeps the joint owner on a goal they edit, and refuses a joint goal with no co-owner', function () {
    $goal = Goal::factory()->create(jointWith($this->owner, $this->partner, 50) + ['target_amount' => 6000]);
    Sanctum::actingAs($this->partner);

    // The form names the viewer's co-owner, which the goal payload now carries.
    $shown = $this->getJson('/api/goals')->json('data.goals.0');
    expect($shown['user_id'])->toBe($this->owner->id)
        ->and($shown['joint_owner_id'])->toBe($this->partner->id);

    $this->putJson("/api/goals/{$goal->id}", ['target_amount' => 6500, 'ownership_type' => 'joint', 'joint_owner_id' => $this->owner->id])->assertOk();
    expect($goal->fresh()->joint_owner_id)->toBe($this->partner->id)
        ->and((float) $goal->fresh()->target_amount)->toBe(6500.0);

    $this->putJson("/api/goals/{$goal->id}", ['target_amount' => 7000, 'ownership_type' => 'joint', 'joint_owner_id' => null])
        ->assertUnprocessable()->assertJsonValidationErrors('joint_owner_id');
    expect($goal->fresh()->joint_owner_id)->toBe($this->partner->id);
});
