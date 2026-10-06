<?php

declare(strict_types=1);

namespace App\Services\Estate;

use App\Models\LifeInsurancePolicy;
use App\Models\User;
use App\Services\Protection\LifeCoverReach;
use App\Support\HouseholdPooling;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class LifeCoverCalculator
{
    public function __construct(
        private readonly LifeCoverReach $lifeCoverReach,
    ) {}

    // The life cover scenarios that lived here (full cover, cover less gifting,
    // self-insurance) priced whole of life cover from typed-in rates with no
    // source, and had no production caller. Removed (Rule 23; CSJ 2026-10-06
    // "we do not make up monthly premiums"). The live life cover path is
    // LifePolicyStrategyService, via LifePolicyController.

    /**
     * Assess existing life insurance policies for Inheritance Tax planning suitability.
     *
     * Checks each policy for:
     * - Trust status: is the policy written in trust (keeps proceeds outside the estate)?
     * - Joint life: is it a first-death or second-death policy (second death is more
     *   Inheritance Tax-efficient for married couples)?
     * - Policy type: whole of life vs term (term policies expire and may leave gaps)
     *
     * @param  Collection  $policies  Collection of LifeInsurancePolicy models
     * @param  User  $user  The primary user
     * @return array Assessment with warnings and recommendations
     */
    public function assessExistingPolicies(Collection $policies, User $user): array
    {
        $warnings = [];
        $isMarried = HouseholdPooling::hasSpousalStatus($user);

        foreach ($policies as $policy) {
            // Check trust status
            if (! $policy->in_trust) {
                $sumAssured = number_format((float) $policy->sum_assured);
                $policyName = $policy->provider ?? 'Life Insurance Policy';

                // What is TRUE about the cover, and what the reader can ACT ON, are two
                // different things, and this warning used to conflate them (W-0382).
                // A joint-life policy reaches the other life assured, so this loop now
                // sees policies the reader does not hold — and it told her the proceeds
                // fall into "your taxable estate" and to "contact your provider", about
                // a contract that is neither hers nor changeable by her. The fact is
                // hers to know; the action is the policyholder's.
                $isOwn = $this->lifeCoverReach->isOwnedBy($policy, $user);
                $holder = $isOwn ? null : $this->lifeCoverReach->otherLifeAssured($policy, $user);

                $message = $isOwn
                    ? "{$policyName} (£{$sumAssured}) is not written in trust. Without trust placement, the policy proceeds will form part of your taxable estate and may be subject to Inheritance Tax. Contact your provider to place this policy in trust."
                    : "{$policyName} (£{$sumAssured}) is not written in trust. Without trust placement, the policy proceeds will form part of the policyholder's taxable estate and may be subject to Inheritance Tax. This policy is held by ".($holder ?? 'the other life assured').', so placing it in trust is arranged by them.';

                $warnings[] = [
                    'type' => 'not_in_trust',
                    'severity' => 'high',
                    'policy_id' => $policy->id,
                    'message' => $message,
                ];
            }

            // Check joint life status for married users
            if (! $policy->joint_life && $isMarried) {
                $warnings[] = [
                    'type' => 'single_life_married',
                    'severity' => 'medium',
                    'policy_id' => $policy->id,
                    'message' => 'This is a single life policy. For Inheritance Tax planning, a joint life second death policy is typically more cost-effective as it pays out on the second death when the Inheritance Tax liability actually arises.',
                ];
            }

            // Check policy type: whole of life vs term
            if ($policy->policy_type !== 'whole_of_life') {
                $endDate = $policy->policy_end_date;
                $expiryWarning = '';

                if ($endDate) {
                    $yearsUntilExpiry = now()->diffInYears($endDate, false);

                    if ($yearsUntilExpiry <= 0) {
                        $expiryWarning = ' This policy has already expired.';
                    } elseif ($yearsUntilExpiry <= 5) {
                        $expiryWarning = ' This policy expires on '.Carbon::parse($endDate)->format('j F Y').' (within '.(int) ceil($yearsUntilExpiry).' years). Review whether replacement cover is needed.';
                    }
                }

                $warnings[] = [
                    'type' => 'not_whole_of_life',
                    'severity' => $endDate && now()->diffInYears($endDate, false) <= 5 ? 'high' : 'low',
                    'policy_id' => $policy->id,
                    'message' => 'This is a term policy, not whole of life cover. Inheritance Tax cover requires whole of life insurance to guarantee a payout whenever death occurs.'.$expiryWarning,
                ];
            }
        }

        return [
            'policy_count' => $policies->count(),
            'warnings' => $warnings,
            'warning_count' => count($warnings),
            'has_critical_warnings' => collect($warnings)->contains('severity', 'high'),
            'summary' => count($warnings) > 0
                ? count($warnings).' potential issue'.($warnings !== 1 ? 's' : '').' found with your existing life insurance policies for Inheritance Tax planning.'
                : 'Your existing life insurance policies are well-structured for Inheritance Tax planning.',
        ];
    }
}
