<?php

declare(strict_types=1);

use App\Models\CriticalIllnessPolicy;
use App\Models\LifeInsurancePolicy;
use App\Models\SpousePermission;
use App\Models\User;
use App\Services\Estate\EstateAssetAggregatorService;
use App\Services\Estate\LifeCoverCalculator;
use App\Services\Protection\LifeCoverReach;

/**
 * W-0341 and W-0278 — one joint-life policy, two lives, four link states.
 *
 * A joint-life policy covers two lives and pays out once. Two questions come out
 * of that one record and they have different right answers:
 *
 * - *"Is this life covered?"* — yes for both, so the policy is in BOTH answers.
 * - *"What does this household hold?"* — one policy, so it is counted ONCE.
 *
 * Conflating them produces either a missing £500,000 (Sarah assessed at zero on the
 * one product insuring her) or a doubled one (£1,000,000 of household cover behind a
 * single payout). Both are asserted here, in the same file, because the pair of them
 * is the invariant — either alone can be satisfied by a reader that is wrong.
 *
 * **The fixture is asymmetric deliberately** (`tests/CLAUDE.md` §4, Collision). If the
 * spouse held nothing, £500,000 would be *both* the right answer and the answer a
 * one-sided reader gives, and no assertion here could tell the two apart. She holds
 * £120,000 of her own, so every hypothesis lands on a different number:
 *
 * | Hypothesis | Sarah's cover | Household in trust |
 * |---|---|---|
 * | Correct | £620,000 | £620,000 |
 * | `user_id`-only (the bug) | £120,000 | £120,000 or £500,000 |
 * | Reach summed across both | £620,000 | £1,120,000 |
 *
 * `liveSpouse()` caches per model instance, so every test re-reads its user rather
 * than reusing a handle whose link was changed underneath it.
 */
beforeEach(function () {
    $this->owner = User::factory()->create(['first_name' => 'David', 'surname' => 'Jones']);
    $this->spouse = User::factory()->create([
        'first_name' => 'Sarah',
        'surname' => 'Jones',
        'spouse_id' => $this->owner->id,
    ]);
    $this->owner->update(['spouse_id' => $this->spouse->id]);

    $this->jointPolicy = LifeInsurancePolicy::factory()->create([
        'user_id' => $this->owner->id,
        'provider' => 'Vitality',
        'sum_assured' => 500_000,
        'joint_life' => true,
        'in_trust' => true,
    ]);

    // Hers, single-life, and a different number from his. It proves the reach is
    // limited to joint-life policies as well as making the totals separable.
    $this->spouseOwnPolicy = LifeInsurancePolicy::factory()->create([
        'user_id' => $this->spouse->id,
        'provider' => 'Aviva',
        'sum_assured' => 120_000,
        'joint_life' => false,
        'in_trust' => true,
    ]);

    $this->reach = app(LifeCoverReach::class);
});

/** The user as the application would load them now, not as this file last held them. */
function reloaded(User $user): User
{
    return User::query()->findOrFail($user->id);
}

it('reaches a joint-life policy to the other life assured', function () {
    $covering = $this->reach->policiesCovering(reloaded($this->spouse));

    expect($covering->pluck('id')->sort()->values()->all())
        ->toBe(collect([$this->spouseOwnPolicy->id, $this->jointPolicy->id])->sort()->values()->all())
        ->and((float) $covering->sum('sum_assured'))->toBe(620000.0);
});

it('does not reach a single-life policy the other way', function () {
    // The owner's answer must not move. £620,000 here would mean the reader pulled
    // everything the spouse holds rather than only what covers him.
    $covering = $this->reach->policiesCovering(reloaded($this->owner));

    expect($covering->pluck('id')->all())->toBe([$this->jointPolicy->id])
        ->and((float) $covering->sum('sum_assured'))->toBe(500000.0);
});

it('credits the estate figure to the life the policy covers', function () {
    $estate = app(EstateAssetAggregatorService::class);

    expect($estate->getExistingLifeCover(reloaded($this->spouse)))->toBe(620000.0)
        ->and($estate->getExistingLifeCover(reloaded($this->owner)))->toBe(500000.0);
});

it('leaves critical illness cover with the life it was written on', function () {
    // `critical_illness_policies` has no joint_life column, so there is nothing to
    // reach with. £820,000 would mean the spouse inherited his critical illness cover.
    CriticalIllnessPolicy::factory()->create([
        'user_id' => $this->owner->id,
        'sum_assured' => 200_000,
    ]);

    $estate = app(EstateAssetAggregatorService::class);

    expect($estate->getExistingLifeCover(reloaded($this->owner)))->toBe(700000.0)
        ->and($estate->getExistingLifeCover(reloaded($this->spouse)))->toBe(620000.0);
});

it('counts a joint-life policy once in the household total', function () {
    // £620,000 is the union of the two accounts' own rows. £1,120,000 would be the
    // reach applied to both sides; £500,000 or £120,000 would be one side alone.
    foreach ([$this->owner, $this->spouse] as $party) {
        $cover = $this->reach->householdCoverInTrust(reloaded($party));

        expect($cover['total'])->toBe(620000.0)
            ->and($cover['count'])->toBe(2);
    }
});

it('stops a deleted partner\'s policies reaching the survivor', function () {
    $this->owner->delete();

    $covering = $this->reach->policiesCovering(reloaded($this->spouse));

    expect($covering->pluck('id')->all())->toBe([$this->spouseOwnPolicy->id])
        ->and(app(EstateAssetAggregatorService::class)->getExistingLifeCover(reloaded($this->spouse)))
        ->toBe(120000.0);
});

it('drops a deleted partner\'s cover and its policy count together', function () {
    // Asserting the amount alone repeats W-0186's own defect, where a total and its
    // count came from different places and disagreed on screen.
    $this->spouse->delete();

    $cover = $this->reach->householdCoverInTrust(reloaded($this->owner));

    expect($cover['total'])->toBe(500000.0)
        ->and($cover['spouse_amount'])->toBe(0.0)
        ->and($cover['count'])->toBe(1);
});

it('names nobody as the second life once the partner has gone', function () {
    $this->spouse->delete();

    $named = $this->reach->otherLifeAssured($this->jointPolicy, reloaded($this->owner));

    expect($named)->toBeNull();
});

it('does not disclose a policy on a link claimed from one side only', function () {
    // She still names him; he no longer names her. Her `spouse_id` is her own
    // assertion, and it is not enough to open his contract to her.
    $this->owner->update(['spouse_id' => null]);

    $covering = $this->reach->policiesCovering(reloaded($this->spouse));

    expect($covering->pluck('id')->all())->toBe([$this->spouseOwnPolicy->id])
        ->and((float) $covering->sum('sum_assured'))->toBe(120000.0);
});

it('does not put a one-sided partner\'s cover into the household total', function () {
    $this->spouse->update(['spouse_id' => null]);

    $cover = $this->reach->householdCoverInTrust(reloaded($this->owner));

    expect($cover['total'])->toBe(500000.0)
        ->and($cover['spouse_amount'])->toBe(0.0)
        ->and($cover['count'])->toBe(1);
});

it('still covers both lives when a spouse permission row is refused', function () {
    // **Decided by Brett, 2026-08-25, on CSJ's delegated authority (W-0345).** A refused
    // permission does NOT suppress a joint-life policy from the life it insures.
    //
    // **Two reasons originally given here were true when written and are not now.**
    // W-0347 changed `hasAcceptedSpousePermission()`: it reads the row and returns
    // `$permission->status === 'accepted'`, so a `rejected` row **does** return false —
    // it can express a refusal. And there is no `married` requirement anywhere in that
    // chain, so gating would not hide an unmarried couple's policy either. Both
    // objections are dead; do not re-derive them from this comment's history.
    //
    // **The one reason that stands, and the whole basis of the decision:** the
    // permission gate governs disclosure of a partner's FINANCIAL position, and a
    // joint-life policy is a fact about the READER'S OWN LIFE that the owner
    // affirmatively declared by setting `joint_life`. You should not be able to hide
    // from someone that their life is insured. The failure mode of the opposite call is
    // worse: a person insured and never told, by an application that holds the fact.
    //
    // **Considered and not taken:** withholding `premium_amount` / `annual_premium`
    // from the other life assured, extending the W-0383 line that already withholds
    // `policy_number` and `beneficiaries`. A premium is money leaving the owner's
    // account rather than a fact about the reader's life. Declined as part of decision
    // A; recorded because W-0383 drew that line without considering the premium, and a
    // compliance sweep should find the question rather than re-discover it.
    //
    // A `rejected` row is used because the enum has no `revoked` member —
    // `spouse_permissions.status` is `enum('pending','accepted','rejected')`, so
    // "refused a request" and "withdrew consent" share one value (W-0346, not taken).
    SpousePermission::create([
        'user_id' => $this->owner->id,
        'spouse_id' => $this->spouse->id,
        'status' => 'rejected',
    ]);

    expect((float) $this->reach->policiesCovering(reloaded($this->spouse))->sum('sum_assured'))
        ->toBe(620000.0);
});

it('still covers both lives when no permission was ever granted', function () {
    SpousePermission::create([
        'user_id' => $this->owner->id,
        'spouse_id' => $this->spouse->id,
        'status' => 'pending',
    ]);

    expect((float) $this->reach->policiesCovering(reloaded($this->spouse))->sum('sum_assured'))
        ->toBe(620000.0);
});

/**
 * W-0382 / W-0383 — reaching a policy answers "am I covered". It is not a licence to
 * read the whole contract, nor an instruction to act on someone else's.
 *
 * Both of these were opened by the reach itself: before it, the other life assured saw
 * no policy at all, so neither branch was reachable. **Unreachable is not absent.**
 */
it('withholds the contract details the other life assured has no use for', function () {
    $asSpouse = $this->actingAs($this->spouse)->getJson('/api/protection')
        ->assertOk()->json('data.policies.life_insurance');

    $joint = collect($asSpouse)->firstWhere('id', $this->jointPolicy->id);

    expect($joint)->not->toBeNull()
        ->and($joint['is_own_policy'])->toBeFalse()
        // She is covered, and she can see by whom and for how much.
        ->and((float) $joint['sum_assured'])->toBe(500000.0)
        ->and($joint['provider'])->toBe('Vitality')
        // She cannot read his policy number or his free-text beneficiaries.
        ->and($joint['policy_number'])->toBeNull()
        ->and($joint['beneficiaries'])->toBeNull();
});

it('still gives the owner every field of their own policy', function () {
    $asOwner = $this->actingAs($this->owner)->getJson('/api/protection')
        ->assertOk()->json('data.policies.life_insurance');

    $joint = collect($asOwner)->firstWhere('id', $this->jointPolicy->id);

    expect($joint['is_own_policy'])->toBeTrue()
        ->and($joint['policy_number'])->toBe($this->jointPolicy->policy_number)
        ->and($joint['beneficiaries'])->toBe($this->jointPolicy->beneficiaries);
});

it('does not tell the other life assured to phone an insurer she has no contract with', function () {
    // The persona cannot reach this branch — its only joint-life policy is in trust —
    // so the fixture builds the state it does not have (`tests/CLAUDE.md` §4, Fixture).
    $this->jointPolicy->update(['in_trust' => false]);

    $calculator = app(LifeCoverCalculator::class);
    $reach = app(LifeCoverReach::class);

    $spouse = reloaded($this->spouse);
    $warnings = collect($calculator->assessExistingPolicies($reach->policiesCovering($spouse), $spouse)['warnings'])
        ->where('type', 'not_in_trust');

    $hers = $warnings->firstWhere('policy_id', $this->jointPolicy->id);

    expect($hers)->not->toBeNull()
        // The fact reaches her: it is her life, and the cover is not in trust.
        ->and($hers['message'])->toContain('is not written in trust')
        // The action does not, and neither does the false claim about whose estate it is.
        ->and($hers['message'])->not->toContain('Contact your provider')
        ->and($hers['message'])->not->toContain('your taxable estate')
        ->and($hers['message'])->toContain('David Jones');
});

it('still tells the policyholder to place their own policy in trust', function () {
    $this->jointPolicy->update(['in_trust' => false]);

    $calculator = app(LifeCoverCalculator::class);
    $reach = app(LifeCoverReach::class);

    $owner = reloaded($this->owner);
    $his = collect($calculator->assessExistingPolicies($reach->policiesCovering($owner), $owner)['warnings'])
        ->firstWhere('type', 'not_in_trust');

    expect($his['message'])->toContain('Contact your provider to place this policy in trust')
        ->and($his['message'])->toContain('your taxable estate');
});
