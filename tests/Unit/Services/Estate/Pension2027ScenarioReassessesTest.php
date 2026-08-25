<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\FamilyMember;
use App\Models\Property;
use App\Models\User;
use App\Services\Estate\IHTCalculationService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * W-0364 — adding the pension pots enlarges the estate, so the estate-size tests have
 * to be re-run against the enlarged one.
 *
 * The scenario reused `$baseCalc['total_allowances']` and `iht_rate` — the SMALLER
 * estate's answers. Both tests that turn on estate size were skipped:
 *
 *   - the residence band taper, IHTA 1984 s8D(5): £1 of band for every £2 above
 *     £2,000,000. An estate under the threshold that crosses it once the pension is
 *     added kept its whole band, UNDERSTATING the post-2027 bill by up to £350,000
 *     for a couple.
 *   - the 10% charitable rate test, Sch 1A: the baseline grows with the estate while a
 *     fixed legacy does not.
 *
 * This is W-0136's defect in the one place W-0136 did not reach.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

it('tapers the residence band away when the pension pushes the estate over the threshold', function () {
    $user = User::factory()->create([
        'marital_status' => 'single',
        'date_of_birth' => '1950-01-01',
        'gender' => 'male',
    ]);
    FamilyMember::factory()->create(['user_id' => $user->id, 'relationship' => 'child']);

    // £1,900,000 of home — under the £2,000,000 taper threshold, so the residence
    // band is intact in the current column.
    Property::factory()->create([
        'user_id' => $user->id,
        'joint_owner_id' => null,
        'property_type' => 'main_residence',
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'current_value' => 1_900_000,
    ]);

    // £600,000 of pension. Outside the estate today; inside it from April 2027, which
    // carries the estate past £2,000,000 and strips the band at £1 per £2.
    DCPension::factory()->create([
        'user_id' => $user->id,
        'current_fund_value' => 600_000,
    ]);

    $result = app(IHTCalculationService::class)->calculate($user->fresh(), null, false);
    $scenario = $result['pension_amendment'];

    expect($scenario['amendment_warning'])->toBeTrue();
    expect((float) $result['rnrb_available'])->toBeGreaterThan(0.0);

    // Under the defect the post-2027 bill was struck with the pre-pension allowances,
    // so it came out 40% of £600,000 = £240,000 above the current bill and no more.
    // With the band correctly tapered away the increase is larger.
    $additional = (float) $scenario['post_2027_rules']['additional_iht'];
    expect($additional)->toBeGreaterThan(240_000.0);
});

it('leaves a household far below the threshold unaffected by the taper', function () {
    $user = User::factory()->create([
        'marital_status' => 'single',
        'date_of_birth' => '1950-01-01',
        'gender' => 'male',
    ]);
    FamilyMember::factory()->create(['user_id' => $user->id, 'relationship' => 'child']);
    Property::factory()->create([
        'user_id' => $user->id,
        'joint_owner_id' => null,
        'property_type' => 'main_residence',
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'current_value' => 400_000,
    ]);
    DCPension::factory()->create([
        'user_id' => $user->id,
        'current_fund_value' => 100_000,
    ]);

    $scenario = app(IHTCalculationService::class)
        ->calculate($user->fresh(), null, false)['pension_amendment'];

    // £500,000 total against £325,000 + £175,000: still nothing to tax, and the
    // scenario must not invent a bill by mis-striking the allowances.
    expect((float) $scenario['post_2027_rules']['additional_iht'])->toEqualWithDelta(0.0, 0.01);
});
