<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\FamilyMember;
use App\Models\User;
use App\Services\LifeStage\LifeStageService;
use Database\Seeders\TaxConfigurationSeeder;

/**
 * W-0242 — LifeStageService threw a SQL error on a column that does not exist.
 *
 * `hasPensionValueAbove()` summed `db_pensions.transfer_value` through the QUERY
 * BUILDER. There is no such column, so it reached MySQL as
 * `select sum(transfer_value) …` and raised
 * `SQLSTATE[42S22]: Unknown column 'transfer_value' in 'field list'` — an
 * unguarded 500, not a wrong number. The identical mistake in
 * `MobileDashboardAggregator` reads over a Collection and silently returns zero,
 * which is why one copy was invisible and this one was fatal.
 *
 * TEST DESIGN — `tests/CLAUDE.md` §4, the fixture variant. The throwing line sits
 * behind a `||` that short-circuits, so it is only REACHED by a user who is
 * mid_career, over 48, and does NOT have all children aged 18 or over. A fixture
 * with no children never enters the branch — `every()` over an empty set is true —
 * and a test built on one would pass without touching the bug. Every case below
 * states the child's age explicitly, because the child's age is the fixture
 * property that decides whether the code under test runs at all.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->service = app(LifeStageService::class);
});

it('does not throw for a mid-career user over 48 with a child under 18', function () {
    // The exact reachable path: the children check must FAIL for the pension check
    // to be evaluated at all.
    $user = User::factory()->create([
        'life_stage' => 'mid_career',
        'date_of_birth' => now()->subYears(49)->toDateString(),
    ]);

    FamilyMember::factory()->create([
        'user_id' => $user->id,
        'relationship' => 'child',
        'date_of_birth' => now()->subYears(12)->toDateString(),
    ]);

    expect(fn () => $this->service->suggestTransition($user))->not->toThrow(Throwable::class);
});

it('suggests the peak stage for a large defined contribution pot with a minor child', function () {
    // Proves the branch is not merely reached but read: the same fixture, plus a
    // pot over the £200,000 threshold, returns the transition. Asserting only "it
    // did not throw" would pass if the whole check were deleted.
    $user = User::factory()->create([
        'life_stage' => 'mid_career',
        'date_of_birth' => now()->subYears(49)->toDateString(),
    ]);

    FamilyMember::factory()->create([
        'user_id' => $user->id,
        'relationship' => 'child',
        'date_of_birth' => now()->subYears(12)->toDateString(),
    ]);

    DCPension::factory()->create([
        'user_id' => $user->id,
        'current_fund_value' => 350000.00,
    ]);

    expect($this->service->suggestTransition($user))->toBe('peak');
});

it('does not suggest the peak stage for a small pot with a minor child', function () {
    // The negative case the one above needs to mean anything: the same reachable
    // path, under the threshold, returns null.
    $user = User::factory()->create([
        'life_stage' => 'mid_career',
        'date_of_birth' => now()->subYears(49)->toDateString(),
    ]);

    FamilyMember::factory()->create([
        'user_id' => $user->id,
        'relationship' => 'child',
        'date_of_birth' => now()->subYears(12)->toDateString(),
    ]);

    DCPension::factory()->create([
        'user_id' => $user->id,
        'current_fund_value' => 40000.00,
    ]);

    expect($this->service->suggestTransition($user))->toBeNull();
});

it('still suggests the peak stage when every child is independent', function () {
    // The short-circuit case, which never reached the broken line and must keep
    // working.
    $user = User::factory()->create([
        'life_stage' => 'mid_career',
        'date_of_birth' => now()->subYears(52)->toDateString(),
    ]);

    FamilyMember::factory()->create([
        'user_id' => $user->id,
        'relationship' => 'child',
        'date_of_birth' => now()->subYears(21)->toDateString(),
    ]);

    expect($this->service->suggestTransition($user))->toBe('peak');
});
