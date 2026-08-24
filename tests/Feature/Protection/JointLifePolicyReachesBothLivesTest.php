<?php

declare(strict_types=1);

use App\Models\LifeInsurancePolicy;
use App\Models\ProtectionProfile;
use App\Models\User;
use App\Services\Estate\EstateAssetAggregatorService;
use App\Services\Protection\LifeCoverReach;

/**
 * W-0186 — a joint-life policy covers two lives and is recorded once, on the
 * account that entered it. Every consumer read the `user_id` hasMany, so the
 * other life assured was told "No Protection Coverage — your family may face
 * financial difficulties" while her own estate plan credited her with £500,000
 * of cover in trust from zero policies.
 *
 * Covering a life and owning the contract are two different questions. These
 * tests hold the first to both spouses and the second to the owner alone — a
 * policy on both protection analyses is correct, the same policy in both estates
 * would be a double count.
 */
beforeEach(function () {
    $this->owner = User::factory()->create(['first_name' => 'David', 'surname' => 'Jones']);
    $this->spouse = User::factory()->create([
        'first_name' => 'Sarah',
        'surname' => 'Jones',
        'spouse_id' => $this->owner->id,
    ]);
    $this->owner->update(['spouse_id' => $this->spouse->id]);

    $this->policy = LifeInsurancePolicy::factory()->create([
        'user_id' => $this->owner->id,
        'policy_type' => 'level_term',
        'provider' => 'Vitality',
        'sum_assured' => 500_000,
        'joint_life' => true,
        'in_trust' => true,
    ]);

    ProtectionProfile::factory()->create(['user_id' => $this->owner->id]);
    ProtectionProfile::factory()->create(['user_id' => $this->spouse->id]);

    $this->reach = app(LifeCoverReach::class);
});

it('shows a joint-life policy to the other life assured', function () {
    $response = $this->actingAs($this->spouse)->getJson('/api/protection');

    $policies = $response->assertOk()->json('data.policies.life_insurance');

    expect($policies)->toHaveCount(1)
        ->and($policies[0]['id'])->toBe($this->policy->id)
        ->and((float) $policies[0]['sum_assured'])->toBe(500000.0);
});

it('does not show a single-life policy to the spouse', function () {
    $this->policy->update(['joint_life' => false]);

    $policies = $this->actingAs($this->spouse)->getJson('/api/protection')
        ->assertOk()->json('data.policies.life_insurance');

    expect($policies)->toBeEmpty();
});

it('marks whose record it is, so no surface offers an edit that cannot work', function () {
    $asOwner = $this->actingAs($this->owner)->getJson('/api/protection')
        ->assertOk()->json('data.policies.life_insurance.0');

    $asSpouse = $this->actingAs($this->spouse)->getJson('/api/protection')
        ->assertOk()->json('data.policies.life_insurance.0');

    expect($asOwner['is_own_policy'])->toBeTrue()
        ->and($asSpouse['is_own_policy'])->toBeFalse();
});

it('names the other life assured the same way from either account', function () {
    $asOwner = $this->actingAs($this->owner)->getJson('/api/protection')
        ->assertOk()->json('data.policies.life_insurance.0');

    $asSpouse = $this->actingAs($this->spouse)->getJson('/api/protection')
        ->assertOk()->json('data.policies.life_insurance.0');

    expect($asOwner['joint_life_with'])->toBe('Sarah Jones')
        ->and($asSpouse['joint_life_with'])->toBe('David Jones');
});

it('still refuses a write from the life assured who does not hold the contract', function () {
    $this->actingAs($this->spouse)
        ->putJson("/api/protection/policies/life/{$this->policy->id}", ['sum_assured' => 10])
        ->assertNotFound();

    $this->actingAs($this->spouse)
        ->deleteJson("/api/protection/policies/life/{$this->policy->id}")
        ->assertNotFound();

    expect((float) $this->policy->fresh()->sum_assured)->toBe(500000.0);
});

it('never reports cover in trust against a policy count of zero', function () {
    foreach ([$this->owner, $this->spouse] as $party) {
        $cover = $this->reach->householdCoverInTrust($party);

        expect($cover['total'])->toBe(500000.0)
            ->and($cover['count'])->toBe(1);
    }
});

it('reports no cover and no policies when the household has neither', function () {
    $this->policy->delete();

    foreach ([$this->owner, $this->spouse] as $party) {
        $cover = $this->reach->householdCoverInTrust($party);

        expect($cover['total'])->toBe(0.0)
            ->and($cover['count'])->toBe(0);
    }
});

it('does not put the same policy into both estates', function () {
    // Covering a life is not owning the contract. The proceeds fall into the
    // estate of the person who holds it, once.
    //
    // This asserted `getExistingLifeCover($spouse) === 0.0` and called that the
    // guard against a double count. It was guarding the wrong method: a life policy
    // never enters the estate asset aggregation at all — `gatherUserAssets()` does
    // not read `LifeInsurancePolicy`, from either account, so the double count it
    // described was not reachable by that route. What the £0 actually did was tell
    // Sarah her estate plan had no life cover behind it, on the one product insuring
    // her life (W-0341). The real guard is the one below, asserted directly.
    $estate = app(EstateAssetAggregatorService::class);

    foreach ([$this->owner, $this->spouse] as $party) {
        $assetTypes = $estate->gatherUserAssets($party)->pluck('asset_type')->all();

        expect($assetTypes)->not->toContain('life_insurance');
    }

    // And the cover figure now reaches the life it covers, from both accounts.
    expect($estate->getExistingLifeCover($this->owner))->toBe(500000.0)
        ->and($estate->getExistingLifeCover($this->spouse))->toBe(500000.0);
});
