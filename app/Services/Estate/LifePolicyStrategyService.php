<?php

declare(strict_types=1);

namespace App\Services\Estate;

use App\Models\User;

/**
 * Whole of life cover for an Inheritance Tax liability.
 *
 * States the cover the liability calls for, the kind of policy, and how to
 * arrange it. It prices nothing: the premium table and the self-insurance
 * comparison built on it that lived here were typed-in rates with no source
 * (Rule 23; CSJ 2026-10-06 "we do not make up monthly premiums"). Only an
 * insurer's quote, after its own underwriting, gives a premium.
 */
class LifePolicyStrategyService
{
    /**
     * @param  float  $coverAmount  The Inheritance Tax liability to cover
     * @param  int  $yearsUntilDeath  Years until expected death
     * @param  int  $currentAge  User's current age
     * @param  string  $gender  User's gender
     * @param  int|null  $spouseAge  Spouse age, for a joint life second death policy
     * @param  string|null  $spouseGender  Spouse gender, for a joint life policy
     */
    public function calculateStrategy(
        float $coverAmount,
        int $yearsUntilDeath,
        int $currentAge,
        string $gender,
        ?int $spouseAge = null,
        ?string $spouseGender = null,
        ?User $user = null
    ): array {
        $isJointPolicy = $spouseAge !== null && $spouseGender !== null;

        return [
            'success' => true,
            'cover_amount' => round($coverAmount, 2),
            'years_until_death' => $yearsUntilDeath,
            'current_age' => $currentAge,
            'gender' => $gender,
            'is_joint_policy' => $isJointPolicy,
            'spouse_age' => $spouseAge,
            'spouse_gender' => $spouseGender,

            'whole_of_life_policy' => $this->wholeOfLifePolicy($coverAmount, $yearsUntilDeath, $isJointPolicy),
            'comparison' => [
                'decision_framework' => [
                    'Choose Insurance if:' => [
                        'You want cover in place from day one',
                        'You prefer a guaranteed payout to an investment that may fall short',
                        'You would not keep saving into a fund set aside for the tax',
                    ],
                    'Choose Self-Insurance if:' => [
                        'You can set money aside for the tax and leave it invested',
                        'You have a long time before the liability is expected',
                        'You are comfortable that the fund may fall short if death comes early',
                    ],
                    'Choose Hybrid if:' => [
                        'You want some guaranteed cover alongside your own savings',
                        'You can compare insurers\' quotes for part of the liability',
                    ],
                ],
            ],
        ];
    }

    private function wholeOfLifePolicy(float $coverAmount, int $yearsUntilDeath, bool $isJointPolicy): array
    {
        return [
            'policy_type' => $isJointPolicy ? 'Joint Life Second Death' : 'Whole of Life (Single Life)',
            'description' => $isJointPolicy
                ? 'Pays out on the second death only, when the Inheritance Tax on the joint estate falls due.'
                : 'Pays out whenever death occurs.',
            'cover_amount' => round($coverAmount, 2),
            'term_years' => $yearsUntilDeath,
            'guaranteed_payout' => round($coverAmount, 2),

            'key_features' => [
                'Pays out at death, whenever that occurs',
                // A policy the deceased owns falls into their estate (HMRC IHTM20211,
                // https://www.gov.uk/hmrc-internal-manuals/inheritance-tax-manual/ihtm20211);
                // written in trust, the trustees own it instead.
                'Written in trust, the payout stays outside the estate',
                $isJointPolicy
                    ? 'Joint life second death policy pays out on the second death'
                    : 'Can be replaced by a joint policy if you marry or enter a civil partnership',
            ],

            'implementation_steps' => [
                'Ask several insurers for whole of life quotes for this cover',
                'Answer each insurer\'s health and lifestyle questions in full',
                'Have the policy written in trust, so the payout stays outside the estate',
                'Set up the premium payments the insurer quotes',
                'Review the cover each year against the Inheritance Tax figure',
                'Tell the trustees and beneficiaries where the policy is held',
            ],
        ];
    }
}
