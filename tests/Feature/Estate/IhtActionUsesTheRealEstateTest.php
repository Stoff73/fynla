<?php

declare(strict_types=1);

use App\Models\Property;
use App\Models\User;
use App\Services\Estate\EstateActionDefinitionService;
use Database\Seeders\EstateActionDefinitionSeeder;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * W-0501 — the "estate exceeds the nil-rate band" recommendation estimated the
 * estate by hand and got it wrong in both directions.
 *
 * `estimateEstateValue()` summed each asset's FULL value with no
 * `ownership_percentage`, then scoped on `user_id` — which drops every asset where
 * the user is the `joint_owner_id` rather than the primary owner. Measured on a
 * £295,000 property held 40/60:
 *
 *   primary owner   true share 118,000   reported 295,000
 *   joint owner     true share 177,000   reported 0
 *
 * The zero is the half that matters. The recommendation is gated on that figure —
 * `if ($estateValue <= $availableBand) return []` — so a user whose exposure sits
 * in a co-owned asset they do not hold as primary owner was told **nothing at all**
 * about an Inheritance Tax liability they have. A suppressed warning, not a
 * conservative estimate.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(EstateActionDefinitionSeeder::class);
});

function ihtActionFor(User $user): ?array
{
    $actions = app(EstateActionDefinitionService::class)->evaluateActions($user->fresh())['recommendations'] ?? [];

    // On `definition_key`, not the title. The displayed title is "Estate Value
    // Exceeds Nil-Rate Band" — hyphenated — and matching prose found nothing, so an
    // earlier draft of these tests passed and failed for reasons that had nothing to
    // do with the defect.
    return collect($actions)->first(fn (array $a) => ($a['definition_key'] ?? null) === 'iht_exceeds_nrb');
}

it('warns the joint owner whose share alone exceeds the band', function () {
    $primary = User::factory()->create(['marital_status' => 'single']);
    // The viewer is the JOINT owner, not the primary — the case that reported £0.
    $viewer = User::factory()->create(['marital_status' => 'single']);

    Property::factory()->create([
        'user_id' => $primary->id,
        'joint_owner_id' => $viewer->id,
        'ownership_type' => 'tenants_in_common',
        'ownership_percentage' => 50,
        'current_value' => 2_000_000,
        'property_type' => 'main_residence',
    ]);

    // Their half is £1,000,000, far above the £325,000 band. Before the fix the
    // estimate returned £0 for them and the recommendation never fired.
    expect(ihtActionFor($viewer))->not->toBeNull(
        'the joint owner was told nothing about a liability on a £1,000,000 share'
    );
});

it('does not inflate the primary owner to the whole property', function () {
    $viewer = User::factory()->create(['marital_status' => 'single']);
    $other = User::factory()->create(['marital_status' => 'single']);

    // 40% of £700,000 is £280,000 — BELOW the £325,000 band, so no warning is due.
    // Counting the whole £700,000, as the hand-rolled estimate did, invents one.
    Property::factory()->create([
        'user_id' => $viewer->id,
        'joint_owner_id' => $other->id,
        'ownership_type' => 'tenants_in_common',
        'ownership_percentage' => 40,
        'current_value' => 700_000,
        'property_type' => 'main_residence',
    ]);

    expect(ihtActionFor($viewer))->toBeNull(
        'a £280,000 share is under the band; the whole £700,000 is not theirs to be warned about'
    );
});
