<?php

declare(strict_types=1);

use App\Models\SavingsAccount;
use App\Models\SpousePermission;
use App\Models\User;
use App\Services\Estate\IHTCalculationService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * W-0340 — one household must not produce two estates depending on which screen it is
 * read from.
 *
 * The item was filed because the projection pooled on `$dataSharingEnabled && $spouse`
 * while the headline used `HouseholdPooling::poolsSpouse()`, which ALSO requires the
 * couple to be married or civil partners. **It has since been fixed** — every
 * calculating branch now asks `poolsSpouse()` — and the item was never updated, so it
 * sat `blocked` on a decision that no longer needed making. This locks the behaviour so
 * it cannot quietly become untrue again.
 *
 * Not a product preference: cohabitants get no spouse exemption (IHTA 1984 s18), no
 * transferable nil rate band (s8A) and no transferable residence nil rate band (s8G), so
 * an estate that pools them is wrong in law on whichever screen it appears.
 *
 * Zero such households exist on the development database, which is why this needs a test
 * rather than a screenshot.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

/**
 * A reciprocally linked, consenting couple of the given marital status.
 *
 * DELIBERATELY UNEQUAL estates, neither zero: with two empty accounts every figure is 0
 * and "pooled" and "not pooled" are the same number, so the suite could not fail
 * (tests/CLAUDE.md §4, Collision).
 */
function w0340LinkedCouple(string $status): array
{
    $a = User::factory()->create(['marital_status' => $status]);
    $b = User::factory()->create(['marital_status' => $status]);

    $a->update(['spouse_id' => $b->id]);
    $b->update(['spouse_id' => $a->id]);

    SpousePermission::create([
        'user_id' => $a->id,
        'spouse_id' => $b->id,
        'status' => 'accepted',
        'requested_at' => now(),
        'responded_at' => now(),
    ]);

    SavingsAccount::factory()->create([
        'user_id' => $a->id, 'current_balance' => 300_000, 'ownership_type' => 'individual',
    ]);
    SavingsAccount::factory()->create([
        'user_id' => $b->id, 'current_balance' => 120_000, 'ownership_type' => 'individual',
    ]);

    return [$a->fresh(), $b->fresh()];
}

function w0340IhtFor(User $user): array
{
    return app(IHTCalculationService::class)
        ->calculate($user, $user->liveSpouse(), $user->sharesFinancialDataWithSpouse());
}

it('does not pool a linked, consenting, UNMARRIED couple', function () {
    [$user] = w0340LinkedCouple('single');

    // Consent and a returned link are both present. Marriage is not, and that is what
    // decides whether the two estates are one estate.
    expect($user->sharesFinancialDataWithSpouse())->toBeTrue();

    $iht = w0340IhtFor($user);

    expect((float) $iht['total_net_estate'])->toBe(300_000.0)
        ->and((float) $iht['user_net_estate'])->toBe(300_000.0);
});

it('does pool a married couple, so the guard is marriage and not the link', function () {
    [$user] = w0340LinkedCouple('married');

    $iht = w0340IhtFor($user);

    // 420,000 is distinct from either person's own estate, so this expectation can tell
    // the two behaviours apart rather than passing on a coincidence.
    expect((float) $iht['total_net_estate'])->toBe(420_000.0)
        ->and((float) $iht['user_net_estate'])->toBe(300_000.0);
});

it('does not pool a civil partnership differently from a marriage', function () {
    // W-0474: a civil partnership is treated identically for Inheritance Tax, via Civil
    // Partnership Act 2004 s.246.
    [$user] = w0340LinkedCouple('civil_partnership');

    expect((float) w0340IhtFor($user)['total_net_estate'])->toBe(420_000.0);
});
