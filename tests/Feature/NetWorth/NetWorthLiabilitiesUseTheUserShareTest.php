<?php

declare(strict_types=1);

use App\Models\Estate\Liability;
use App\Models\User;
use App\Services\NetWorth\NetWorthService;
use App\Services\Shared\CrossModuleAssetAggregator;

/**
 * W-0226 — the net worth liabilities breakdown charged the primary owner 100% of
 * every shared debt, and showed the co-owner none of it.
 *
 * The sixth mechanism answering "what does this user owe", and the last one still
 * answering it wrong: W-0187 fixed the protection need, W-0206 the goals projection,
 * W-0173 rental income. `liabilities` carries `ownership_type`,
 * `ownership_percentage` and `joint_owner_id` like every other shared record, and
 * neither the reach nor the fraction was used.
 *
 * **These assert the SPLIT, not a literal.** A test asserting "£25,000" would pass on
 * a 50/50 household against either implementation as long as the arithmetic happened
 * to land — the persona's symmetry is exactly what let the client-side variant of this
 * bug survive in W-0237. The third-party case is the one that cannot be faked by
 * symmetry.
 */
beforeEach(function () {
    $this->service = app(NetWorthService::class);
    $this->aggregator = app(CrossModuleAssetAggregator::class);
});

function w0226Liability(User $owner, ?User $coOwner, string $type, float $pct, float $balance, string $liabilityType = 'personal_loan'): Liability
{
    return Liability::factory()->create([
        'user_id' => $owner->id,
        'joint_owner_id' => $coOwner?->id,
        'ownership_type' => $type,
        'ownership_percentage' => $pct,
        'liability_type' => $liabilityType,
        'current_balance' => $balance,
    ]);
}

it('charges each party only their half of a joint loan, and reaches the co-owner at all', function () {
    $owner = User::factory()->create();
    $coOwner = User::factory()->create();

    w0226Liability($owner, $coOwner, 'joint', 50.0, 40_000);

    $ownerLoans = $this->service->calculateNetWorth($owner)['liabilities_breakdown']['loans'];
    $coOwnerLoans = $this->service->calculateNetWorth($coOwner)['liabilities_breakdown']['loans'];

    // Both halves of the defect: the recorder was charged the whole £40,000 and the
    // co-owner — who never appears in a `user_id` query — was charged nothing.
    expect($ownerLoans)->toBe(20000.0)
        ->and($coOwnerLoans)->toBe(20000.0)
        ->and($ownerLoans + $coOwnerLoans)->toBe(40000.0);
});

it('charges a co-owner with no account on this platform to nobody', function () {
    $owner = User::factory()->create();

    // Tenants in common, 40% to the user; `joint_owner_id` null because the other
    // 60% belongs to someone with no account here.
    w0226Liability($owner, null, 'tenants_in_common', 40.0, 100_000);

    $loans = $this->service->calculateNetWorth($owner)['liabilities_breakdown']['loans'];

    expect($loans)->toBe(40000.0)
        // The £60,000 is charged to no account at all — the same principle the
        // property and estate modules already honour.
        ->and($loans)->toBeLessThan(100000.0);
});

it('agrees with the aggregator, which is the one home for what a user owes', function () {
    $owner = User::factory()->create();
    $coOwner = User::factory()->create();

    w0226Liability($owner, $coOwner, 'joint', 50.0, 30_000, 'credit_card');
    w0226Liability($owner, null, 'tenants_in_common', 40.0, 50_000, 'personal_loan');

    $breakdown = $this->service->calculateNetWorth($owner)['liabilities_breakdown'];
    $fromNetWorth = $breakdown['loans'] + $breakdown['credit_cards'] + $breakdown['other'];

    // `calculateLiabilityTotals()['other']` is every non-mortgage liability at the
    // user's share. Two mechanisms, one question — they must not diverge (Rule 20).
    $fromAggregator = $this->aggregator->calculateLiabilityTotals($owner->id)['other'];

    expect(round($fromNetWorth, 2))->toBe(round($fromAggregator, 2));
});
