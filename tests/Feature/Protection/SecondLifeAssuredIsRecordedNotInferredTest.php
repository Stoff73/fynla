<?php

declare(strict_types=1);

use App\Http\Requests\Protection\StoreLifePolicyRequest;
use App\Models\LifeInsurancePolicy;
use App\Models\User;
use App\Services\Protection\LifeCoverReach;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * W-0200. `life_insurance_policies` recorded THAT a policy covered two lives and
 * never WHOSE, so the only available answer was `users.spouse_id`. A key-person
 * policy over a business partner, an unmarried couple's policy, or a parent and
 * adult child were all silently attributed to the spouse — or to nobody.
 *
 * These pin the order: a recorded second life assured beats the inference, and the
 * inference only answers where nobody has.
 */
beforeEach(function () {
    $this->reach = app(LifeCoverReach::class);

    $this->owner = User::factory()->create(['first_name' => 'David', 'surname' => 'Chen']);
    $this->spouse = User::factory()->create(['first_name' => 'Sarah', 'surname' => 'Chen']);
    $this->partner = User::factory()->create(['first_name' => 'Marcus', 'surname' => 'Reid']);

    $this->owner->update(['spouse_id' => $this->spouse->id]);
    $this->spouse->update(['spouse_id' => $this->owner->id]);
});

function jointPolicyFor(User $owner, array $attributes = []): LifeInsurancePolicy
{
    return LifeInsurancePolicy::factory()->create(array_merge([
        'user_id' => $owner->id,
        'joint_life' => true,
        'sum_assured' => 500_000,
    ], $attributes));
}

it('names a recorded second life assured rather than the spouse', function () {
    $policy = jointPolicyFor($this->owner, ['joint_life_with_user_id' => $this->partner->id]);

    expect($this->reach->otherLifeAssured($policy, $this->owner->fresh()))->toBe('Marcus Reid')
        ->and($this->reach->otherLifeAssuredSource($policy, $this->owner->fresh()))->toBe('recorded');
});

it('reaches the named account, which is not the spouse', function () {
    jointPolicyFor($this->owner, ['joint_life_with_user_id' => $this->partner->id]);

    expect($this->reach->policiesCovering($this->partner->fresh()))->toHaveCount(1)
        // The wife is not a life assured on her husband's key-person policy, and
        // used to be told she was.
        ->and($this->reach->policiesCovering($this->spouse->fresh()))->toHaveCount(0);
});

it('accepts a second life assured who holds no account', function () {
    $policy = jointPolicyFor($this->owner, ['joint_life_with_name' => 'Marcus Reid']);

    expect($this->reach->otherLifeAssured($policy, $this->owner->fresh()))->toBe('Marcus Reid')
        ->and($this->reach->otherLifeAssuredSource($policy, $this->owner->fresh()))->toBe('recorded')
        ->and($this->reach->policiesCovering($this->spouse->fresh()))->toHaveCount(0);
});

it('tells the named account whose policy covers them', function () {
    $policy = jointPolicyFor($this->owner, ['joint_life_with_user_id' => $this->partner->id]);

    expect($this->reach->otherLifeAssured($policy, $this->partner->fresh()))->toBe('David Chen');
});

it('tells a third party nothing about either name', function () {
    $policy = jointPolicyFor($this->owner, ['joint_life_with_user_id' => $this->partner->id]);
    $stranger = User::factory()->create();

    expect($this->reach->otherLifeAssured($policy, $stranger))->toBeNull()
        ->and($this->reach->otherLifeAssuredSource($policy, $stranger))->toBeNull();
});

it('still infers the spouse where nobody recorded a second life assured', function () {
    $policy = jointPolicyFor($this->owner);

    expect($this->reach->otherLifeAssured($policy, $this->owner->fresh()))->toBe('Sarah Chen')
        ->and($this->reach->otherLifeAssuredSource($policy, $this->owner->fresh()))->toBe('inferred_from_spouse')
        ->and($this->reach->policiesCovering($this->spouse->fresh()))->toHaveCount(1);
});

it('reaches a covered life once, not twice', function () {
    // The named account is also the spouse — the ordinary case, recorded explicitly.
    jointPolicyFor($this->owner, ['joint_life_with_user_id' => $this->spouse->id]);

    expect($this->reach->policiesCovering($this->spouse->fresh()))->toHaveCount(1);
});

it('keeps the second life assured out of the household in-trust count', function () {
    // Covering a life and owning the contract stay two questions (W-0341): a policy
    // has one user_id, so naming a second life must not double the household figure.
    jointPolicyFor($this->owner, [
        'joint_life_with_user_id' => $this->spouse->id,
        'in_trust' => true,
    ]);

    $household = $this->reach->householdCoverInTrust($this->owner->fresh());

    expect($household['total'])->toBe(500_000.0)
        ->and($household['count'])->toBe(1);
});

it('the request classes accept the pair', function () {
    $rules = (new StoreLifePolicyRequest)->rules();

    expect($rules)->toHaveKeys(['joint_life_with_user_id', 'joint_life_with_name']);
});
