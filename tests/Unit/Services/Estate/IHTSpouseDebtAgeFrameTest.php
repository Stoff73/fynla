<?php

declare(strict_types=1);

use App\Models\FamilyMember;
use App\Models\Mortgage;
use App\Models\Property;
use App\Models\User;
use App\Services\Estate\IHTCalculationService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * W-0374 — a spouse's undated debts were amortised in the VIEWER's age frame.
 *
 * `projectSingleLiability()` falls back to "assume cleared at retirement age" when
 * a debt has no maturity date, and that fallback is `$retirementAge - $currentAge`.
 * Both were the signed-in user's, for both members, so a spouse's undated debt was
 * discharged on their partner's timetable.
 *
 * Same family as W-0188, which fixed the equivalent age-frame error for the
 * projection HORIZON. The horizon is deliberately still shared — that is
 * W-0188's settled answer and acceptance 2 here — so only the fallback moves.
 *
 * ## Why the fixture is shaped like this
 *
 * The defect is invisible on an ordinary household: every debt amortises to zero
 * well inside a 36-year horizon, so both frames give the same £0. It appears only
 * when the horizon is SHORT relative to the frame, which needs the two members'
 * frames to differ sharply. `target_retirement_age` is set explicitly on each so
 * the difference comes from the data and not from a life-expectancy table that
 * could drift.
 *
 * The viewer's frame is already spent (retirement age BEHIND their current age),
 * which is what makes the bug show as a clean disappearance: `max(0, 60 - 70)` is
 * zero, `$yearsToProject >= 0` is always true, and the spouse's debt vanished.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

/**
 * @return array{0: User, 1: User}
 */
function ageFrameHousehold(): array
{
    $viewer = User::factory()->create([
        'marital_status' => 'married',
        'date_of_birth' => today()->subYears(70)->toDateString(),
        // Already behind them, so their own fallback term is zero.
        'target_retirement_age' => 60,
    ]);
    $spouse = User::factory()->create([
        'marital_status' => 'married',
        'date_of_birth' => today()->subYears(55)->toDateString(),
        'target_retirement_age' => 80,
        'spouse_id' => $viewer->id,
    ]);
    $viewer->update(['spouse_id' => $spouse->id]);

    return [$viewer->fresh(), $spouse->fresh()];
}

function undatedMortgageFor(User $member, float $balance): Mortgage
{
    $property = Property::factory()->create([
        'user_id' => $member->id,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'property_type' => 'secondary_residence',
    ]);

    return Mortgage::factory()->create([
        'user_id' => $member->id,
        'property_id' => $property->id,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'outstanding_balance' => $balance,
        'maturity_date' => null,
    ]);
}

it('amortises a spouse undated debt in the spouse own age frame', function () {
    [$viewer, $spouse] = ageFrameHousehold();
    undatedMortgageFor($spouse, 100_000);

    $r = app(IHTCalculationService::class)->calculate($viewer, $spouse, true);

    $horizon = (int) $r['years_to_death'];
    // The spouse retires at 80 and is 55, so their undated debt is assumed cleared
    // over 25 years. Read from the response rather than assumed, so the assertion
    // does not silently pass if the life-expectancy table moves.
    $spouseTerm = 25;
    expect($horizon)->toBeLessThan($spouseTerm);

    $expected = round(100_000 * (($spouseTerm - $horizon) / $spouseTerm), 2);

    // In the viewer's frame the term is max(0, 60 - 70) = 0, so the debt was
    // discharged instantly and reported as nothing at all.
    expect((float) $r['projected_liabilities'])->toBeGreaterThan(0.0)
        ->and((float) $r['projected_liabilities'])->toEqualWithDelta($expected, 0.01);
});

it('keeps the household horizon shared and viewer-framed', function () {
    // W-0374 acceptance 2, and the trap in the fix: `projectMemberLiabilities()`
    // used to DERIVE the horizon from the same `$currentAge` it used for the
    // fallback, so handing it the spouse's age would have moved the horizon too.
    // The horizon is W-0188's settled answer and must not follow the spouse.
    [$viewer, $spouse] = ageFrameHousehold();
    $before = app(IHTCalculationService::class)->calculate($viewer, $spouse, true)['years_to_death'];

    $spouse->update([
        'date_of_birth' => today()->subYears(30)->toDateString(),
        'target_retirement_age' => 70,
    ]);

    $after = app(IHTCalculationService::class)
        ->calculate($viewer->fresh(), $spouse->fresh(), true)['years_to_death'];

    expect($after)->toBe($before);
});

it('amortises a spouse undated mortgage on the family home in the spouse own frame', function () {
    // The SECOND site, which W-0374 does not name: `projectMainResidenceNetValue()`
    // builds one closure and applies it to both members, and it used to carry the
    // viewer's ages in with it. This one feeds the projected residence band cap
    // rather than the estate total, so it is invisible in `projected_liabilities`.
    //
    // Differential rather than arithmetic: two households identical but for the
    // spouse's OWN retirement age. If that age is ignored — the defect — both
    // answer the same. The growth rate and horizon cancel between them.
    $build = function (int $spouseRetirementAge): float {
        $viewer = User::factory()->create([
            'marital_status' => 'married',
            'date_of_birth' => today()->subYears(70)->toDateString(),
            'target_retirement_age' => 60,
        ]);
        $spouse = User::factory()->create([
            'marital_status' => 'married',
            'date_of_birth' => today()->subYears(55)->toDateString(),
            'target_retirement_age' => $spouseRetirementAge,
            'spouse_id' => $viewer->id,
        ]);
        $viewer->update(['spouse_id' => $spouse->id]);

        // The residence band only exists where the home passes to a direct
        // descendant, and it is only CAPPED where the home is worth less than the
        // band. Both have to hold or the figure under test is zero either way.
        FamilyMember::factory()->create([
            'user_id' => $viewer->id,
            'relationship' => 'child',
        ]);

        $property = Property::factory()->create([
            'user_id' => $spouse->id,
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
            'property_type' => 'main_residence',
            'current_value' => 120_000,
        ]);
        Mortgage::factory()->create([
            'user_id' => $spouse->id,
            'property_id' => $property->id,
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
            'outstanding_balance' => 100_000,
            'maturity_date' => null,
        ]);

        return (float) app(IHTCalculationService::class)
            ->calculate($viewer->fresh(), $spouse->fresh(), true)['projected_rnrb_residence_cap_reduction'];
    };

    // Retiring at 80 leaves 25 years to clear the debt, so a horizon shorter than
    // that leaves some of it outstanding and the home is worth less at death.
    // Retiring at 56 clears it within one year and the whole value stands.
    expect($build(80))->not->toBe($build(56));
});
