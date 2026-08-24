<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The one home for the household expenditure sharing rule (Rule 20).
 *
 * `users.expenditure_sharing_mode` says how a household's spending divides between
 * two accounts. Under `joint` each account carries **its half** of the household
 * total; under `separate` each account carries what that person actually spends.
 * There is no share column on the row — the row IS the share — so the division has
 * to happen on the way IN, exactly as SharedOwnership normalises an ownership
 * percentage on the way in and every reader then trusts what is stored.
 *
 * That rule existed and was correct, in one place only: the onboarding flow
 * (`OnboardingService::processExpenditure`), which divided by two and mirrored the
 * halves onto both accounts. **The profile path did neither.** It stored the whole
 * household figure on whoever typed it and, in joint mode, mirrored the whole
 * figure to the spouse as well. Either way the declared 50/50 was never applied:
 *
 * - Where the spouse write landed, a £2,450 household read as £4,900.
 * - Where it did not, the table said "Joint (50/50) expenditure" in its own
 *   subheading and then charged £2,450 to one spouse and £0 to the other, beside a
 *   financial-commitments row that IS split by ownership. Two rows, one screen,
 *   different rules (W-0190).
 *
 * Disposable income is what every affordability statement rests on, so the error
 * ran in opposite directions on the two accounts of one household: one overstated
 * by £14,700 a year, the other understated by the same.
 */
final class SharedExpenditure
{
    /** Both accounts carry half of the household's spending. */
    public const MODE_JOINT = 'joint';

    /** Each account carries what that person actually spends. */
    public const MODE_SEPARATE = 'separate';

    /**
     * A married household is assumed to share until it says otherwise — the same
     * default `OnboardingService` and `UserResource` already apply.
     */
    public const DEFAULT_MODE = self::MODE_JOINT;

    /** Each party's fraction of a household figure under the shared mode. */
    public const JOINT_SHARE = 0.5;

    /**
     * The money fields a household expenditure payload divides.
     *
     * This is the list the onboarding path has always divided, unchanged, so that
     * routing the second path through here changes which path applies the rule and
     * not which fields it applies to.
     *
     * `charitable_donations` is **deliberately absent**. It is a Gift Aid input
     * rather than a household running cost, and halving it would move a tax relief
     * figure. Whether it should share is a question for whoever owns Gift Aid.
     */
    public const SHARED_FIELDS = [
        'food_groceries',
        'transport_fuel',
        'healthcare_medical',
        'insurance',
        'mobile_phones',
        'internet_tv',
        'subscriptions',
        'clothing_personal_care',
        'entertainment_dining',
        'holidays_travel',
        'pets',
        'childcare',
        'school_fees',
        'school_lunches',
        'school_extras',
        'university_fees',
        'children_activities',
        'gifts_charity',
        'regular_savings',
        'other_expenditure',
        'monthly_expenditure',
        'annual_expenditure',
    ];

    /**
     * Does this sharing mode divide the household's spending between two accounts?
     */
    public static function isShared(?string $mode): bool
    {
        return ($mode ?? self::DEFAULT_MODE) === self::MODE_JOINT;
    }

    /**
     * One account's share of a household expenditure payload.
     *
     * Keys outside SHARED_FIELDS pass through untouched — the entry mode, the
     * sharing mode itself, and the budget-override arrays are not money. Keys that
     * are absent stay absent, so a partial update stays partial rather than
     * writing zeros over categories the caller never mentioned.
     *
     * @param  array<string, mixed>  $household  the figures as the household entered them
     * @param  bool  $isShared  whether the household's declared mode divides them
     * @return array<string, mixed>
     */
    public static function shareOf(array $household, bool $isShared): array
    {
        if (! $isShared) {
            return $household;
        }

        foreach (self::SHARED_FIELDS as $field) {
            if (! array_key_exists($field, $household) || $household[$field] === null) {
                continue;
            }

            $household[$field] = (float) $household[$field] * self::JOINT_SHARE;
        }

        return $household;
    }

    /**
     * The household figure a stored share came from. W-0477.
     *
     * The inverse of `shareOf()`, and it lives beside it deliberately: the moment
     * these two rules are written in two places, one of them will be edited alone.
     *
     * **Why it is needed at all.** Under the shared mode the row IS the half — every
     * writer divides on the way in and every reader trusts what is stored. That is
     * only true while there are two accounts. When one goes, the stored halves do not
     * change, so the survivor holds £600 of groceries that means £1,200 of household
     * spending, and from that moment every reader — the affordability statements, the
     * cash-flow projection, `/m`'s expenditure screen — treats the half as the whole.
     * It UNDERSTATES spending and therefore OVERSTATES disposable income, which is
     * what every affordability statement rests on.
     *
     * @param  array<string, mixed>  $share  one account's stored figures
     * @return array<string, mixed> the same keys, in household terms
     */
    public static function householdOf(array $share): array
    {
        foreach (self::SHARED_FIELDS as $field) {
            if (! array_key_exists($field, $share) || $share[$field] === null) {
                continue;
            }

            $share[$field] = (float) $share[$field] / self::JOINT_SHARE;
        }

        return $share;
    }
}
