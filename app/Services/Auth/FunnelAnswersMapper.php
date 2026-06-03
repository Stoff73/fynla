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

        // Marital status — funnel only asks "do you have a spouse or partner?".
        // yes → married (the spouse-detail onboarding step confirms/refines);
        // no → single. Civil partnership etc. are refined later in onboarding.
        $spouse = $funnel['spouse'] ?? null;
        if (! $user->marital_status && ($spouse === 'yes' || $spouse === 'no')) {
            $user->marital_status = $spouse === 'yes' ? 'married' : 'single';
            $dirty = true;
        }

        if ($dirty) {
            $user->save();
        }
    }
}
