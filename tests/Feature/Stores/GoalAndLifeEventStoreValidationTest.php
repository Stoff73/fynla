<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\Goal;
use App\Models\LifeEvent;
use App\Models\User;
use App\Services\Stores\Exceptions\StoreValidationException;
use App\Services\Stores\GoalStore;
use App\Services\Stores\IngestSource;
use App\Services\Stores\LifeEventStore;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;

/**
 * W-0501 — GoalStore and LifeEventStore validate their accepted-value columns.
 *
 * Both Stores contained no `Validator::make` at all before this: `create` merged
 * `user_id` into the caller's array and handed it to `Model::create` unexamined.
 * Twelve enum columns across the two tables had no list anywhere in the Store.
 *
 * **The first four cases are the ones that matter, and they are regression
 * cases.** Adding validation to a path that previously accepted anything is the
 * risk in this change — not the validation itself but what it might newly refuse.
 * Each of the four real callers is driven end to end here: Fyn's two capture
 * handlers through `CoordinatingAgent`, and the two form endpoints. If a rule is
 * ever tightened past what a caller sends, one of these four reddens.
 *
 * The rejection cases assert the boundary now exists, and that the error names the
 * field. That is the actual gain: `STRICT_TRANS_TABLES` already stopped a bad enum
 * reaching the column, but it stopped it as a raw SQLSTATE with nothing to
 * attribute the failure to, several layers below anything that could report it.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
    $this->user = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium']);
});

describe('the real callers still write', function () {
    it('still creates a goal through Fyn', function () {
        $method = (new ReflectionClass(CoordinatingAgent::class))->getMethod('handleCreateGoal');
        $method->setAccessible(true);

        $result = $method->invoke(app(CoordinatingAgent::class), [
            'name' => 'House deposit',
            'goal_type' => 'home_deposit',
            'target_amount' => 40000,
            'target_date' => now()->addYears(3)->toDateString(),
            'priority' => 'high',
        ], $this->user, false);

        expect($result['success'] ?? false)->toBeTrue();

        $this->assertDatabaseHas('goals', [
            'user_id' => $this->user->id,
            'goal_type' => 'home_deposit',
            'priority' => 'high',
        ]);
    });

    it('still creates a life event through Fyn', function () {
        $method = (new ReflectionClass(CoordinatingAgent::class))->getMethod('handleCreateLifeEvent');
        $method->setAccessible(true);

        $result = $method->invoke(app(CoordinatingAgent::class), [
            'event_name' => 'Inheritance from aunt',
            'event_type' => 'inheritance',
            'event_date' => now()->addYear()->toDateString(),
            'estimated_amount' => 25000,
            'certainty' => 'likely',
        ], $this->user, false);

        expect($result['success'] ?? false)->toBeTrue();

        $this->assertDatabaseHas('life_events', [
            'user_id' => $this->user->id,
            'event_type' => 'inheritance',
            'certainty' => 'likely',
        ]);
    });

    it('still creates a goal through the form endpoint', function () {
        $this->actingAs($this->user)->postJson('/api/goals', [
            'goal_name' => 'Emergency fund',
            'goal_type' => 'emergency_fund',
            'target_amount' => 12000,
            'target_date' => now()->addYear()->toDateString(),
            'priority' => 'critical',
        ])->assertSuccessful();

        $this->assertDatabaseHas('goals', [
            'user_id' => $this->user->id,
            'goal_type' => 'emergency_fund',
            // Set by GoalAssignmentService, not by the request — the Store had no
            // list for it, so this is the field most exposed to a new rule.
            'assigned_module' => 'savings',
        ]);
    });

    it('still creates a life event through the form endpoint', function () {
        $this->actingAs($this->user)->postJson('/api/life-events', [
            'event_name' => 'Kitchen refit',
            'event_type' => 'home_improvement',
            'amount' => 15000,
            'expected_date' => now()->addMonths(8)->toDateString(),
        ])->assertSuccessful();

        $this->assertDatabaseHas('life_events', [
            'user_id' => $this->user->id,
            'event_type' => 'home_improvement',
            // Derived by LifeEventService::createEvent, never sent by the client.
            'impact_type' => 'expense',
        ]);
    });
});

describe('an impossible value is refused, and named', function () {
    it('refuses a goal priority the column cannot store', function () {
        expect(fn () => app(GoalStore::class)->create(
            ['goal_name' => 'Something', 'goal_type' => 'custom', 'priority' => 'urgent'],
            $this->user,
            IngestSource::FYN_AI
        ))->toThrow(StoreValidationException::class);

        expect(Goal::where('user_id', $this->user->id)->count())->toBe(0);
    });

    it('names the offending field rather than failing anonymously', function () {
        try {
            app(GoalStore::class)->create(
                ['goal_name' => 'Something', 'goal_type' => 'custom', 'priority' => 'urgent'],
                $this->user,
                IngestSource::FYN_AI
            );
            $this->fail('Expected StoreValidationException');
        } catch (StoreValidationException $e) {
            // The whole point of the item: MySQL would have refused this too, but
            // as SQLSTATE 22001 against a column, with no field to report.
            expect(array_keys($e->errors))->toBe(['priority']);
        }
    });

    it('refuses a life event type the column cannot store', function () {
        expect(fn () => app(LifeEventStore::class)->create(
            ['event_name' => 'Something', 'event_type' => 'alien_abduction'],
            $this->user,
            IngestSource::FYN_AI
        ))->toThrow(StoreValidationException::class);

        expect(LifeEvent::where('user_id', $this->user->id)->count())->toBe(0);
    });

    it('refuses a dead event type the column stores but nothing creates', function () {
        // `divorce` IS in the life_events.event_type enum, so this is the Store
        // being deliberately narrower than its column — recorded in the guard's
        // DELIBERATELY_NARROWER. Accepting it would be worse than refusing it:
        // LifeEventService::createEvent derives impact_type from
        // INCOME_EVENT_TYPES, which does not list divorce, so it would be filed
        // as money going out.
        expect(fn () => app(LifeEventStore::class)->create(
            ['event_name' => 'Divorce', 'event_type' => 'divorce'],
            $this->user,
            IngestSource::FYN_AI
        ))->toThrow(StoreValidationException::class);
    });

    it('accepts every event type the application actually creates', function () {
        $creatable = array_merge(LifeEvent::INCOME_EVENT_TYPES, LifeEvent::EXPENSE_EVENT_TYPES);

        foreach ($creatable as $type) {
            app(LifeEventStore::class)->create(
                [
                    'event_name' => 'Event '.$type,
                    'event_type' => $type,
                    'amount' => 100,
                    // NOT NULL with no default, so the insert needs it. The
                    // rejection cases above omit it deliberately and still pass,
                    // which is itself the proof that validation runs before the
                    // write rather than after it.
                    'expected_date' => now()->addYear()->toDateString(),
                ],
                $this->user,
                IngestSource::FYN_AI
            );
        }

        expect(LifeEvent::where('user_id', $this->user->id)->count())->toBe(count($creatable));
    });
});
