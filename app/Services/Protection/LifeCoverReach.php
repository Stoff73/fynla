<?php

declare(strict_types=1);

namespace App\Services\Protection;

use App\Models\LifeInsurancePolicy;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Which life policies cover THIS user's life — the one home for that question
 * (Rule 20).
 *
 * A joint-life policy covers two lives and is recorded once, on the account that
 * entered it. Every consumer read `$user->lifeInsurancePolicies`, a plain hasMany
 * on `user_id`, so the policy reached the person who typed it and stopped: the
 * other life assured was told "No Protection Coverage — your family may face
 * financial difficulties" on the one product whose entire purpose is covering
 * both of them (W-0186).
 *
 * **Covering a life and owning the contract are two different questions, and the
 * split is deliberate.** This class answers the first. The second — whose asset
 * is it, whose estate do the proceeds fall into, who may edit or cancel it — is
 * still `user_id`, and nothing here changes it. A joint-life policy appearing on
 * both accounts' protection analysis is correct, because it pays out in full on
 * either death and those are mutually exclusive events.
 *
 * **The estate ASSET aggregation is untouched** — `gatherUserAssets()` does not
 * read life policies at all, from either account, so the same policy cannot land
 * in two estates. `EstateAssetAggregatorService::getExistingLifeCover()` DOES read
 * this class (W-0341): it is a per-life cover figure, not an estate asset, and the
 * distinction is the whole of §"Two questions" below.
 *
 * **What passes this gate is more than a number.** Consumers render the reached
 * policy through `LifeInsurancePolicyResource`, which ships `policy_number`,
 * `premium_amount`, `premium_frequency` and the free-text `beneficiaries` field —
 * commonly the couple's children — alongside the sum assured. Widening what
 * reaches the second life assured means widening that, so weigh it against the
 * resource, not against this class's method names.
 *
 * **The schema constrains who the other life is.** `life_insurance_policies` has
 * `joint_life` (a boolean) and a free-text `beneficiaries` string. It carries no
 * `joint_owner_id`, no `ownership_type` and no `ownership_percentage`, unlike
 * every other shared record in the application. So the second life assured can
 * only be the linked spouse. Naming a second life explicitly would be a schema
 * change and is raised separately.
 *
 * **Two questions live here, and they must not be added together (W-0341).**
 * `policiesCovering()` answers *"is this life covered?"* — it reaches across the
 * link, so a joint-life policy appears on BOTH accounts. `householdCoverInTrust()`
 * answers *"what does this household hold?"* — it takes each account's OWN rows,
 * so the policy is counted ONCE. Summing a per-life figure across two people
 * doubles a policy that pays out once; reaching inside a household figure does the
 * same. Every consumer must know which of the two it is asking.
 */
class LifeCoverReach
{
    /**
     * The spouse whose joint-life policies reach this user — the one gate every
     * method here passes through (W-0278, W-0341).
     *
     * **A spouse link has four states and only one of them may disclose.**
     *
     * 1. **Live and reciprocal** — both accounts name each other and both are open.
     *    The policies reach. This is the W-0186 case and the only one that shares.
     * 2. **Deleted partner.** `users.spouse_id` deliberately survives the partner
     *    deleting their account — everything is retained for regulatory purposes —
     *    so the raw column kept a closed account's policies flowing to the survivor
     *    forever, as cover they may no longer have (W-0278). `liveSpouse()` is the
     *    established answer (CSJ decision D1/D2, 2026-08-19: retain the rows, ignore
     *    them at read time), and it is what `DependantsReach` uses for the same
     *    reason.
     * 3. **Linked from one side only.** `spouse_id` is a claim the account holder
     *    makes, unilaterally. Honouring it would disclose one person's £500,000
     *    contract to another on nothing but the reader's own say-so.
     *    `User::hasReciprocalSpouseLink()` is declared THE authorization rule for
     *    exactly this — an attached id granting the linked account visibility of a
     *    record — so it is called here rather than re-derived.
     * 4. **`SpousePermission` revoked or never accepted — still reaches, deliberately.**
     *    `hasAcceptedSpousePermission()` is NOT the instrument for this: it returns
     *    true for any married reciprocal pair whatever the row says, so it cannot
     *    express "revoked" in the first place, and its `marital_status === 'married'`
     *    requirement would newly hide a linked unmarried couple's joint-life policy
     *    from the person it insures — W-0186 reopened for them. The disclosure marker
     *    here is `joint_life` itself: a policy its owner recorded as covering two
     *    lives. A single-life policy never reaches, in any state. Whether an explicit
     *    revocation should additionally suppress it is a product call, raised as
     *    W-0345 rather than decided here.
     *
     * **What this gate does NOT buy, stated so nobody trusts it further than it
     * goes (W-0347).** Reciprocity is only as good as the writes that establish it,
     * and `SpouseLinkingService::linkExistingSpouse()` writes BOTH rows — the
     * caller's and the named account's — plus `accepted` permission rows for both,
     * on one party's say-so. So a reciprocal link is not proof that the other person
     * agreed to anything; it is proof that somebody typed their email address. This
     * gate raises the bar from "any account plus a column write" to "any account
     * plus the target's email plus the target being unlinked". It is not closure,
     * and it cannot become closure from inside this class.
     */
    private function coveringSpouse(User $user): ?User
    {
        $spouse = $user->liveSpouse();

        if ($spouse === null || ! $user->hasReciprocalSpouseLink($spouse->id)) {
            return null;
        }

        return $spouse;
    }

    /**
     * The life policies covering this user's life: their own, plus any joint-life
     * policy their linked spouse recorded.
     *
     * **Per-life. Never sum this across a household** — a joint-life policy is in
     * both collections on purpose, because it covers both lives and pays out on
     * either death, and those are mutually exclusive events. Adding the two totals
     * would count one £500,000 payout as £1,000,000. `householdCoverInTrust()` is
     * the household question.
     *
     * @return Collection<int, LifeInsurancePolicy>
     */
    public function policiesCovering(User $user): Collection
    {
        $own = $user->relationLoaded('lifeInsurancePolicies')
            ? $user->lifeInsurancePolicies
            : $user->lifeInsurancePolicies()->get();

        $spouse = $this->coveringSpouse($user);

        if ($spouse === null) {
            return collect($own->all());
        }

        $sharedWithSpouse = LifeInsurancePolicy::query()
            ->where('user_id', $spouse->id)
            ->where('joint_life', true)
            ->get();

        return collect($own->all())->concat($sharedWithSpouse);
    }

    /**
     * Does this policy belong to the person looking at it?
     *
     * A policy reaching a user through their spouse is theirs to be covered by
     * and not theirs to change — the write path is scoped to `user_id`, so an
     * edit would fail. Surfaces use this to present it without an edit affordance
     * rather than offering one that cannot work.
     */
    public function isOwnedBy(LifeInsurancePolicy $policy, User $user): bool
    {
        return $policy->user_id === $user->id;
    }

    /**
     * The other life a joint-life policy covers, as seen by this user.
     *
     * Symmetric on purpose: on the owner's account it names their spouse, and on
     * the spouse's account it names the owner. One rule, so the two accounts
     * cannot describe the same policy differently.
     *
     * Once the link is dead or one-sided the policy stays joint-life — it is still
     * the contract the owner bought — but there is nobody this application may name
     * as the second life, so it names nobody. The gate is `coveringSpouse()`, which
     * also resolves the relation without lazy loading: `preventLazyLoading()` is on
     * outside production, and reaching for `$viewer->spouse` on a model loaded as
     * part of a collection throws.
     *
     * **BOTH branches are gated, deliberately.** The non-owner branch used to be
     * `User::find($policy->user_id)` — an ungated name lookup, safe only because the
     * one path that hands a viewer a policy they do not own is itself gated. That is
     * an implicit invariant, and the first caller to pass a policy fetched by id
     * would have got a free disclosure of its owner's name. It is enforced here
     * instead: a policy names its owner only to the spouse it actually reaches.
     */
    public function otherLifeAssured(LifeInsurancePolicy $policy, User $viewer): ?string
    {
        if (! $policy->joint_life) {
            return null;
        }

        $spouse = $this->coveringSpouse($viewer);

        $other = $this->isOwnedBy($policy, $viewer)
            ? $spouse
            : ($spouse?->id === $policy->user_id ? $spouse : null);

        if (! $other) {
            return null;
        }

        return trim($other->first_name.' '.$other->surname) ?: null;
    }

    /**
     * The household's life cover written in trust — the amount AND the policies
     * behind it, from one pass over one set.
     *
     * The estate plan summed the user's in-trust cover and the spouse's, then
     * printed the count of the user's own policies beside it. So a spouse with no
     * policy of her own was shown "Cover in Trust £500,000 · Total Policies 0"
     * (W-0186). A total and its count now come from the same place and cannot
     * disagree.
     *
     * Policies NOT in trust are deliberately left individual: that figure drives
     * "place this policy in trust", an action only the policy's owner can take.
     *
     * **Each side contributes its OWN rows, not the rows covering it.** A policy
     * has exactly one `user_id`, so the union of the two accounts' own policies
     * contains each policy once. Reaching here instead — asking what covers David
     * and adding what covers Sarah — would report their one £500,000 joint policy
     * as £1,000,000 of household cover.
     *
     * @return array{user_amount: float, spouse_amount: float, total: float, count: int}
     */
    public function householdCoverInTrust(User $user): array
    {
        $own = LifeInsurancePolicy::query()
            ->where('user_id', $user->id)
            ->where('in_trust', true)
            ->get();

        $spouse = $this->coveringSpouse($user);

        $spousePolicies = $spouse === null
            ? collect()
            : LifeInsurancePolicy::query()
                ->where('user_id', $spouse->id)
                ->where('in_trust', true)
                ->get();

        $userAmount = (float) $own->sum('sum_assured');
        $spouseAmount = (float) $spousePolicies->sum('sum_assured');

        return [
            'user_amount' => $userAmount,
            'spouse_amount' => $spouseAmount,
            'total' => $userAmount + $spouseAmount,
            'count' => $own->count() + $spousePolicies->count(),
        ];
    }
}
