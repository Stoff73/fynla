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
 * either death and those are mutually exclusive events. The same policy appearing
 * in both estates would be a double count, which is why the estate asset
 * aggregation is untouched.
 *
 * **The schema constrains who the other life is.** `life_insurance_policies` has
 * `joint_life` (a boolean) and a free-text `beneficiaries` string. It carries no
 * `joint_owner_id`, no `ownership_type` and no `ownership_percentage`, unlike
 * every other shared record in the application. So the second life assured can
 * only be the linked spouse. Naming a second life explicitly would be a schema
 * change and is raised separately.
 */
class LifeCoverReach
{
    /**
     * The life policies covering this user's life: their own, plus any joint-life
     * policy their linked spouse recorded.
     *
     * @return Collection<int, LifeInsurancePolicy>
     */
    public function policiesCovering(User $user): Collection
    {
        $own = $user->relationLoaded('lifeInsurancePolicies')
            ? $user->lifeInsurancePolicies
            : $user->lifeInsurancePolicies()->get();

        $spouseId = $user->spouse_id;

        if ($spouseId === null) {
            return collect($own->all());
        }

        $sharedWithSpouse = LifeInsurancePolicy::query()
            ->where('user_id', $spouseId)
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
     */
    public function otherLifeAssured(LifeInsurancePolicy $policy, User $viewer): ?string
    {
        if (! $policy->joint_life) {
            return null;
        }

        $other = $this->isOwnedBy($policy, $viewer)
            ? $viewer->spouse
            : User::find($policy->user_id);

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
     * @return array{user_amount: float, spouse_amount: float, total: float, count: int}
     */
    public function householdCoverInTrust(User $user): array
    {
        $own = LifeInsurancePolicy::query()
            ->where('user_id', $user->id)
            ->where('in_trust', true)
            ->get();

        $spouseId = $user->spouse_id;

        $spousePolicies = $spouseId === null
            ? collect()
            : LifeInsurancePolicy::query()
                ->where('user_id', $spouseId)
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
