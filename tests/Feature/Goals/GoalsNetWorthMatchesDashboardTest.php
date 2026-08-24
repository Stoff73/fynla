<?php

declare(strict_types=1);

use App\Models\Estate\Liability;
use App\Models\Mortgage;
use App\Models\Property;
use App\Models\User;
use App\Services\Goals\GoalsProjectionService;
use App\Services\NetWorth\NetWorthService;
use Illuminate\Support\Facades\Cache;

/**
 * W-0206 — /goals reported a net worth that was wrong on both accounts of one
 * household, in opposite directions, from a single cause.
 *
 * `GoalsProjectionService::getMortgageParameters()` was a fourth implementation
 * of "what does this user owe on mortgages", and it got both halves of the
 * question wrong at once:
 *
 *   Reach    — it read `forUserPrimaryOnly`, so it saw only mortgages the user
 *              is the borrower on. A spouse who is the joint owner of every
 *              household mortgage and the borrower on none got an empty set, a
 *              zero balance, and a projection that subtracted her liabilities
 *              exactly zero times: £861,780 where the dashboard said £739,280.
 *   Fraction — it summed `outstanding_balance` at face value, so the borrower
 *              was charged the whole household debt including the share of a
 *              co-owner with no account here: £1,295,000 where the dashboard
 *              said £1,477,500, the difference being £365,000 of household
 *              mortgages charged to one man instead of his own £182,500.
 *
 * Both halves now come from the homes F-0019 established — the reach from
 * `CrossModuleAssetAggregator::getMortgages()`, the fraction from
 * `CalculatesOwnershipShare::calculateUserMortgageShare()`.
 *
 * These tests pin the harm rather than the arithmetic: one household's net
 * worth is one figure wherever it is shown, and a third party's debt is charged
 * to nobody. Written against a fixture that deliberately holds what the
 * peak_earners persona does not — a non-mortgage liability, and a mortgage
 * co-owned with someone who is not a user (tests/CLAUDE.md §4).
 */
beforeEach(function () {
    Cache::flush();

    $this->borrower = User::factory()->create(['date_of_birth' => '1976-11-08']);
    $this->jointOwner = User::factory()->create([
        'date_of_birth' => '1978-04-22',
        'spouse_id' => $this->borrower->id,
    ]);
    $this->borrower->update(['spouse_id' => $this->jointOwner->id]);

    $this->projection = app(GoalsProjectionService::class);
    $this->netWorth = app(NetWorthService::class);
});

/**
 * A mortgage on a property, with the ownership recorded on both records.
 *
 * A null co-owner with a percentage below 100 is the third-party case: the
 * remainder belongs to somebody who has no account in the application.
 */
function projectionFixtureMortgage(User $owner, ?User $coOwner, string $ownershipType, float $percentage, float $balance): Mortgage
{
    $property = Property::factory()->create([
        'user_id' => $owner->id,
        'joint_owner_id' => $coOwner?->id,
        'property_type' => 'main_residence',
        'ownership_type' => $ownershipType,
        'ownership_percentage' => $percentage,
    ]);

    return Mortgage::factory()->create([
        'user_id' => $owner->id,
        'joint_owner_id' => $coOwner?->id,
        'property_id' => $property->id,
        'ownership_type' => $ownershipType,
        'ownership_percentage' => $percentage,
        'outstanding_balance' => $balance,
        'interest_rate' => 4.29,
        'remaining_term_months' => 300,
        'mortgage_type' => 'repayment',
    ]);
}

function goalsStartingNetWorth(GoalsProjectionService $service, User $user): float
{
    Cache::flush();

    return (float) $service->generateProjection($user->id)['summary']['starting_net_worth'];
}

it('shows the borrower the same net worth on goals as on the dashboard', function () {
    projectionFixtureMortgage($this->borrower, $this->jointOwner, 'joint', 50.0, 65_000);
    projectionFixtureMortgage($this->borrower, $this->jointOwner, 'joint', 50.0, 180_000);

    $dashboard = round((float) $this->netWorth->calculateNetWorth($this->borrower)['net_worth']);

    expect(goalsStartingNetWorth($this->projection, $this->borrower))->toBe($dashboard);
});

it('shows the joint owner the same net worth on goals as on the dashboard, though she is the borrower on nothing', function () {
    projectionFixtureMortgage($this->borrower, $this->jointOwner, 'joint', 50.0, 65_000);
    projectionFixtureMortgage($this->borrower, $this->jointOwner, 'joint', 50.0, 180_000);

    // The reach failure: every one of these mortgages names her as joint owner
    // and none of them names her as borrower, so the old code saw no debt at all.
    $dashboardDebt = (float) $this->netWorth->calculateNetWorth($this->jointOwner)['total_liabilities'];
    expect($dashboardDebt)->toBe(122_500.0);

    $dashboard = round((float) $this->netWorth->calculateNetWorth($this->jointOwner)['net_worth']);

    expect(goalsStartingNetWorth($this->projection, $this->jointOwner))->toBe($dashboard);
});

it('charges a third party\'s share of a mortgage to neither spouse', function () {
    // Tenants in common, 40% to the borrower. The other 60% — £72,000 — belongs
    // to a co-owner with no account, so `joint_owner_id` is null.
    projectionFixtureMortgage($this->borrower, null, 'tenants_in_common', 40.0, 120_000);

    $projection = $this->projection->generateProjection($this->borrower->id);
    $borrowerDebt = (float) $projection['yearly_data'][0]['liabilities']['mortgage'];

    Cache::flush();
    $spouseProjection = $this->projection->generateProjection($this->jointOwner->id);
    $spouseDebt = (float) $spouseProjection['yearly_data'][0]['liabilities']['mortgage'];

    expect($borrowerDebt)->toBe(48_000.0)
        // The linked spouse is not a party to this loan and must not inherit the
        // remainder merely for being married to somebody who is.
        ->and($spouseDebt)->toBe(0.0)
        // And the £72,000 lands on neither account: charged to nobody.
        ->and($borrowerDebt + $spouseDebt)->toBeLessThan(120_000.0);
});

it('subtracts a non-mortgage liability, and still agrees with the dashboard when it does', function () {
    // The branch the persona cannot reach: peak_earners holds zero `liabilities`
    // rows, so a suite built from it never enters the non-mortgage path at all.
    projectionFixtureMortgage($this->borrower, $this->jointOwner, 'joint', 50.0, 180_000);

    $withoutLiability = goalsStartingNetWorth($this->projection, $this->borrower);

    Liability::factory()->create([
        'user_id' => $this->borrower->id,
        'liability_type' => 'credit_card',
        'current_balance' => 12_000,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ]);

    Cache::flush();
    $dashboard = round((float) $this->netWorth->calculateNetWorth($this->borrower)['net_worth']);
    $withLiability = goalsStartingNetWorth($this->projection, $this->borrower);

    expect($withLiability)->toBe($dashboard)
        // The answer has to MOVE when the input moves, or the agreement above
        // proves nothing but that both sides ignore the same row.
        ->and($withLiability)->toBe($withoutLiability - 12_000.0);
});

it('agrees with the dashboard on both accounts of a household holding a jointly owned non-mortgage liability beside a third party mortgage', function () {
    // The shape the tester is about to enter on the persona next cycle: a hire
    // purchase on the jointly owned car, alongside the tenants-in-common
    // mortgage. Both halves of the liability side are present at once, which is
    // the combination neither the persona nor the earlier fixtures could reach.
    projectionFixtureMortgage($this->borrower, $this->jointOwner, 'joint', 50.0, 180_000);
    projectionFixtureMortgage($this->borrower, null, 'tenants_in_common', 40.0, 120_000);

    Liability::factory()->create([
        'user_id' => $this->borrower->id,
        'joint_owner_id' => $this->jointOwner->id,
        'liability_type' => 'hire_purchase',
        'liability_name' => 'Car finance',
        'current_balance' => 24_000,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
    ]);

    // Deliberately NOT asserting how the £24,000 divides. That split is
    // NetWorthService::calculateLiabilitiesBreakdown()'s to decide and it is
    // W-0226's to correct — pinning today's answer here would bake W-0226's bug
    // into a goals test and turn its eventual fix red. What is in scope, and
    // what must hold whatever W-0226 decides, is that the two surfaces never
    // disagree about it.
    foreach ([$this->borrower, $this->jointOwner] as $user) {
        $dashboard = round((float) $this->netWorth->calculateNetWorth($user)['net_worth']);

        expect(goalsStartingNetWorth($this->projection, $user))->toBe($dashboard);
    }
});

it('carries a non-mortgage liability into the goals figure rather than dropping it on the mortgage path', function () {
    // W-0206's evidence was a SUM over the mortgages table, so a fix that
    // corrected mortgages and never looked at anything else would produce the
    // right number on a household that holds no other liabilities — which is
    // every household this persona can produce.
    projectionFixtureMortgage($this->borrower, $this->jointOwner, 'joint', 50.0, 180_000);

    $before = goalsStartingNetWorth($this->projection, $this->borrower);

    Liability::factory()->create([
        'user_id' => $this->borrower->id,
        'liability_type' => 'hire_purchase',
        'liability_name' => 'Car finance',
        'current_balance' => 24_000,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ]);

    expect(goalsStartingNetWorth($this->projection, $this->borrower))->toBe($before - 24_000.0);
});

it('keeps agreeing with the dashboard when a mortgage is recorded as a liability row rather than a mortgage row', function () {
    // A tripwire, not a statement that the current answer is right.
    //
    // Today both surfaces value a mortgage-typed `liabilities` row at ZERO:
    // NetWorthService::calculateLiabilitiesBreakdown() skips `case 'mortgage'`
    // on the stated grounds that property mortgages come from the mortgages
    // table, and the goals projection reaches the mortgages table directly. They
    // agree by both omitting it. (CrossModuleAssetAggregator disagrees with both
    // and counts the user's share of it — raised separately.)
    //
    // The hazard is the fix, not the bug. The goals projection derives its
    // mortgage figure from getMortgages() and never reads
    // liabilities_breakdown['mortgages'], so the day anybody teaches
    // NetWorthService to count these rows, the dashboard will move and /goals
    // will not — which is W-0206 again, reintroduced by a fix to something else.
    // This test goes red at that moment instead of a persona run finding it.
    projectionFixtureMortgage($this->borrower, $this->jointOwner, 'joint', 50.0, 180_000);

    Liability::factory()->create([
        'user_id' => $this->borrower->id,
        'joint_owner_id' => $this->jointOwner->id,
        'liability_type' => 'mortgage',
        'liability_name' => 'Second charge',
        'current_balance' => 50_000,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
    ]);

    foreach ([$this->borrower, $this->jointOwner] as $user) {
        $dashboard = round((float) $this->netWorth->calculateNetWorth($user)['net_worth']);

        expect(goalsStartingNetWorth($this->projection, $user))->toBe($dashboard);
    }
});

it('moves the goals figure and the dashboard figure together when a balance changes', function () {
    $mortgage = projectionFixtureMortgage($this->borrower, $this->jointOwner, 'joint', 50.0, 200_000);

    $before = goalsStartingNetWorth($this->projection, $this->borrower);

    $mortgage->update(['outstanding_balance' => 100_000]);

    Cache::flush();
    $after = goalsStartingNetWorth($this->projection, $this->borrower);
    $dashboard = round((float) $this->netWorth->calculateNetWorth($this->borrower)['net_worth']);

    // Half the balance was repaid, and half of that half is his.
    expect($after - $before)->toBe(50_000.0)
        ->and($after)->toBe($dashboard);
});
