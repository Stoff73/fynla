<?php

declare(strict_types=1);

use App\Models\Goal;
use App\Models\LifeEvent;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Goals\GoalsProjectionService;
use App\Services\Goals\LifeEventIntegrationService;
use Illuminate\Support\Facades\Cache;

/**
 * W-0210 — a goal was counted as a life event, and W-0207 — an event that had
 * already happened was counted as money still to come. Two faults, both in
 * figures that name what they are counting and then count something else.
 *
 * W-0210: the projection summary partitioned its events by `impact`, and every
 * goal is stamped `impact => 'expense'` on the way in. So a savings target —
 * money the user is putting *aside* — was totalled as a cash outflow event on a
 * card titled "Life Events". One account with a single £400,000 target and no
 * life events at all read "1 cash outflow events £400K"; the other read "9 cash
 * outflow events £1.1M" beside its own events tab reading 6 events and
 * £355,000, the difference being three goals. One cause on both accounts, not
 * a double count.
 *
 * W-0207: nothing anywhere excluded an event whose date had passed. A confirmed
 * inheritance from 2020 sat inside a total labelled "Expected Income", under a
 * heading reading "Future occurrences", displayed as "In 0 years" because
 * `years_until_event` clamped a negative to zero and destroyed the only signal
 * that said otherwise.
 *
 * The predicate now has one home — `LifeEvent::hasOccurred()`, served to every
 * client as `has_occurred` — and the totals have one home in
 * `LifeEventService::summariseUpcoming()`, which the projection summary, the
 * module impact panel, the web events tab and the /m goals screen all read.
 */
beforeEach(function () {
    Cache::flush();

    $this->user = User::factory()->create(['date_of_birth' => '1976-11-08']);
    $this->projection = app(GoalsProjectionService::class);
});

function summaryFor(GoalsProjectionService $service, User $user): array
{
    Cache::flush();

    return $service->generateProjection($user->id)['summary'];
}

describe('a goal is not a life event', function () {
    it('leaves a goal out of the cash outflow event count and total', function () {
        Goal::factory()->create([
            'user_id' => $this->user->id,
            'goal_name' => 'Wealth Building',
            'target_amount' => 400_000,
            'target_date' => now()->addYears(9),
            'status' => 'active',
            'show_in_projection' => true,
        ]);

        $summary = summaryFor($this->projection, $this->user);

        // The account that read "1 cash outflow events £400K" with no life
        // events on file at all.
        expect($summary['expense_event_count'])->toBe(0)
            ->and($summary['total_expense_events'])->toBe(0.0)
            // The goal has not been hidden — it is counted as what it is.
            ->and($summary['goal_count'])->toBe(1);
    });

    it('counts only the life events when goals and life events sit side by side', function () {
        Goal::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'target_amount' => 100_000,
            'target_date' => now()->addYears(7),
            'status' => 'active',
            'show_in_projection' => true,
        ]);

        LifeEvent::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'event_type' => 'large_purchase',
            'impact_type' => 'expense',
            'amount' => 55_000,
            'expected_date' => now()->addYears(3),
            'status' => 'expected',
            'show_in_projection' => true,
        ]);

        $summary = summaryFor($this->projection, $this->user);

        expect($summary['expense_event_count'])->toBe(2)
            ->and($summary['total_expense_events'])->toBe(110_000.0)
            ->and($summary['goal_count'])->toBe(3);
    });

    it('still models the goal as an outflow in the projection itself', function () {
        // The fix is to the label, not to the arithmetic. A goal is still money
        // leaving the household in the year it falls due, which is what the
        // chart is for — it simply is not a life event.
        //
        // The household needs assets for this to be measurable at all: cash is
        // floored at zero, so on an empty balance sheet the projected net worth
        // reads 0.0 whether the goal is applied or not, and an assertion against
        // it passes for the wrong reason (tests/CLAUDE.md §4, the clamp).
        SavingsAccount::factory()->create([
            'user_id' => $this->user->id,
            'current_balance' => 900_000,
        ]);

        $withoutGoal = summaryFor($this->projection, $this->user)['ending_net_worth'];
        expect($withoutGoal)->toBeGreaterThan(0.0);

        Goal::factory()->create([
            'user_id' => $this->user->id,
            'target_amount' => 400_000,
            'target_date' => now()->addYears(9),
            'status' => 'active',
            'show_in_projection' => true,
        ]);

        expect(summaryFor($this->projection, $this->user)['ending_net_worth'])
            ->toBeLessThan($withoutGoal);
    });
});

describe('an event that has already happened is not money still to come', function () {
    function pastInheritance(User $user): LifeEvent
    {
        return LifeEvent::factory()->create([
            'user_id' => $user->id,
            'event_name' => "Previous Inheritance (David's Aunt)",
            'event_type' => 'inheritance',
            'impact_type' => 'income',
            'amount' => 45_000,
            'expected_date' => '2020-03-15',
            'certainty' => 'confirmed',
            'status' => 'expected',
            'show_in_projection' => true,
        ]);
    }

    it('keeps a past dated event out of expected income', function () {
        pastInheritance($this->user);

        LifeEvent::factory()->create([
            'user_id' => $this->user->id,
            'event_type' => 'property_sale',
            'impact_type' => 'income',
            'amount' => 350_000,
            'expected_date' => now()->addYears(20),
            'status' => 'expected',
            'show_in_projection' => true,
        ]);

        $summary = summaryFor($this->projection, $this->user);

        expect($summary['income_event_count'])->toBe(1)
            ->and($summary['total_income_events'])->toBe(350_000.0);
    });

    it('serves the same totals to every client from the life events endpoint', function () {
        pastInheritance($this->user);

        // Web and /m both render this rather than summing the list themselves,
        // so neither can drift back to counting a 2020 inheritance as income.
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/life-events')
            ->assertOk()
            ->assertJsonPath('data.summary.expected_income', 0)
            ->assertJsonPath('data.summary.income_count', 0)
            // The record itself is still returned: the user has to be able to
            // see a stale event in order to correct or complete it.
            ->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.events.0.has_occurred', true);
    });

    it('stops reporting a past event as being zero years away', function () {
        $event = pastInheritance($this->user);

        // The clamp used to return 0 here, which reads on screen as "In 0 years"
        // — the same words as an event happening this year.
        expect($event->years_until_event)->toBeLessThan(0)
            ->and($event->hasOccurred())->toBeTrue();
    });

    it('leaves a past event out of the module panel that calls itself upcoming', function () {
        pastInheritance($this->user);

        $impact = app(LifeEventIntegrationService::class)
            ->getModuleImpactSummary($this->user->id, 'estate');

        expect($impact['upcoming_income'])->toBe(0.0)
            ->and($impact['event_count'])->toBe(0)
            // It used to be named as the user's *next* event, six years after
            // the fact, because next_event takes the earliest date.
            ->and($impact['next_event'])->toBeNull();
    });

    it('counts an event dated today, because the day is not out yet', function () {
        LifeEvent::factory()->create([
            'user_id' => $this->user->id,
            'event_type' => 'bonus',
            'impact_type' => 'income',
            'amount' => 35_000,
            'expected_date' => now()->toDateString(),
            'status' => 'expected',
            'show_in_projection' => true,
        ]);

        expect(summaryFor($this->projection, $this->user)['total_income_events'])->toBe(35_000.0);
    });

    it('excludes an event the user has marked completed even where its date has not passed', function () {
        LifeEvent::factory()->create([
            'user_id' => $this->user->id,
            'event_type' => 'inheritance',
            'impact_type' => 'income',
            'amount' => 45_000,
            'expected_date' => now()->addYears(2),
            'status' => 'completed',
            'show_in_projection' => true,
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/life-events')
            ->assertOk()
            ->assertJsonPath('data.summary.expected_income', 0);
    });
});
