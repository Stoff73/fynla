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

/**
 * W-0411 — the residual of the fix above.
 *
 * W-0029's acceptance ended at "the row lands in the database". It never asked
 * what the app then SAYS about a goal dated in the past — and the answer was
 * "On track", for every one of them, with the page summary reading "All goals
 * on track! Keep up the great progress" over a goal four and a half months
 * past its date at 75%.
 *
 * `start_date` is stamped with today at creation, so a past-dated goal stores a
 * span that runs BACKWARDS; Carbon 2's `diffInDays()` is absolute, so the
 * inverted range came back positive and the `$totalDays <= 0` guard — there for
 * exactly this — never fired.
 *
 * These sit in W-0029's own file deliberately. The gap was in its acceptance,
 * so this is where the guard belongs.
 */
describe('and the app tells the truth about them afterwards', function () {
    it('does not call a goal created against a date in the past "on track"', function () {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/goals', [
            'goal_name' => 'Max Pension Contributions',
            'goal_type' => 'retirement',
            'target_amount' => 60000,
            'current_amount' => 45000,
            'target_date' => now()->subMonths(4)->subDays(15)->toDateString(),
            'priority' => 'high',
        ])->assertStatus(201);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/goals')->assertOk();

        $goal = collect($response->json('data.goals'))
            ->firstWhere('goal_name', 'Max Pension Contributions');

        expect($goal)->not->toBeNull()
            ->and((float) $goal['progress_percentage'])->toBe(75.0)
            ->and($goal['is_on_track'])->toBeFalse()
            ->and($goal['is_overdue'])->toBeTrue()
            ->and($goal['status_label'])->toBe('Overdue');
    });

    it('calls an overdue goal that IS funded achieved late, not on track', function () {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/goals', [
            'goal_name' => "Charlotte's Gap Year Fund",
            'goal_type' => 'education',
            'target_amount' => 15000,
            'current_amount' => 15000,
            'target_date' => now()->subWeeks(3)->toDateString(),
            'priority' => 'medium',
        ])->assertStatus(201);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/goals')->assertOk();

        $goal = collect($response->json('data.goals'))
            ->firstWhere('goal_name', "Charlotte's Gap Year Fund");

        expect($goal['is_on_track'])->toBeFalse()
            ->and($goal['status_label'])->toBe('Achieved late');
    });

    it('stops the page summary claiming every goal is on track while one is overdue', function () {
        $user = User::factory()->create();

        // One healthy, one overdue — so on_track_count can be neither 0 nor all,
        // and the assertion cannot pass by everything having gone false.
        $this->actingAs($user, 'sanctum')->postJson('/api/goals', [
            'goal_name' => 'House Deposit',
            'goal_type' => 'home_deposit',
            'target_amount' => 40000,
            'current_amount' => 28000,
            'target_date' => now()->addYear()->toDateString(),
            'priority' => 'high',
        ])->assertStatus(201);
        $this->actingAs($user, 'sanctum')->postJson('/api/goals', [
            'goal_name' => 'Max Pension Contributions',
            'goal_type' => 'retirement',
            'target_amount' => 60000,
            'current_amount' => 45000,
            'target_date' => now()->subMonths(4)->toDateString(),
            'priority' => 'high',
        ])->assertStatus(201);

        $overview = $this->actingAs($user, 'sanctum')
            ->getJson('/api/goals/dashboard-overview')->assertOk();

        expect($overview->json('data.total_goals'))->toBe(2)
            ->and($overview->json('data.on_track_count'))->toBe(1);
    });
});
