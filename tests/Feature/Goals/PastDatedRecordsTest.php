<?php

declare(strict_types=1);

use App\Models\Goal;
use App\Models\LifeEvent;
use App\Models\User;
use Database\Seeders\TierConfigurationSeeder;

/**
 * W-0029 — goals and life events could not be dated today or earlier, so a
 * completed inheritance or a missed savings target had nowhere to go. The
 * update rules always accepted any date; only creation refused one.
 */
beforeEach(function () {
    $this->seed(TierConfigurationSeeder::class);
});

describe('Goals accept a target date that has already passed', function () {
    it('creates a goal dated in the past', function () {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/goals', [
            'goal_name' => 'Max Pension Contributions',
            'goal_type' => 'retirement',
            'target_amount' => 60000,
            'current_amount' => 45000,
            'target_date' => '2026-04-05',
            'priority' => 'high',
        ])->assertStatus(201);

        $this->assertDatabaseHas('goals', [
            'user_id' => $user->id,
            'goal_name' => 'Max Pension Contributions',
            'target_date' => '2026-04-05',
        ]);
    });

    it('creates a goal dated today', function () {
        $user = User::factory()->create();
        $today = now()->toDateString();

        $this->actingAs($user, 'sanctum')->postJson('/api/goals', [
            'goal_name' => 'Deadline Today',
            'goal_type' => 'emergency_fund',
            'target_amount' => 10000,
            'target_date' => $today,
            'priority' => 'medium',
        ])->assertStatus(201);

        $this->assertDatabaseHas('goals', [
            'user_id' => $user->id,
            'target_date' => $today,
        ]);
    });

    it('still edits a goal whose target date has passed', function () {
        $user = User::factory()->create();
        $goal = Goal::factory()->create([
            'user_id' => $user->id,
            'target_date' => '2020-06-01',
            'start_date' => '2019-06-01',
        ]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/goals/{$goal->id}", ['target_amount' => 25000])
            ->assertStatus(200);

        expect((float) $goal->fresh()->target_amount)->toBe(25000.0);
    });
});

describe('Life events accept a date that has already passed', function () {
    it('creates a life event dated in the past', function () {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/life-events', [
            'event_name' => "Previous Inheritance (David's Aunt)",
            'event_type' => 'inheritance',
            'amount' => 45000,
            'impact_type' => 'income',
            'expected_date' => '2020-03-15',
            'certainty' => 'confirmed',
        ])->assertStatus(201);

        $this->assertDatabaseHas('life_events', [
            'user_id' => $user->id,
            'expected_date' => '2020-03-15',
        ]);
    });

    it('creates a life event dated today', function () {
        $user = User::factory()->create();
        $today = now()->toDateString();

        $this->actingAs($user, 'sanctum')->postJson('/api/life-events', [
            'event_name' => 'Bonus Paid Today',
            'event_type' => 'bonus',
            'amount' => 35000,
            'impact_type' => 'income',
            'expected_date' => $today,
            'certainty' => 'confirmed',
        ])->assertStatus(201);

        $this->assertDatabaseHas('life_events', [
            'user_id' => $user->id,
            'expected_date' => $today,
        ]);
    });

    it('marks a past event completed through the existing endpoint', function () {
        $user = User::factory()->create();

        $created = $this->actingAs($user, 'sanctum')->postJson('/api/life-events', [
            'event_name' => 'Inheritance Received',
            'event_type' => 'inheritance',
            'amount' => 45000,
            'impact_type' => 'income',
            'expected_date' => '2020-03-15',
            'certainty' => 'confirmed',
        ])->assertStatus(201);

        $eventId = $created->json('data.id');

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/life-events/{$eventId}/complete")
            ->assertStatus(200);

        expect(LifeEvent::findOrFail($eventId)->status)->toBe('completed');
    });
});
