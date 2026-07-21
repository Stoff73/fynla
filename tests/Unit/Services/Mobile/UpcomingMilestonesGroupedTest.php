<?php

declare(strict_types=1);

use App\Models\Mortgage;
use App\Models\PointAward;
use App\Models\RecommendationTracking;
use App\Models\User;
use App\Models\UserMilestone;
use App\Services\Gamification\ActivityFeedService;
use App\Services\Mobile\MilestoneDetectionService;
use App\Services\TaxConfigService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * WP-5c-ii — grouped next-per-family upcoming milestones + the uncapped
 * pages (completed load-more pagination, activity-feed cursor).
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->service = app(MilestoneDetectionService::class);
});

function earnRow(User $user, string $type, ?int $ref, float $threshold): void
{
    UserMilestone::create([
        'user_id' => $user->id,
        'milestone_type' => $type,
        'reference_id' => $ref,
        'threshold' => $threshold,
        'achieved_at' => Carbon::now(),
    ]);
}

it('offers the next unearned step from every applicable family, grouped', function () {
    $user = User::factory()->create();

    $upcoming = $this->service->upcoming($user, 15520.0, 30000.0);
    $groups = collect($upcoming)->pluck('group')->unique()->values()->all();

    expect($groups)->toContain('Wealth')
        ->and($groups)->toContain('Tax year')
        ->and($groups)->toContain('Savings')
        ->and($groups)->toContain('Retirement')
        ->and($groups)->toContain('Protection & estate')
        ->and($groups)->toContain('Journey')
        ->and(collect($upcoming)->firstWhere('title', 'Net worth £25,000')['steps'])->toContain('£9,480 away')
        ->and(collect($upcoming)->firstWhere('title', 'Pension savings £50,000')['steps'])->toContain('£20,000 away')
        ->and(array_column($upcoming, 'title'))->toContain('Open your first ISA')
        ->and(array_column($upcoming, 'title'))->toContain('Put your will in place');
});

it('advances each ladder past earned thresholds and skips maxed families', function () {
    $user = User::factory()->create();
    earnRow($user, 'emergency_fund', null, 1);
    earnRow($user, 'emergency_fund', null, 3);
    earnRow($user, 'isa_first', null, 1);
    earnRow($user, 'tax_actioned', null, 1);
    earnRow($user, 'tax_actioned', null, 250);

    $titles = array_column($this->service->upcoming($user), 'title');

    expect($titles)->toContain('Emergency fund covering 6 months of spending')
        ->and($titles)->not->toContain('Open your first ISA')
        ->and($titles)->toContain('£500 a year of tax savings actioned')
        ->and($titles)->not->toContain('Action your first tax saving');
});

it('keys allowance-usage upcoming to the active tax year', function () {
    $user = User::factory()->create();
    // 50% earned for the ACTIVE year → the full-allowance step is next.
    $year = (int) substr(app(TaxConfigService::class)->getTaxYear(), 0, 4);
    earnRow($user, 'isa_used', $year, 50);

    $titles = array_column($this->service->upcoming($user), 'title');

    expect(collect($titles)->first(fn ($t) => str_starts_with($t, 'Use your full ISA allowance')))->not->toBeNull()
        ->and(collect($titles)->first(fn ($t) => str_starts_with($t, 'Use half your ISA allowance')))->toBeNull();
});

it('lists the mortgage paydown step with the amount still to pay', function () {
    $user = User::factory()->create();
    $mortgage = Mortgage::factory()->create([
        'user_id' => $user->id,
        'original_loan_amount' => 200000,
        'outstanding_balance' => 170000, // 15% paid → next is 25%
    ]);
    earnRow($user, 'mortgage_paid', $mortgage->id, 0); // unrelated ref noise guard

    $item = collect($this->service->upcoming($user))->firstWhere('title', 'Pay off 25% of your mortgage');

    expect($item)->not->toBeNull()
        ->and($item['steps'])->toContain('£20,000 more');
});

it('folds remaining module profiles into one line and drops earned ones', function () {
    $user = User::factory()->create();
    earnRow($user, 'module_profile', MilestoneDetectionService::MODULE_IDS['protection'], 1);
    earnRow($user, 'module_profile', MilestoneDetectionService::MODULE_IDS['estate'], 1);

    $item = collect($this->service->upcoming($user))->firstWhere('title', 'Complete your remaining profiles');

    expect($item['steps'])->toContain('retirement')
        ->and($item['steps'])->toContain('goals')
        ->and($item['steps'])->not->toContain('protection')
        ->and($item['steps'])->not->toContain('estate');
});

it('serves completed actions in load-more pages of 25', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    for ($i = 1; $i <= 30; $i++) {
        RecommendationTracking::create([
            'user_id' => $user->id,
            'recommendation_id' => "rec_{$i}",
            'module' => 'general',
            'recommendation_text' => "Action {$i}",
            'status' => 'completed',
            'completed_at' => now()->subMinutes(30 - $i),
        ]);
    }

    $index = $this->getJson('/api/v1/mobile/achievements')->assertOk()->json('data');
    expect(count($index['completed']))->toBe(25)
        ->and($index['completed_total'])->toBe(30);

    $page2 = $this->getJson('/api/v1/mobile/achievements/completed?page=2')->assertOk()->json('data');
    expect(count($page2['completed']))->toBe(5)
        ->and($page2['completed'][0]['title'])->toBe('Action 5'); // newest-first continues
});

it('pages the activity feed by cursor until the ledger is exhausted', function () {
    $user = User::factory()->create();
    for ($i = 1; $i <= 7; $i++) {
        // Distinct labels per event so the no-overlap check below is real.
        PointAward::create([
            'user_id' => $user->id,
            'source_type' => 'milestone',
            'dedup_key' => 'milestone:net_worth:0:'.($i * 1000),
            'points' => 30,
        ]);
    }

    $feed = app(ActivityFeedService::class);

    $first = $feed->feed($user, 5);
    expect(count($first['events']))->toBe(5)
        ->and($first['next_cursor'])->not->toBeNull();

    $second = $feed->feed($user, 5, $first['next_cursor']);
    expect(count($second['events']))->toBe(2)
        ->and($second['next_cursor'])->toBeNull();

    // No overlap between pages.
    $all = array_merge(array_column($first['events'], 'label'), array_column($second['events'], 'label'));
    expect(count(array_unique($all)))->toBe(7);
});

it('serves the cursor over the API', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    PointAward::create([
        'user_id' => $user->id,
        'source_type' => 'data',
        'dedup_key' => 'data:pension:first',
        'points' => 20,
    ]);

    $this->getJson('/api/gamification/activity')
        ->assertOk()
        ->assertJsonPath('data.0.label', 'Added your first pension')
        ->assertJsonPath('next_cursor', null);
});
