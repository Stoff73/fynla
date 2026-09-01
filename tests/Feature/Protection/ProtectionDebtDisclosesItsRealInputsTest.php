<?php

declare(strict_types=1);

use App\Models\Mortgage;
use App\Models\Property;
use App\Models\ProtectionProfile;
use App\Models\User;
use App\Services\Protection\CoverageGapAnalyzer;
use App\Services\Protection\ProtectionGapPresentationService;

/**
 * W-0227 — the debt panel disclosed inputs that did not produce the figure beside them.
 *
 * `protection_profiles.mortgage_balance` and `.other_debts` are a manual summary that
 * predates the mortgages and liabilities tables. Two things were wrong at once:
 *
 * 1. `calculateDebtProtectionNeed()` read those two columns FIRST and returned early on
 *    them, so a figure typed once outranked every mortgage record the user owns —
 *    permanently, invisibly, and with records free to change by hundreds of thousands
 *    without the need moving.
 * 2. The panel then published those same two columns AS the inputs to a need computed
 *    from the records, so a user read `mortgage_balance £0, other_debts £0` above a need
 *    of £182,500 and could reconcile it to nothing.
 *
 * These tests pin the reversal, the fallback that stops it being a regression, and the
 * disclosure. **Assertions are on the RELATIONSHIP between the disclosed inputs and the
 * need, not on literals** — a test that asserted £182,500 would have passed just as well
 * against the old code on a household whose two sources happened to agree, which is why
 * the persona did not catch this for months.
 */
beforeEach(function () {
    $this->analyzer = app(CoverageGapAnalyzer::class);
    $this->presenter = app(ProtectionGapPresentationService::class);
});

function w0227Mortgage(User $owner, float $balance): Mortgage
{
    $property = Property::factory()->create([
        'user_id' => $owner->id,
        'property_type' => 'main_residence',
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.0,
    ]);

    return Mortgage::factory()->create([
        'user_id' => $owner->id,
        'property_id' => $property->id,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.0,
        'outstanding_balance' => $balance,
    ]);
}

it('lets the records outrank a stale profile summary instead of the other way round', function () {
    $user = User::factory()->create();
    w0227Mortgage($user, 200_000);

    // The shape that caused this: a summary typed once, long ago, against records
    // that have moved since.
    $profile = ProtectionProfile::factory()->create([
        'user_id' => $user->id,
        'mortgage_balance' => 5_000,
        'other_debts' => 0,
    ]);

    $basis = $this->analyzer->debtProtectionBasis($profile);

    expect($basis['source'])->toBe('records')
        ->and($basis['total'])->toBe(200000.0)
        // The stale £5,000 must not be the answer, and must not be added to it either.
        ->and($basis['total'])->not->toBe(5000.0)
        ->and($basis['total'])->not->toBe(205000.0);
});

it('still answers from the profile summary when the user has no records at all', function () {
    // The reason the columns are kept rather than deleted: removing them outright
    // would drop this household's need to zero, which is a regression dressed as a fix.
    $user = User::factory()->create();

    $profile = ProtectionProfile::factory()->create([
        'user_id' => $user->id,
        'mortgage_balance' => 90_000,
        'other_debts' => 10_000,
    ]);

    $basis = $this->analyzer->debtProtectionBasis($profile);

    expect($basis['source'])->toBe('profile')
        ->and($basis['total'])->toBe(100000.0);
});

it('discloses inputs that add up to the need it is explaining', function () {
    $user = User::factory()->create();
    w0227Mortgage($user, 182_500);

    $profile = ProtectionProfile::factory()->create([
        'user_id' => $user->id,
        'mortgage_balance' => 0,
        'other_debts' => 0,
    ]);

    $panel = collect($this->presenter->forUser($user, $profile)['categories'])
        ->firstWhere('key', 'debt_protection');

    $inputs = $panel['inputs'];
    $disclosed = (float) $inputs['mortgage_balance'] + (float) $inputs['other_debts'];

    // THE defect, stated as arithmetic: the numbers offered as the inputs must
    // produce the number offered as the result. This failed as 0 + 0 = 182500.
    expect($disclosed)->toBe((float) $inputs['calculated_debt_need'])
        ->and($disclosed)->toBeGreaterThan(0.0);
});

it('names which of the two sources answered, so the figure can be checked', function () {
    $user = User::factory()->create();
    w0227Mortgage($user, 120_000);

    $profile = ProtectionProfile::factory()->create([
        'user_id' => $user->id,
        'mortgage_balance' => 0,
        'other_debts' => 0,
    ]);

    $panel = collect($this->presenter->forUser($user, $profile)['categories'])
        ->firstWhere('key', 'debt_protection');

    $source = collect($panel['assumptions'])->firstWhere('key', 'debt_source');

    expect($source)->not->toBeNull()
        ->and($source['value'])->toContain('records');
});
