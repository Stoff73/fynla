<?php

declare(strict_types=1);

use App\Models\Estate\Bequest;
use App\Models\Estate\Will;
use App\Models\FamilyMember;
use App\Models\Property;
use App\Models\SavingsAccount;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * W-0132 — the rate a user is shown must be the rate their figure was calculated at.
 *
 * `IHTPlanning.vue` decided the rate itself, from `users.charitable_bequest`, because
 * the summary block the screen reads published `iht_rate_percent` but not the type or
 * the message — so the only surface that could state the rate correctly was
 * `/plans/estate`, which reads the raw calculation. The screen that shows the figure
 * could not name its own rate.
 *
 * The assertions here are RELATIONSHIPS inside one response — the published rate,
 * divided into the published liability, must reach the published taxable estate. A
 * payload where the rate and the figure disagree fails, whatever either of them says.
 */
beforeEach(function () {
    $this->seed(TierConfigurationSeeder::class);
    $this->seed(TaxConfigurationSeeder::class);
});

function estateOwnerWithLegacy(?float $legacy): User
{
    // The Inheritance Tax calculation is a Premium capability; a Free account never
    // reaches the payload under test.
    $user = User::factory()->withActivePremiumSubscription()->create([
        'marital_status' => 'single',
        'date_of_birth' => '1968-03-04',
        // Set to the value that would give the WRONG answer if anything still read
        // it: a user who has "said no" but whose will leaves a qualifying legacy.
    ]);

    Property::factory()->create([
        'user_id' => $user->id,
        'property_type' => 'main_residence',
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'current_value' => 400_000,
    ]);
    SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'current_balance' => 600_000,
    ]);
    FamilyMember::factory()->create([
        'user_id' => $user->id,
        'relationship' => 'child',
    ]);

    if ($legacy !== null) {
        $will = Will::create([
            'user_id' => $user->id,
            'has_will' => true,
            'spouse_primary_beneficiary' => false,
            'spouse_bequest_percentage' => 0,
        ]);
        Bequest::create([
            'will_id' => $will->id,
            'user_id' => $user->id,
            'beneficiary_name' => 'Cancer Research UK',
            'beneficiary_type' => 'charity',
            'bequest_type' => 'specific_amount',
            'specific_amount' => $legacy,
            'priority_order' => 1,
        ]);
    }

    return $user->fresh();
}

it('publishes the rate, its type and its explanation on the summary the screen reads', function () {
    $user = estateOwnerWithLegacy(null);

    Sanctum::actingAs($user);

    $current = $this
        ->postJson('/api/estate/calculate-iht')
        ->assertOk()
        ->json('iht_summary.current');

    // All three were needed and only the first was published, which is why the screen
    // was left deciding the rate for itself.
    expect($current)->toHaveKeys(['iht_rate', 'iht_rate_percent', 'iht_rate_type', 'iht_rate_message']);
});

it('publishes a rate that divides into the liability beside it', function () {
    $user = estateOwnerWithLegacy(null);

    Sanctum::actingAs($user);

    $current = $this
        ->postJson('/api/estate/calculate-iht')
        ->assertOk()
        ->json('iht_summary.current');

    // The whole defect in one assertion: label ÷ figure must reconcile. Priya's screen
    // said 40% above a number that was 36% of the estate beside it.
    expect($current['taxable_estate'])->toBeGreaterThan(0)
        ->and(round($current['iht_liability'] / $current['taxable_estate'] * 100))
        ->toBe((float) $current['iht_rate_percent']);
});

it('reaches the reduced rate from the recorded will, on a user whose toggle says no', function () {
    // Baseline is net estate less the nil rate band; the 10% threshold on it is well
    // under £100,000 here, so this legacy qualifies. `charitable_bequest` is false on
    // this account — if anything still read it, the rate would come back standard.
    $user = estateOwnerWithLegacy(100_000);

    Sanctum::actingAs($user);

    $current = $this
        ->postJson('/api/estate/calculate-iht')
        ->assertOk()
        ->json('iht_summary.current');

    expect($current['iht_rate_type'])->toBe('reduced')
        ->and($current['charitable_deduction'])->toEqual(100_000)
        ->and(round($current['iht_liability'] / $current['taxable_estate'] * 100))
        ->toBe((float) $current['iht_rate_percent']);
});

it('keeps the standard rate for a legacy that does not clear the threshold, and still deducts it', function () {
    // A £500 legacy is exempt under IHTA 1984 s23 and does NOT reach the 10%
    // component. "Has a legacy" is not "qualifies for the reduced rate", and a screen
    // that shortcuts the two gets this account wrong.
    $user = estateOwnerWithLegacy(500);

    Sanctum::actingAs($user);

    $current = $this
        ->postJson('/api/estate/calculate-iht')
        ->assertOk()
        ->json('iht_summary.current');

    expect($current['iht_rate_type'])->toBe('standard')
        ->and($current['charitable_deduction'])->toEqual(500)
        ->and(round($current['iht_liability'] / $current['taxable_estate'] * 100))
        ->toBe((float) $current['iht_rate_percent']);
});

it('publishes the projected column its own rate, which need not match today', function () {
    $user = estateOwnerWithLegacy(null);

    Sanctum::actingAs($user);

    $summary = $this
        ->postJson('/api/estate/calculate-iht')
        ->assertOk()
        ->json('iht_summary');

    // The projection re-runs the 10% test against the projected estate (W-0136), so a
    // single label across both columns would be wrong in one of them. The screen can
    // only state them separately if both are published.
    expect($summary['projected'])->toHaveKey('iht_rate_percent')
        ->and($summary['projected']['iht_rate_percent'])->not->toBeNull();
});
