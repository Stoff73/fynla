<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;

/**
 * Seeds a new user's profile from the /savetax acquisition-funnel answers so
 * Fyn's onboarding starts from what the user already told us instead of asking
 * cold. Only the answers that map cleanly to a profile column are written
 * (employment status, marital status); the income BAND is a range not a figure,
 * so it is left for Fyn to confirm as an exact value (the recap still surfaces
 * the band). Coarse acquisition data — never overwrites a value already set.
 */
class FunnelAnswersMapper
{
    /** Funnel employment value → users.employment_status enum. */
    private const EMPLOYMENT_MAP = [
        'full-time' => 'full_time',
        'part-time' => 'part_time',
        'self-employed' => 'self_employed',
        'retired' => 'retired',
        'not-employed' => 'unemployed',
    ];

    /**
     * The partner's employment from the funnel, as a
     * tax_strategy_household_inputs.spouse_employment_status value, or null
     * when it was not asked. Asked only when the partner's band is the
     * Personal Allowance taper, because their pension relief depends on
     * earnings from work (FA 2004 s189-190). Read when the household row is
     * first written (capture_spouse_household_data), not at registration:
     * creating the row early would give the plan a partner with £0 income.
     */
    public static function spouseEmploymentStatus(User $user): ?string
    {
        $funnel = is_array($user->funnel_answers) ? $user->funnel_answers : [];
        if (($funnel['spouse'] ?? null) !== 'yes') {
            return null;
        }

        return self::EMPLOYMENT_MAP[$funnel['spouseEmployment'] ?? ''] ?? null;
    }

    public function mapToProfile(User $user): void
    {
        $funnel = $user->funnel_answers ?? [];
        if (! is_array($funnel) || $funnel === []) {
            return;
        }

        $dirty = false;

        // Employment status — a clean 1:1 map.
        $employment = $funnel['employment'] ?? null;
        if (! $user->employment_status && isset(self::EMPLOYMENT_MAP[$employment])) {
            $user->employment_status = self::EMPLOYMENT_MAP[$employment];
            $dirty = true;
        }

        // Marital status — the live funnels ask "Do you have a spouse?".
        // yes → married (the spouse-detail onboarding step confirms/refines);
        // no → single. Civil partnership etc. are refined later in onboarding.
        $spouse = $funnel['spouse'] ?? null;
        if (! $user->marital_status && ($spouse === 'yes' || $spouse === 'no')) {
            $user->marital_status = $spouse === 'yes' ? 'married' : 'single';
            $dirty = true;
        }

        // Spouse income — unlike the primary's band, 'zero' is not a range
        // needing refinement: it answers the spouse-work question outright.
        // Pre-setting household_calculation_mode lets STATE_CAMPAIGN_SPOUSE_WORK
        // skip itself instead of re-asking what the funnel already captured;
        // an earning band routes to the household-data step, which still
        // confirms the exact figure conversationally.
        $spouseIncome = $funnel['spouseIncome'] ?? null;
        if ($spouse === 'yes' && ! $user->household_calculation_mode
            && is_string($spouseIncome) && $spouseIncome !== '') {
            $spouseWorks = $spouseIncome !== 'zero';
            $user->household_calculation_mode = $spouseWorks ? 'dual_earner' : 'single_earner_couple';
            $user->marriage_allowance_eligible = ! $spouseWorks;
            $dirty = true;
        }

        if ($dirty) {
            $user->save();
        }
    }
}
