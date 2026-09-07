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
            // W-0200. The second life assured is now a recorded field, so this says
            // `recorded` where the owner named them and `inferred_from_spouse` where
            // the application still worked it out from `users.spouse_id` — which it
            // does only for policies saved before the field existed, or where the
            // owner left it blank. A surface qualifies the statement off this rather
            // than each one deciding for itself whether to.
            'joint_life_with_source' => $viewer === null
                ? null
                : $reach->otherLifeAssuredSource($this->resource, $viewer),
            'user_id' => $this->user_id,
            'policy_type' => $this->policy_type,
            'provider' => $this->provider,
            // W-0383 — CSJ ruled 2026-09-01 that the other life assured sees the whole
            // policy: "if there is a shared account, show the life policy to the other
            // user". The earlier rule withheld `policy_number` and `beneficiaries` on
            // the reasoning that reaching a policy answers "am I covered" rather than
            // licensing the contract. That reasoning was overruled, not forgotten: a
            // joint-life policy is the couple's cover, and a second life assured who
            // cannot see the policy number cannot phone the insurer about the contract
            // that insures them.
            //
            // `is_own_policy` still separates reading from writing — the write path is
            // scoped to `user_id`, so surfaces present a reached policy without an edit
            // affordance rather than offering one that cannot work.
            'policy_number' => $this->policy_number,
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
            // The owner's own record of the second life assured, so the edit form can
            // seed the picker with what was saved rather than re-deriving it.
            'joint_life_with_user_id' => $isOwn ? $this->joint_life_with_user_id : null,
            'joint_life_with_name' => $isOwn ? $this->joint_life_with_name : null,
            'is_mortgage_protection' => (bool) $this->is_mortgage_protection,
            'beneficiaries' => $this->beneficiaries,
            'indexation_rate' => $this->indexation_rate ? (float) $this->indexation_rate : null,
            'start_value' => $this->start_value ? (float) $this->start_value : null,
            'decreasing_rate' => $this->decreasing_rate ? (float) $this->decreasing_rate : null,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
