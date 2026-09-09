<?php

declare(strict_types=1);

use App\Models\RecommendationTracking;
use App\Models\User;
use App\Services\Coordination\RecommendationsAggregatorService;
use App\Services\Mobile\NextActionsService;

it('caps the unified list at four items', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    $items = app(NextActionsService::class)->build($user->id);

    expect(count($items))->toBeLessThanOrEqual(4);
});

it('emits an unlock item for a gated module carrying a fyn_capture action', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    $items = app(NextActionsService::class)->build($user->id);

    $unlock = collect($items)->firstWhere('type', 'unlock');
    expect($unlock)->not->toBeNull()
        ->and($unlock['action']['kind'])->toBe('fyn_capture')
        ->and($unlock['done'])->toBeFalse();
});

it('sorts by value descending', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    $items = app(NextActionsService::class)->build($user->id);
    $values = array_column($items, 'value');
    $sorted = $values;
    rsort($sorted);

    expect($values)->toEqual($sorted);
});

it('excludes a completed recommendation from the list (banked + replaced by next-best)', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    // Stub the aggregator with one stable recommendation so the id is deterministic
    // (real module recs fall back to uniqid() ids — see formatRecommendations()).
    $recommendationId = 'goals_test_rec_1';

    $aggregator = Mockery::mock(RecommendationsAggregatorService::class);
    $aggregator->shouldReceive('aggregateRecommendations')
        ->with($user->id)
        ->andReturn([[
            'recommendation_id' => $recommendationId,
            'module' => 'goals',
            'recommendation_text' => 'Increase your monthly goal contributions',
            'priority_score' => 70.0,
            'category' => 'goals',
            'potential_benefit' => null,
            // The aggregator merges recommendation_tracking status onto the rec (F18).
            'status' => 'completed',
        ]]);
    app()->instance(RecommendationsAggregatorService::class, $aggregator);

    // A matching completed tracking row removes the rec from the actionable list.
    RecommendationTracking::create([
        'user_id' => $user->id,
        'recommendation_id' => $recommendationId,
        'module' => 'goals',
        'recommendation_text' => 'Increase your monthly goal contributions',
        'priority_score' => 70.0,
        'status' => 'completed',
        'completed_at' => now(),
    ]);

    $items = app(NextActionsService::class)->build($user->id);

    // Completed recs are banked toward the wheel count + replaced, not shown
    // ticked — so the rec is absent from the actionable list (CSJ 4.4).
    $item = collect($items)->firstWhere('id', $recommendationId);
    expect($item)->toBeNull();
});

it('shows only the headline before the dash and carries the rest as detail', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    $aggregator = Mockery::mock(RecommendationsAggregatorService::class);
    $aggregator->shouldReceive('aggregateRecommendations')
        ->with($user->id)
        ->andReturn([
            [
                'recommendation_id' => 'savings_cash_isa_recommended',
                'module' => 'savings',
                'recommendation_text' => 'Consider a Cash ISA — Moving savings to a Cash ISA would shelter interest from tax.',
                'priority_score' => 60.0,
                'category' => 'Lifecycle',
                'potential_benefit' => null,
            ],
            [
                // A tax strategy title carries its own tagline dash: the row keeps
                // the words before the FIRST dash only.
                'recommendation_id' => 'tax_junior_pension',
                'module' => 'tax',
                'recommendation_text' => 'Open a pension for each child — instant £1,440 a year of free money — Anyone can contribute.',
                'priority_score' => 55.0,
                'category' => 'tax',
                'potential_benefit' => 1440,
            ],
            [
                // A hyphen inside a name is not a separator.
                'recommendation_id' => 'savings_rate_below_market',
                'module' => 'savings',
                'recommendation_text' => 'Better Rate Available for Chen Tech - Business Reserve',
                'priority_score' => 50.0,
                'category' => 'Lifecycle',
                'potential_benefit' => null,
            ],
        ]);
    app()->instance(RecommendationsAggregatorService::class, $aggregator);

    $items = collect(app(NextActionsService::class)->buildAll($user->id))->keyBy('id');

    expect($items['savings_cash_isa_recommended']['title'])->toBe('Consider a Cash ISA')
        ->and($items['savings_cash_isa_recommended']['detail'])->toBe('Moving savings to a Cash ISA would shelter interest from tax.')
        ->and($items['tax_junior_pension']['title'])->toBe('Open a pension for each child')
        ->and($items['tax_junior_pension']['detail'])->toBe('instant £1,440 a year of free money — Anyone can contribute.')
        ->and($items['savings_rate_below_market']['title'])->toBe('Better Rate Available for Chen Tech - Business Reserve')
        ->and($items['savings_rate_below_market']['detail'])->toBeNull();
});

it('routes recommendations by what they ask for: Fyn capture, the exact record, or the product page', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    $aggregator = Mockery::mock(RecommendationsAggregatorService::class);
    $aggregator->shouldReceive('aggregateRecommendations')
        ->with($user->id)
        ->andReturn([
            // Asks the user to record information → Fyn, in an income capture.
            ['recommendation_id' => 'savings_missing_income', 'module' => 'savings', 'recommendation_text' => 'Provide Your Income Details — needed for advice.', 'priority_score' => 90.0, 'category' => 'Data Readiness'],
            // Names an account → that account's page.
            ['recommendation_id' => 'savings_rate_below_market', 'module' => 'savings', 'recommendation_text' => 'Better Rate Available for Rainy Day — 2% below market.', 'priority_score' => 70.0, 'category' => 'Lifecycle', 'account_id' => 42],
            // Names a workplace pension → that pension's page.
            ['recommendation_id' => 'retirement_high_pension_total_fees', 'module' => 'retirement', 'recommendation_text' => 'Review total fees on Aviva — 1.4% a year.', 'priority_score' => 60.0, 'category' => 'Lifecycle', 'account_id' => 7],
            // A tax strategy about a Stocks & Shares move → investments.
            ['recommendation_id' => 'tax_bed_and_isa', 'module' => 'tax', 'recommendation_text' => 'Bed & ISA — shelter £20,000.', 'priority_score' => 55.0, 'category' => 'tax', 'potential_benefit' => 300],
            // A tax strategy about pension contributions → retirement.
            ['recommendation_id' => 'tax_salary_sacrifice_ni', 'module' => 'tax', 'recommendation_text' => 'Salary sacrifice — save NI.', 'priority_score' => 50.0, 'category' => 'tax', 'potential_benefit' => 400],
            // Nothing specific named → the module overview.
            ['recommendation_id' => 'investment_rebalance_portfolio', 'module' => 'investment', 'recommendation_text' => 'Rebalance Portfolio — drifted from target.', 'priority_score' => 40.0, 'category' => 'Lifecycle'],
        ]);
    app()->instance(RecommendationsAggregatorService::class, $aggregator);

    $items = collect(app(NextActionsService::class)->buildAll($user->id))->keyBy('id');

    expect($items['savings_missing_income']['action'])->toEqual([
        'kind' => 'fyn_capture',
        'payload' => 'savings',
        'contextual' => [
            'action' => 'add',
            'resource_type' => 'income',
            'resource_id' => null,
            'current_destination' => ['screen' => 'income', 'params' => (object) [], 'fallback' => 'dashboard'],
            'origin' => ['kind' => 'recommendation', 'recommendation_id' => 'savings_missing_income'],
        ],
    ]);

    expect($items['savings_rate_below_market']['action']['kind'])->toBe('navigate')
        ->and($items['savings_rate_below_market']['action']['payload'])->toBe('/savings/account/42')
        ->and($items['savings_rate_below_market']['action']['destination'])->toBe(['screen' => 'savings_account_detail', 'params' => ['account_id' => 42], 'fallback' => 'savings']);

    expect($items['retirement_high_pension_total_fees']['action']['payload'])->toBe('/retirement/pension/dc/7')
        ->and($items['retirement_high_pension_total_fees']['action']['destination']['screen'])->toBe('pension_detail')
        ->and($items['retirement_high_pension_total_fees']['action']['destination']['params'])->toBe(['pension_id' => 7, 'pension_type' => 'dc']);

    expect($items['tax_bed_and_isa']['action']['payload'])->toBe('/investment')
        ->and($items['tax_bed_and_isa']['action']['destination']['screen'])->toBe('investment')
        ->and($items['tax_salary_sacrifice_ni']['action']['payload'])->toBe('/retirement')
        ->and($items['investment_rebalance_portfolio']['action']['payload'])->toBe('/investment')
        ->and($items['investment_rebalance_portfolio']['action']['destination']['screen'])->toBe('investment');
});

it('serves the capture prompt on unlock cards so no client carries its own copy', function () {
    $user = User::factory()->create(['is_preview_user' => false, 'onboarding_completed' => true]);

    $aggregator = Mockery::mock(RecommendationsAggregatorService::class);
    $aggregator->shouldReceive('aggregateRecommendations')->with($user->id)->andReturn([]);
    app()->instance(RecommendationsAggregatorService::class, $aggregator);

    $unlocks = collect(app(NextActionsService::class)->buildAll($user->id))->where('type', 'unlock');

    expect($unlocks)->not->toBeEmpty();
    foreach ($unlocks as $unlock) {
        expect($unlock['action']['kind'])->toBe('fyn_capture')
            ->and($unlock['action']['prompt'])->toBeString()->not->toBe('');
    }
    expect($unlocks->firstWhere('module', 'savings')['action']['prompt'] ?? 'Help me add my savings details')
        ->toBe('Help me add my savings details');
});

it('deep-links a tax recommendation to the tax strategy screen', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    // The aggregator's seventh module — a tax rec must navigate to
    // /tax-strategy, not the /net-worth fallback.
    $aggregator = Mockery::mock(RecommendationsAggregatorService::class);
    $aggregator->shouldReceive('aggregateRecommendations')
        ->with($user->id)
        ->andReturn([[
            'recommendation_id' => 'tax_test_rec_1',
            'module' => 'tax',
            'recommendation_text' => 'Use salary sacrifice for your pension contributions',
            'priority_score' => 80.0,
            'category' => 'tax',
            'potential_benefit' => null,
        ]]);
    app()->instance(RecommendationsAggregatorService::class, $aggregator);

    $items = app(NextActionsService::class)->build($user->id);

    $item = collect($items)->firstWhere('id', 'tax_test_rec_1');
    expect($item)->not->toBeNull()
        ->and($item['action']['kind'])->toBe('navigate')
        ->and($item['action']['payload'])->toBe('/tax-strategy');
});

it('builds focus-area cards: a Top card first, then one per module', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    $areas = app(NextActionsService::class)->focusAreas($user->id);

    // First card is always "Top actions".
    expect($areas[0]['key'])->toBe('top')
        ->and($areas[0]['label'])->toBe('Top actions')
        ->and($areas[0]['locked'])->toBeFalse();

    // One card per module, in the canonical order.
    $keys = array_column($areas, 'key');
    foreach (['retirement', 'protection', 'savings', 'investment', 'estate', 'goals'] as $module) {
        expect($keys)->toContain($module);
    }

    // A fresh user's modules are KYC-gated → those cards are locked with a single
    // unlock action.
    $estate = collect($areas)->firstWhere('key', 'estate');
    expect($estate['locked'])->toBeTrue()
        ->and($estate['actions'])->toHaveCount(1)
        ->and($estate['actions'][0]['type'])->toBe('unlock');
});

afterEach(function () {
    Mockery::close();
});

it('suppresses unlock items while the user is mid-onboarding — the walk is the action', function () {
    // CSJ 2026-07-23 (live): a brand-new mid-walk user saw four "Unlock X
    // advice — date of birth is required" actions on the dashboard while Fyn
    // was about to ask for exactly that data in the walk. While the director
    // owns the next turn (onboarding_fyn_step non-null), unlock prompts are
    // noise that competes with onboarding — the dashboard's "finish your
    // plan with Fyn" nudge is the one call to action.
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'onboarding_fyn_step' => 'base_work',
    ]);

    $service = app(NextActionsService::class);

    expect(collect($service->build($user->id))->where('type', 'unlock'))->toBeEmpty();

    // The unified Top card carries no unlock prompts mid-walk; the
    // per-module tab cards keep their true gate state (the level map).
    $areas = collect($service->focusAreas($user->id));
    $top = $areas->firstWhere('key', 'top');
    expect(collect($top['actions'])->where('type', 'unlock'))->toBeEmpty();
    expect($areas->where('locked', true))->not->toBeEmpty();
});

it('shows unlock items again once the walk is over (step nulled)', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => true,
        'onboarding_fyn_step' => null,
    ]);

    $unlocks = collect(app(NextActionsService::class)->build($user->id))->where('type', 'unlock');
    expect($unlocks)->not->toBeEmpty();
});

it('suppresses unlock items for a fresh campaign registrant before the first turn stamps the step', function () {
    // 2026-07-23 live (user 292, round 2): the first dashboard fetch races
    // the chat turn that stamps onboarding_fyn_step, so the suppressed list
    // was cached with four unlock prompts at the exact moment the campaign
    // walk was starting. A funnel registrant who has not started the walk
    // (onboarding_started_at null) is walking by construction.
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'onboarding_fyn_step' => null,
        'onboarding_started_at' => null,
        'funnel_answers' => ['campaign' => 'savetax', 'employment_status' => 'full_time'],
    ]);

    expect(collect(app(NextActionsService::class)->build($user->id))->where('type', 'unlock'))->toBeEmpty();
});

it('keeps unlock items for a paused walker whose step was nulled', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'onboarding_fyn_step' => null,
        'onboarding_started_at' => now()->subHour(),
        'funnel_answers' => ['campaign' => 'savetax'],
    ]);

    $unlocks = collect(app(NextActionsService::class)->build($user->id))->where('type', 'unlock');
    expect($unlocks)->not->toBeEmpty();
});
