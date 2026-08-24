<?php

declare(strict_types=1);

use App\Models\Estate\Liability;
use App\Models\Mortgage;
use App\Models\Property;
use App\Models\ProtectionProfile;
use App\Models\User;
use App\Services\Protection\CoverageGapAnalyzer;
use App\Services\Shared\CrossModuleAssetAggregator;
use App\Services\UserProfile\UserProfileService;

/**
 * W-0187 — protection charged one person the whole household's debt, and a third
 * party's with it. "Mortgage Debt £365,000" was every mortgage at 100%: his
 * wife's halves, and the share of a tenants-in-common loan belonging to a
 * co-owner who has no account here at all. His own exposure was £182,500.
 *
 * A protection need is personal. These tests hold the debt side to the same
 * ownership calculation the property cards already use, on both accounts, and
 * hold the third party's share to nobody.
 */
beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->spouse = User::factory()->create(['spouse_id' => $this->owner->id]);
    $this->owner->update(['spouse_id' => $this->spouse->id]);

    $this->aggregator = app(CrossModuleAssetAggregator::class);
    $this->profileService = app(UserProfileService::class);
    $this->gapAnalyzer = app(CoverageGapAnalyzer::class);
});

/**
 * A mortgage on a property, with the borrowers named on the mortgage itself.
 */
function debtFixtureMortgage(User $owner, ?User $coOwner, string $ownershipType, float $percentage, float $balance): Mortgage
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
    ]);
}

it('charges each spouse only their half of a joint mortgage', function () {
    debtFixtureMortgage($this->owner, $this->spouse, 'joint', 50.0, 300_000);

    expect($this->aggregator->calculateLiabilityTotals($this->owner->id)['mortgages'])->toBe(150000.0)
        ->and($this->aggregator->calculateLiabilityTotals($this->spouse->id)['mortgages'])->toBe(150000.0);
});

it('charges a third party\'s share of a mortgage to nobody', function () {
    // Tenants in common, 40% to the user. The other 60% — £72,000 — belongs to a
    // co-owner with no account, so `joint_owner_id` is null.
    debtFixtureMortgage($this->owner, null, 'tenants_in_common', 40.0, 120_000);

    $ownerDebt = $this->aggregator->calculateLiabilityTotals($this->owner->id)['mortgages'];
    $spouseDebt = $this->aggregator->calculateLiabilityTotals($this->spouse->id)['mortgages'];

    expect($ownerDebt)->toBe(48000.0)
        // The linked spouse is not a party to this loan and must not inherit the
        // remainder just because she is married to someone who is.
        ->and($spouseDebt)->toBe(0.0)
        // And the £72,000 is on neither account — it is charged to nobody.
        ->and($ownerDebt + $spouseDebt)->toBeLessThan(120000.0);
});

it('gives the protection debt need the user\'s share, not the whole household plus a stranger', function () {
    debtFixtureMortgage($this->owner, $this->spouse, 'joint', 50.0, 65_000);
    debtFixtureMortgage($this->owner, $this->spouse, 'joint', 50.0, 180_000);
    debtFixtureMortgage($this->owner, null, 'tenants_in_common', 40.0, 120_000);

    $profile = ProtectionProfile::factory()->create([
        'user_id' => $this->owner->id,
        'mortgage_balance' => 0,
        'other_debts' => 0,
    ]);

    // 32,500 + 90,000 + 48,000. The whole of the three records is £365,000.
    expect($this->gapAnalyzer->calculateDebtProtectionNeed($profile))->toBe(170500.0);
});

it('splits a joint non-mortgage liability between both parties', function () {
    Liability::factory()->create([
        'user_id' => $this->owner->id,
        'joint_owner_id' => $this->spouse->id,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50.0,
        'liability_type' => 'personal_loan',
        'current_balance' => 20_000,
    ]);

    expect($this->aggregator->calculateLiabilityTotals($this->owner->id)['other'])->toBe(10000.0)
        ->and($this->aggregator->calculateLiabilityTotals($this->spouse->id)['other'])->toBe(10000.0);

    // And the profile's non-mortgage list agrees with its own total, on both sides.
    foreach ([$this->owner, $this->spouse] as $party) {
        $summary = $this->profileService->getCompleteProfile($party->fresh())['liabilities_summary'];

        expect($summary['other']['total'])->toBe(10000.0)
            ->and(collect($summary['other']['items'])->sum('amount'))->toBe(10000.0);
    }
});

it('counts a mortgage recorded as a liability with the mortgages, not twice', function () {
    Liability::factory()->create([
        'user_id' => $this->owner->id,
        'joint_owner_id' => $this->spouse->id,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50.0,
        'liability_type' => 'mortgage',
        'current_balance' => 100_000,
    ]);

    $totals = $this->aggregator->calculateLiabilityTotals($this->owner->id);

    expect($totals['mortgages'])->toBe(50000.0)
        ->and($totals['other'])->toBe(0.0)
        ->and($totals['total'])->toBe(50000.0);
});

it('makes the profile liability list add up to the figure printed above it', function () {
    debtFixtureMortgage($this->owner, $this->spouse, 'joint', 50.0, 65_000);
    debtFixtureMortgage($this->owner, null, 'tenants_in_common', 40.0, 120_000);

    $summary = $this->profileService->getCompleteProfile($this->owner->fresh())['liabilities_summary'];

    $itemSum = collect($summary['mortgages']['items'])->sum('outstanding_balance');

    expect($summary['mortgages']['total'])->toBe(80500.0)
        ->and($itemSum)->toBe(80500.0)
        ->and($summary['total'])->toBe(80500.0);
});

it('shows the same debt figure to protection and to the profile', function () {
    debtFixtureMortgage($this->owner, $this->spouse, 'joint', 50.0, 300_000);

    $profile = ProtectionProfile::factory()->create([
        'user_id' => $this->owner->id,
        'mortgage_balance' => 0,
        'other_debts' => 0,
    ]);

    $summary = $this->profileService->getCompleteProfile($this->owner->fresh())['liabilities_summary'];

    expect($this->gapAnalyzer->calculateDebtProtectionNeed($profile))->toBe($summary['total']);
});
