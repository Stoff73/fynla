<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Goal;
use App\Models\Mortgage;
use App\Services\TaxConfigService;
use App\Models\UserMilestone;
use App\Services\Mobile\MilestoneDetectionService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->service = app(MilestoneDetectionService::class);
});

it('detects all net-worth thresholds at or below the current total, once', function () {
    $user = User::factory()->create();

    $new = $this->service->detectNetWorth($user, 120000);

    expect(collect($new)->pluck('threshold')->all())->toBe([10000, 25000, 50000, 100000])
        ->and(collect($new)->every(fn ($m) => $m['share_type'] === 'net_worth_milestone'))->toBeTrue();
    expect(UserMilestone::where('user_id', $user->id)->count())->toBe(4);
});

it('does not re-fire net-worth milestones already crossed', function () {
    $user = User::factory()->create();
    $this->service->detectNetWorth($user, 120000);

    // Same total again → nothing new.
    expect($this->service->detectNetWorth($user, 120000))->toBe([]);

    // Crossing the next threshold → only the new one fires.
    $new = $this->service->detectNetWorth($user, 260000);
    expect(collect($new)->pluck('threshold')->all())->toBe([250000]);
    expect(UserMilestone::where('user_id', $user->id)->count())->toBe(5);
});

it('returns nothing when below the first threshold', function () {
    $user = User::factory()->create();
    expect($this->service->detectNetWorth($user, 5000))->toBe([]);
    expect(UserMilestone::where('user_id', $user->id)->count())->toBe(0);
});

it('detects goal-progress milestones and labels the 100% one as reached', function () {
    $user = User::factory()->create();

    $new = $this->service->detectGoal($user, 42, 100.0, 'House Deposit');

    expect(collect($new)->pluck('threshold')->all())->toBe([25, 50, 75, 100])
        ->and(end($new)['label'])->toContain('reached your goal')
        ->and($new[0]['share_type'])->toBe('goal_milestone');

    // Idempotent.
    expect($this->service->detectGoal($user, 42, 100.0, 'House Deposit'))->toBe([]);
});

it('keeps goal milestones independent per goal', function () {
    $user = User::factory()->create();
    $this->service->detectGoal($user, 1, 60.0, 'Goal A');   // 25, 50
    $new = $this->service->detectGoal($user, 2, 60.0, 'Goal B'); // 25, 50 (different goal)

    expect(collect($new)->pluck('threshold')->all())->toBe([25, 50]);
    expect(UserMilestone::where('user_id', $user->id)->where('milestone_type', 'goal')->count())->toBe(4);
});

it('presents verified milestone progress and semantic next actions without financial destination parameters', function () {
    $user = User::factory()->create();

    $withAggregates = collect($this->service->upcoming($user, 5000.0, 1000.0));
    $netWorth = $withAggregates->firstWhere('key', 'net_worth:0:10000');
    $retirement = $withAggregates->firstWhere('key', 'retirement_on_track:0:1');

    expect($netWorth)->toMatchArray([
        'state' => 'in_progress',
        'progress' => [
            'current' => 5000.0,
            'target' => 10000.0,
            'percent' => 50.0,
            'label' => '£5,000 of £10,000',
        ],
        'next_action' => [
            'label' => 'Review your net worth',
            'destination' => [
                'screen' => 'net_worth',
                'params' => [],
                'fallback' => 'dashboard',
            ],
        ],
        'route' => 'm-net-worth',
    ])
        ->and($retirement)->toMatchArray([
            'state' => 'inapplicable',
            'progress' => null,
        ]);

    $withoutAggregates = collect($this->service->upcoming($user));
    $lockedNetWorth = $withoutAggregates->firstWhere('key', 'net_worth:0:10000');

    expect($lockedNetWorth['state'])->toBe('locked')
        ->and($lockedNetWorth['progress'])->toBeNull()
        ->and($lockedNetWorth['next_action']['destination']['params'])->toBe([]);
});

it('preserves negative canonical progress while clamping only its percentage', function () {
    $user = User::factory()->create();

    $netWorth = collect($this->service->upcoming($user, -5000.0))->firstWhere('key', 'net_worth:0:10000');

    expect($netWorth['progress'])->toMatchArray([
        'current' => -5000.0,
        'target' => 10000.0,
        'percent' => 0.0,
        'label' => '£-5,000 of £10,000',
    ])
        ->and($netWorth['steps'])->toContain('£15,000 away');
});

it('gives same-named goals and mortgages unique stable event keys', function () {
    $user = User::factory()->create();
    $firstGoal = Goal::factory()->create([
        'user_id' => $user->id,
        'goal_name' => 'Home',
        'target_amount' => 10000,
        'current_amount' => 1000,
    ]);
    $secondGoal = Goal::factory()->create([
        'user_id' => $user->id,
        'goal_name' => 'Home',
        'target_amount' => 10000,
        'current_amount' => 1000,
    ]);
    $firstMortgage = Mortgage::factory()->create([
        'user_id' => $user->id,
        'original_loan_amount' => 200000,
        'outstanding_balance' => 180000,
    ]);
    $secondMortgage = Mortgage::factory()->create([
        'user_id' => $user->id,
        'original_loan_amount' => 200000,
        'outstanding_balance' => 180000,
    ]);

    $first = collect($this->service->upcoming($user));
    $firstGoalKey = $first->firstWhere('key', 'goal:'.$firstGoal->id.':25')['key'];
    $secondGoalKey = $first->firstWhere('key', 'goal:'.$secondGoal->id.':25')['key'];
    $firstMortgageKey = $first->firstWhere('key', 'mortgage_paid:'.$firstMortgage->id.':25')['key'];
    $secondMortgageKey = $first->firstWhere('key', 'mortgage_paid:'.$secondMortgage->id.':25')['key'];

    $firstGoal->update(['goal_name' => 'Renamed home']);
    $renamed = collect($this->service->upcoming($user));

    expect([$firstGoalKey, $secondGoalKey, $firstMortgageKey, $secondMortgageKey])
        ->toHaveCount(4)
        ->and(array_unique([$firstGoalKey, $secondGoalKey, $firstMortgageKey, $secondMortgageKey]))->toHaveCount(4)
        ->and($renamed->firstWhere('key', $firstGoalKey))->not->toBeNull();
});

it('bounds high-cardinality upcoming goal and mortgage candidates', function () {
    $user = User::factory()->create();
    Goal::factory()->count(8)->create([
        'user_id' => $user->id,
        'target_amount' => 10000,
        'current_amount' => 1000,
    ]);
    Mortgage::factory()->count(8)->create([
        'user_id' => $user->id,
        'original_loan_amount' => 200000,
        'outstanding_balance' => 180000,
    ]);

    $upcoming = collect($this->service->upcoming($user));

    expect($upcoming->where('group', 'Goals'))->toHaveCount(3)
        ->and($upcoming->where('group', 'Property'))->toHaveCount(3);
});

it('uses database limits for high-cardinality upcoming candidates', function () {
    $user = User::factory()->create();
    Goal::factory()->count(8)->create([
        'user_id' => $user->id,
        'target_amount' => 10000,
        'current_amount' => 1000,
    ]);
    Mortgage::factory()->count(8)->create([
        'user_id' => $user->id,
        'original_loan_amount' => 200000,
        'outstanding_balance' => 180000,
    ]);
    $candidateQueries = [];
    DB::listen(function ($query) use (&$candidateQueries): void {
        if (str_contains($query->sql, 'from `goals`') || str_contains($query->sql, 'from `mortgages`') || str_contains($query->sql, 'from `user_milestones`')) {
            $candidateQueries[] = $query->sql;
        }
    });

    $this->service->upcoming($user);

    expect($candidateQueries)->toHaveCount(4)
        ->and(array_values(array_filter($candidateQueries, fn (string $sql): bool => str_contains($sql, 'from `goals`') || str_contains($sql, 'from `mortgages`'))))->each->toContain('limit 3')
        ->and(collect($candidateQueries)->first(fn (string $sql): bool => str_starts_with($sql, 'select exists(') && str_contains($sql, 'from `user_milestones`')))->toStartWith('select exists(')
        ->and(collect($candidateQueries)->first(fn (string $sql): bool => str_contains($sql, 'from `user_milestones`') && str_contains($sql, 'limit 80')))->toContain('limit 80');
});

it('keeps exact current and selected earned identities after irrelevant historic allowance rows', function () {
    $user = User::factory()->create();
    $year = (int) substr(app(TaxConfigService::class)->getTaxYear(), 0, 4);
    foreach (range(1, 130) as $offset) {
        UserMilestone::create([
            'user_id' => $user->id,
            'milestone_type' => 'isa_used',
            'reference_id' => 1800 + $offset,
            'threshold' => 50,
            'achieved_at' => now()->subYears(2),
        ]);
    }
    $goal = Goal::factory()->create(['user_id' => $user->id, 'target_amount' => 10000, 'current_amount' => 1000]);
    $mortgage = Mortgage::factory()->create(['user_id' => $user->id, 'original_loan_amount' => 200000, 'outstanding_balance' => 180000]);
    foreach ([
        ['isa_used', $year, 50],
        ['will_in_place', null, 1],
        ['goal', $goal->id, 25],
        ['mortgage_paid', $mortgage->id, 25],
    ] as [$type, $referenceId, $threshold]) {
        UserMilestone::create([
            'user_id' => $user->id,
            'milestone_type' => $type,
            'reference_id' => $referenceId,
            'threshold' => $threshold,
            'achieved_at' => now(),
        ]);
    }

    $keys = collect($this->service->upcoming($user))->pluck('key');

    expect($keys)->not->toContain("isa_used:{$year}:50")
        ->and($keys)->not->toContain('will_in_place:0:1')
        ->and($keys)->not->toContain("goal:{$goal->id}:25")
        ->and($keys)->not->toContain("mortgage_paid:{$mortgage->id}:25");
});

it('treats any recorded tax saving as the earned first-saving identity', function () {
    $user = User::factory()->create(['funnel_answers' => ['assets' => []]]);

    $this->service->detectTaxSavingsIdentified($user, 500);

    expect(collect($this->service->upcoming($user))->pluck('key'))
        ->not->toContain('tax_savings:0:1');
});

it('does not let non-catalogue thresholds crowd valid selected goal and mortgage identities out of the bounded query', function () {
    $user = User::factory()->create();
    $goal = Goal::factory()->create(['user_id' => $user->id, 'target_amount' => 10000, 'current_amount' => 1000]);
    $mortgage = Mortgage::factory()->create(['user_id' => $user->id, 'original_loan_amount' => 200000, 'outstanding_balance' => 180000]);
    foreach (range(1000, 1079) as $irrelevantThreshold) {
        UserMilestone::create([
            'user_id' => $user->id,
            'milestone_type' => 'goal',
            'reference_id' => $goal->id,
            'threshold' => $irrelevantThreshold,
            'achieved_at' => now(),
        ]);
    }
    foreach ([['goal', $goal->id], ['mortgage_paid', $mortgage->id]] as [$type, $referenceId]) {
        UserMilestone::create([
            'user_id' => $user->id,
            'milestone_type' => $type,
            'reference_id' => $referenceId,
            'threshold' => 25,
            'achieved_at' => now(),
        ]);
    }

    $keys = collect($this->service->upcoming($user))->pluck('key');

    expect($keys)->not->toContain("goal:{$goal->id}:25")
        ->and($keys)->not->toContain("mortgage_paid:{$mortgage->id}:25");
});

it('selects eligible mortgages before applying the bounded candidate limit', function () {
    $user = User::factory()->create();
    Mortgage::factory()->count(3)->create([
        'user_id' => $user->id,
        'original_loan_amount' => 0,
        'outstanding_balance' => 0,
    ]);
    $valid = Mortgage::factory()->create([
        'user_id' => $user->id,
        'original_loan_amount' => 200000,
        'outstanding_balance' => 180000,
    ]);

    $property = collect($this->service->upcoming($user))->firstWhere('key', "mortgage_paid:{$valid->id}:25");

    expect($property)->not->toBeNull();
});
