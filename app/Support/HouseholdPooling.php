<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;

/**
 * Does a calculation cover one person's records or two? W-0474.
 *
 * The one home for that question. It used to be asked in several places, in several
 * ways, and the disagreements moved tax in both directions:
 *
 *   - `IHTCalculationService` headline: `marital_status === 'married'` plus sharing
 *   - its projection branches: sharing and a linked account, **no marital status**
 *   - `HouseholdCashFlowProjector`: sharing and a linked account
 *
 * Neither `liveSpouse()` nor `hasAcceptedSpousePermission()` consults
 * `marital_status`, so a **civil partnership** had two projected estates assessed
 * against one person's allowances, and an **unmarried couple** who had linked accounts
 * and accepted sharing had their estates pooled against a single nil rate band and a
 * spouse exemption they are not entitled to.
 *
 * A civil partnership is a marriage for Inheritance Tax throughout: IHTA 1984 s18
 * (spouse exemption), s8A (transferable nil rate band) and s8G (brought-forward
 * residence allowance) each read "spouse or civil partner", inserted by the Tax and
 * Civil Partnership Regulations 2005 (SI 2005/3229) reg 7 under the power in Civil
 * Partnership Act 2004 s.246, in force 5 December 2005. Verified against
 * legislation.gov.uk and HMRC IHTM11031 by `tax-compliance-reviewer`, 2026-08-24.
 *
 * **Public, and in `Support`, on purpose.** The first version of this was a private
 * constant on one service, which cannot be the "one home" it claimed to be — four
 * sibling services still read `['married']` alone and show a civil partnership the
 * wrong answer on adjacent screens (W-0480). A shared rule they can actually read is
 * the precondition for closing that.
 */
final class HouseholdPooling
{
    /**
     * The marital statuses that pool two people's records into one estate.
     *
     * `single` and `divorced` get no spouse exemption, no transferable nil rate band
     * and no brought-forward residence allowance. `widowed` is deliberately absent
     * and handled separately: what passed to a survivor is already in the survivor's
     * own records, and what did not is not theirs to be taxed on — the transferred
     * bands reach them through their own `IHTProfile` instead.
     */
    public const POOLING_MARITAL_STATUSES = ['married', 'civil_partnership'];

    /**
     * Every marital status the application accepts — W-0509.
     *
     * Distinct from the pooling list above: this is the whole vocabulary, and it is
     * what a validation rule needs. `IHTController` restated it as the literal
     * `in:single,married,widowed,divorced` and so rejected a civil partnership's
     * Inheritance Tax profile with a 422 — for four months after the users column had
     * been widened to accept the status.
     *
     * Neither that rule nor the stale column enum contained a quoted `'married'`, which
     * is exactly why the W-0480 sweep guard could not see either of them. A shared list
     * is the fix; another regex is not.
     *
     * Mirrors `users.marital_status` and `iht_profiles.marital_status`. Adding a status
     * means widening both columns as well as this line.
     */
    public const ALL_MARITAL_STATUSES = ['single', 'married', 'civil_partnership', 'divorced', 'widowed'];

    public static function hasSpousalStatus(?User $user): bool
    {
        return $user !== null
            && in_array($user->marital_status, self::POOLING_MARITAL_STATUSES, true);
    }

    /**
     * Whether this calculation's figures cover both members of the household.
     *
     * Reads the VIEWER's own declared status, not the partner's. The linking path
     * writes both sides in one transaction so they start symmetric; a later unilateral
     * profile edit can desynchronise them, and the two accounts would then see two
     * different bills. Reading the viewer's own declaration is the defensible choice —
     * if one party has divorced and the other has not updated their profile, the
     * party who updated is the one with the accurate picture.
     */
    public static function poolsSpouse(User $user, ?User $spouse, bool $dataSharingEnabled): bool
    {
        return $spouse !== null && $dataSharingEnabled && self::hasSpousalStatus($user);
    }
}
