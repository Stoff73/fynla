<?php

declare(strict_types=1);

namespace App\Http\Resources\Protection;

use App\Services\Protection\LifeCoverReach;
use App\Support\PremiumAnnualiser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LifeInsurancePolicyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewer = $request->user();
        $reach = app(LifeCoverReach::class);

        // A joint-life policy covers both spouses but is recorded once, on the
        // account that entered it. It reaches the other life assured read-only —
        // the write path is scoped to `user_id`, so an edit from her account would
        // fail. Surfaces read `is_own_policy` to present it without an edit
        // affordance rather than offering one that cannot work (W-0186).
        $isOwn = $viewer === null || $reach->isOwnedBy($this->resource, $viewer);

        return [
            'id' => $this->id,
            'is_own_policy' => $isOwn,
            'joint_life_with' => $viewer === null ? null : $reach->otherLifeAssured($this->resource, $viewer),
            // W-0200. `life_insurance_policies` records THAT a policy covers two lives
            // and never WHOSE: it has no `joint_owner_id`, unlike every other shared
            // record in the application. So the name above is inferred from
            // `users.spouse_id` — the only answer available — and until now the
            // inference was invisible: a user was told their spouse is the other life
            // assured as though they had said so.
            //
            // Published as a source rather than folded into the name, the same shape as
            // `income_source` (W-0035) and life expectancy's `source` (W-0198), so a
            // surface can qualify the statement instead of each one deciding for itself
            // whether to. Becomes `recorded` on the day a second life assured is a
            // first-class field — the product call this item is gated on.
            'joint_life_with_source' => $viewer === null
                || $reach->otherLifeAssured($this->resource, $viewer) === null
                    ? null
                    : 'inferred_from_spouse',
            'user_id' => $this->user_id,
            'policy_type' => $this->policy_type,
            'provider' => $this->provider,
            // Withheld from the other life assured (W-0383). Reaching a policy answers
            // "am I covered" — it is not a licence to read the whole contract. A policy
            // number is effectively a credential for phoning the insurer, and
            // `beneficiaries` is free text that commonly names the couple's children.
            // Neither is needed to know you are covered, and both belong to the person
            // who holds the contract. Nulled rather than omitted so the key shape stays
            // constant for every client: `/m` renders `policy_number || '—'` and hides
            // the beneficiaries block on falsy, and both native fields are `String?`.
            'policy_number' => $isOwn ? $this->policy_number : null,
            'sum_assured' => (float) $this->sum_assured,
            'premium_amount' => (float) $this->premium_amount,
            'premium_frequency' => $this->premium_frequency,
            // W-0464 / Rule 20 — computed here so `/m` displays it instead of
            // annualising the premium itself in a Vue computed property. One
            // mapping, one answer, every surface.
            'annual_premium' => PremiumAnnualiser::toAnnual(
                (float) $this->premium_amount,
                $this->premium_frequency,
            ),
            'policy_start_date' => $this->policy_start_date?->format('Y-m-d'),
            'policy_end_date' => $this->policy_end_date?->format('Y-m-d'),
            'policy_term_years' => $this->policy_term_years,
            'in_trust' => (bool) $this->in_trust,
            'joint_life' => (bool) $this->joint_life,
            'is_mortgage_protection' => (bool) $this->is_mortgage_protection,
            'beneficiaries' => $isOwn ? $this->beneficiaries : null,
            'indexation_rate' => $this->indexation_rate ? (float) $this->indexation_rate : null,
            'start_value' => $this->start_value ? (float) $this->start_value : null,
            'decreasing_rate' => $this->decreasing_rate ? (float) $this->decreasing_rate : null,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
