<?php

declare(strict_types=1);

use App\Models\FamilyMember;
use App\Models\Property;
use App\Models\User;
use App\Services\Estate\IHTCalculationService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * W-0365 — a joint owner holds a qualifying residential interest.
 *
 * IHTA 1984 s8H(2): a qualifying residential interest is an interest in a
 * dwelling-house which has at some time been the person's residence. A beneficial
 * co-owner recorded as `joint_owner_id` has one. Nothing in ss8E–8H requires being the
 * primary named owner of a database row.
 *
 * `hasMainResidence()` filtered to primary-owner-only and said so deliberately — "to
 * match the pre-PR-5a semantics", a statement about this codebase rather than about
 * the statute. **The file contradicted itself:** `sumMainResidenceNetShare()` uses the
 * joint-aware reader and counts that same user's share into the s8E(2) cap, so a joint
 * owner's share raised the cap on a band they were refused.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

it('grants the residence band to a joint owner who is not the primary owner', function () {
    $recorder = User::factory()->create(['marital_status' => 'single', 'date_of_birth' => '1950-01-01']);
    $coOwner = User::factory()->create(['marital_status' => 'single', 'date_of_birth' => '1950-01-01']);

    // One home, held between them. The row names the recorder; the co-owner's
    // interest is `joint_owner_id` — a beneficial interest, and s8H(2) asks about
    // interests, not about who typed the record in.
    Property::factory()->create([
        'user_id' => $recorder->id,
        'joint_owner_id' => $coOwner->id,
        'property_type' => 'main_residence',
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
        'current_value' => 800_000,
    ]);

    // The band also requires the residence to pass to a lineal descendant (s8E), so
    // both need a child or neither gets it for an unrelated reason.
    foreach ([$recorder, $coOwner] as $person) {
        FamilyMember::factory()->create(['user_id' => $person->id, 'relationship' => 'child']);
    }

    $service = app(IHTCalculationService::class);

    $recorded = $service->calculate($recorder->fresh(), null, false);
    $coOwned = $service->calculate($coOwner->fresh(), null, false);

    // Same interest, same band. Under the defect the co-owner was refused it outright
    // while their share still counted toward the taper cap on it.
    expect((float) $coOwned['rnrb_available'])->toBeGreaterThan(0.0);
    expect((float) $coOwned['rnrb_available'])->toEqualWithDelta((float) $recorded['rnrb_available'], 0.01);
});

it('still refuses the band to someone with no residential interest at all', function () {
    $noHome = User::factory()->create(['marital_status' => 'single', 'date_of_birth' => '1950-01-01']);
    FamilyMember::factory()->create(['user_id' => $noHome->id, 'relationship' => 'child']);

    $result = app(IHTCalculationService::class)->calculate($noHome, null, false);

    expect((float) $result['rnrb_available'])->toEqualWithDelta(0.0, 0.01);
});
