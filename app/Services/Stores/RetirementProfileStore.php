<?php

declare(strict_types=1);

namespace App\Services\Stores;

use App\Models\RetirementProfile;
use App\Models\User;
use App\Services\Stores\Exceptions\StoreValidationException;

/**
 * The one place a retirement goal is recorded.
 *
 * `retirement_profiles.target_retirement_income` is the figure every retirement
 * projection is built on — required capital, the income projection, decumulation,
 * capital adequacy, the income gap and Monte Carlo all key on it. Until W-0035 the
 * only thing that could write it was Fyn's `capture_retirement_goals` tool, so a
 * user who never chatted to Fyn had every projection built on
 * `RequiredCapitalCalculator`'s fallback — a figure they never chose and were never
 * told was derived.
 *
 * Two constraints of the table drive the shape of this class, and both are easy to
 * get wrong from a caller:
 *
 * 1. **`target_retirement_age` and `current_age` are NOT NULL with no default.** A
 *    profile therefore cannot be created from an income alone. Rather than invent an
 *    age, `updateGoals()` falls back to `users.target_retirement_age` and refuses if
 *    there is nothing to use. Fyn reaches the same conclusion by a different route
 *    (it parks the income and asks for the age), so both surfaces answer alike.
 *
 * 2. **`current_age` is a cached copy of the user's age, and it goes stale every
 *    birthday.** It is not decorative: `PensionProjector::getCurrentAge()`
 *    (`PensionProjector.php:247-248`) prefers it *over* `date_of_birth`, so a stale
 *    value silently shifts years-to-retirement for every projection. This store
 *    refreshes it on every write, not just on create — a derived column left stale
 *    by a write that had the correct source to hand is the W-0030 failure repeated.
 *
 * 3. **The retirement age has to be mirrored onto `users.target_retirement_age`.**
 *    `retirement_profiles` is the store of record, but `RetirementProjectionService`,
 *    the "When you want to retire" data requirement and `ModuleAvailabilityProvider`
 *    all read the `users` column, so leaving it behind shows the default age of 67
 *    on `/retirement` and keeps the checklist item outstanding while the goal is
 *    plainly set. Fyn's `capture_retirement_goals` handler learned this the hard way
 *    and carried the mirror alone; it lives here now so every surface gets it, which
 *    is the whole point of there being one store (Rule 20).
 */
class RetirementProfileStore
{
    /**
     * Record a target retirement age, a target retirement income, or both.
     *
     * Only the values actually supplied are written; passing null leaves the stored
     * value alone rather than clearing it, because neither surface offers the user a
     * way to say "unset this".
     *
     * @throws StoreValidationException when there is nothing to write, or when a
     *                                  profile must be created and no retirement age
     *                                  is available from any source
     */
    public function updateGoals(
        User $user,
        ?int $targetRetirementAge = null,
        ?float $targetRetirementIncome = null,
    ): RetirementProfile {
        if ($targetRetirementAge === null && $targetRetirementIncome === null) {
            throw new StoreValidationException([
                'target_retirement_income' => ['Provide a target retirement age, a target retirement income, or both.'],
            ]);
        }

        $profile = RetirementProfile::where('user_id', $user->id)->first();
        $currentAge = $user->date_of_birth?->age;

        $changes = array_filter([
            'target_retirement_age' => $targetRetirementAge,
            'target_retirement_income' => $targetRetirementIncome,
            // Refresh rather than leave stale — see the class docblock.
            'current_age' => $currentAge,
        ], static fn ($value): bool => $value !== null);

        if ($profile !== null) {
            $profile->update($changes);
            $this->mirrorRetirementAgeOntoUser($user, $targetRetirementAge);

            return $profile->fresh();
        }

        // Creating: both NOT NULL columns have to be satisfied honestly.
        $age = $targetRetirementAge ?? $user->target_retirement_age;

        if ($age === null) {
            throw new StoreValidationException([
                'target_retirement_age' => ['Set your target retirement age before recording a target retirement income.'],
            ]);
        }

        if ($currentAge === null) {
            throw new StoreValidationException([
                'target_retirement_age' => ['Add your date of birth before setting a retirement target — years to retirement cannot be worked out without it.'],
            ]);
        }

        $profile = RetirementProfile::create([
            ...$changes,
            'user_id' => $user->id,
            'target_retirement_age' => $age,
            'current_age' => $currentAge,
        ]);

        $this->mirrorRetirementAgeOntoUser($user, $targetRetirementAge);

        return $profile;
    }

    /**
     * Keep `users.target_retirement_age` in step with the profile — see constraint
     * 3 in the class docblock.
     *
     * Only mirrors a figure the user actually stated. The create path can fall back
     * to the `users` column for the profile's NOT NULL constraint, and writing that
     * value back onto itself would be a no-op dressed up as a save.
     */
    private function mirrorRetirementAgeOntoUser(User $user, ?int $targetRetirementAge): void
    {
        if ($targetRetirementAge === null || $user->target_retirement_age === $targetRetirementAge) {
            return;
        }

        $user->target_retirement_age = $targetRetirementAge;
        $user->save();
    }
}
