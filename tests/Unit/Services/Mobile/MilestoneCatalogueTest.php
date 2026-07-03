<?php

declare(strict_types=1);

use App\Models\Estate\LastingPowerOfAttorney;
use App\Models\Estate\Will;
use App\Models\Mortgage;
use App\Models\RecommendationTracking;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Models\UserMilestone;
use App\Services\Mobile\MilestoneDetectionService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * WP-5c — the expanded milestone catalogue. Every family mints once per
 * threshold (unique on user/type/reference/threshold), yearly families repeat
 * via reference_id = tax-year start, and detection takes figures as arguments
 * rather than recomputing aggregates.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->service = app(MilestoneDetectionService::class);
});

function milestoneCount(User $user, string $type): int
{
    return UserMilestone::where('user_id', $user->id)->where('milestone_type', $type)->count();
}

it('mints every crossed pension-pot threshold once', function () {
    $user = User::factory()->create();

    $first = $this->service->detectPensionPot($user, 60000.0);
    $again = $this->service->detectPensionPot($user, 60000.0);

    expect(array_column($first, 'threshold'))->toBe([10000, 25000, 50000])
        ->and($again)->toBe([])
        ->and(milestoneCount($user, 'pension_pot'))->toBe(3);
});

it('mints only the new pension-pot threshold as the pot grows', function () {
    $user = User::factory()->create();
    $this->service->detectPensionPot($user, 30000.0);

    $new = $this->service->detectPensionPot($user, 120000.0);

    expect(array_column($new, 'threshold'))->toBe([50000, 100000]);
});

it('mints emergency-fund months up to the runway', function () {
    $user = User::factory()->create();

    $new = $this->service->detectEmergencyFund($user, 3.4);

    expect(array_column($new, 'threshold'))->toBe([1, 3])
        ->and($new[0]['label'])->toBe('Your emergency fund covers a month of your spending.')
        ->and($new[1]['label'])->toBe('Your emergency fund covers 3 months of your spending.');
});

it('mints retirement on-track only when the target is met', function () {
    $user = User::factory()->create();

    expect($this->service->detectRetirementOnTrack($user, 20000.0, 0.0))->toBe([])
        ->and($this->service->detectRetirementOnTrack($user, 18000.0, 20000.0))->toBe([])
        ->and($this->service->detectRetirementOnTrack($user, 21000.0, 20000.0))->not->toBe([])
        ->and(milestoneCount($user, 'retirement_on_track'))->toBe(1);
});

it('mints protection adequacy only with policies and zero gaps', function () {
    $user = User::factory()->create();

    expect($this->service->detectProtectionAdequate($user, 0, 0))->toBe([])
        ->and($this->service->detectProtectionAdequate($user, 2, 1))->toBe([])
        ->and($this->service->detectProtectionAdequate($user, 2, 0))->not->toBe([]);
});

it('mints mortgage paydown per mortgage, including joint', function () {
    $user = User::factory()->create();
    $spouse = User::factory()->create();
    $own = Mortgage::factory()->create([
        'user_id' => $user->id,
        'original_loan_amount' => 200000,
        'outstanding_balance' => 90000, // 55% paid
    ]);
    Mortgage::factory()->create([
        'user_id' => $spouse->id,
        'joint_owner_id' => $user->id,
        'original_loan_amount' => 100000,
        'outstanding_balance' => 70000, // 30% paid
    ]);

    $new = $this->service->detectMortgagesPaid($user);
    $again = $this->service->detectMortgagesPaid($user);

    expect(count($new))->toBe(3) // own 25+50, joint 25
        ->and($again)->toBe([])
        ->and(UserMilestone::where('user_id', $user->id)
            ->where('milestone_type', 'mortgage_paid')
            ->where('reference_id', $own->id)
            ->pluck('threshold')->map(fn ($t) => (int) $t)->all())->toBe([25, 50]);
});

/**
 * The Will factory derives its dependent columns from an internal has_will
 * roll, so overriding has_will alone can pair it with mismatched values —
 * always pass the dependent columns explicitly.
 */
function makeWill(User $user, bool $hasWill): Will
{
    return Will::factory()->create([
        'user_id' => $user->id,
        'has_will' => $hasWill,
        'spouse_primary_beneficiary' => $hasWill,
        'spouse_bequest_percentage' => $hasWill ? 100.00 : 0.00,
        'executor_name' => $hasWill ? 'Test Executor' : '',
        'executor_notes' => null,
        'will_last_updated' => $hasWill ? now() : null,
    ]);
}

it('ignores a will profile row that says there is no will', function () {
    $user = User::factory()->create();
    makeWill($user, false);

    expect($this->service->detectEstateBasics($user))->toBe([]);
});

it('mints will and registered Lasting Power of Attorney', function () {
    $user = User::factory()->create();
    makeWill($user, true);
    LastingPowerOfAttorney::factory()->create(['user_id' => $user->id, 'status' => 'draft']);

    $first = $this->service->detectEstateBasics($user);
    expect(array_column($first, 'type'))->toBe(['will_in_place']); // draft LPA doesn't count

    LastingPowerOfAttorney::factory()->create(['user_id' => $user->id, 'status' => 'registered']);
    $second = $this->service->detectEstateBasics($user);

    expect(array_column($second, 'type'))->toBe(['lpa_in_place']);
});

it('mints the first ISA once', function () {
    $user = User::factory()->create();

    expect($this->service->detectIsaFirst($user))->toBe([]);

    SavingsAccount::factory()->create(['user_id' => $user->id, 'is_isa' => true]);

    expect($this->service->detectIsaFirst($user))->not->toBe([])
        ->and($this->service->detectIsaFirst($user))->toBe([]);
});

it('mints module profiles keyed by stable reference ids', function () {
    $user = User::factory()->create();

    $new = $this->service->detectModuleProfiles($user, ['savings', 'retirement', 'unknown_module']);

    expect(count($new))->toBe(2)
        ->and(UserMilestone::where('user_id', $user->id)
            ->where('milestone_type', 'module_profile')
            ->pluck('reference_id')->map(fn ($r) => (int) $r)->sort()->values()->all())
        ->toBe([
            MilestoneDetectionService::MODULE_IDS['retirement'],
            MilestoneDetectionService::MODULE_IDS['savings'],
        ]);
});

it('mints allowance usage per tax year and repeats the next year', function () {
    $user = User::factory()->create();
    $positions = fn (float $pct) => [
        ['key' => 'isa_allowance', 'amount' => 20000.0, 'utilisation_pct' => $pct, 'available' => true],
        ['key' => 'pension_annual_allowance', 'amount' => 60000.0, 'utilisation_pct' => 30.0, 'available' => true],
    ];

    $first = $this->service->detectTaxYearAllowances($user, '2026/27', $positions(100.0));
    expect(array_column($first, 'threshold'))->toBe([50, 100])
        ->and($first[1]['label'])->toBe("You've used all of your ISA allowance for 2026/27.")
        ->and(milestoneCount($user, 'pension_aa_used'))->toBe(0); // 30% — below 50

    // Same year again: nothing new. Next tax year: repeats.
    expect($this->service->detectTaxYearAllowances($user, '2026/27', $positions(100.0)))->toBe([]);
    $nextYear = $this->service->detectTaxYearAllowances($user, '2027/28', $positions(60.0));
    expect(array_column($nextYear, 'threshold'))->toBe([50])
        ->and(milestoneCount($user, 'isa_used'))->toBe(3);
});

it('skips unavailable or zero-amount allowance positions', function () {
    $user = User::factory()->create();

    $new = $this->service->detectTaxYearAllowances($user, '2026/27', [
        ['key' => 'isa_allowance', 'amount' => 0.0, 'utilisation_pct' => 100.0, 'available' => true],
        ['key' => 'pension_annual_allowance', 'amount' => 60000.0, 'utilisation_pct' => 100.0, 'available' => false],
    ]);

    expect($new)->toBe([]);
});

it('mints the first actioned tax saving, then cumulative thresholds', function () {
    $user = User::factory()->create();

    $first = $this->service->detectTaxActioned($user, 300.0);
    expect(array_column($first, 'threshold'))->toBe([1, 250]);

    $more = $this->service->detectTaxActioned($user, 1200.0);
    expect(array_column($more, 'threshold'))->toBe([500, 1000])
        ->and($this->service->detectTaxActioned($user, 0.0))->toBe([]);
});

it('mints a named strategy first from a completed recommendation', function () {
    $user = User::factory()->create();

    $new = $this->service->detectStrategyFirst($user, 'tax_gift_aid_higher_rate_relief');

    expect($new[0]['label'])->toBe("You've claimed your first Gift Aid relief.")
        ->and($this->service->detectStrategyFirst($user, 'tax_gift_aid_higher_rate_relief'))->toBe([])
        ->and($this->service->detectStrategyFirst($user, 'tax_unknown_thing'))->toBe([])
        ->and($this->service->detectStrategyFirst($user, 'protection_abc123'))->toBe([]);
});

it('mints strategy-first and estate-start through the observer on completion', function () {
    $user = User::factory()->create();

    RecommendationTracking::create([
        'user_id' => $user->id,
        'recommendation_id' => 'tax_salary_sacrifice_ni',
        'module' => 'tax',
        'recommendation_text' => 'Set up salary sacrifice',
        'status' => 'completed',
        'completed_at' => now(),
    ]);
    RecommendationTracking::create([
        'user_id' => $user->id,
        'recommendation_id' => 'estate_a1b2c3d4e5f6a7b8',
        'module' => 'estate',
        'recommendation_text' => 'Review your will',
        'status' => 'completed',
        'completed_at' => now(),
    ]);

    expect(milestoneCount($user, 'strategy:salary_sacrifice'))->toBe(1)
        ->and(milestoneCount($user, 'estate_plan_started'))->toBe(1);
});

it('mints anniversaries per completed year and the mutual household link', function () {
    $user = User::factory()->create(['created_at' => now()->subDays(800)]); // ~2.2 years
    $spouse = User::factory()->create(['spouse_id' => null]);
    $user->update(['spouse_id' => $spouse->id]);

    $first = $this->service->detectJourney($user);
    expect(array_column(array_filter($first, fn ($m) => $m['type'] === 'anniversary'), 'threshold'))->toBe([1, 2])
        ->and(milestoneCount($user, 'household'))->toBe(0); // one-way link — no mint

    $spouse->update(['spouse_id' => $user->id]);
    $second = $this->service->detectJourney($user);

    expect(array_column($second, 'type'))->toContain('household')
        ->and($this->service->detectJourney($user))->toBe([]);
});
