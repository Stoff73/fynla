<?php

declare(strict_types=1);

namespace App\Services\Retirement;

use App\Models\User;

/**
 * W-0196 — the one answer to "at what age does this person retire, when they have
 * not said?", and the one order in which their sources are consulted.
 *
 * Before this class there were **seven** private `DEFAULT_RETIREMENT_AGE` constants
 * holding two different numbers (68 in `AssumptionsService` and
 * `GoalsProjectionService`, 67 in the four retirement services and `DBPension`), and
 * **four** independent copies of the priority chain that did not agree on order.
 * A household with a target on the retirement profile and a different one on the user
 * record got different answers from different modules, and nothing revealed it.
 *
 * ## Why 67 and not 68
 *
 * 67 was already anchored: `DBPension::DEFAULT_NORMAL_RETIREMENT_AGE` is deliberately
 * the same 67 as `PensionProjector`'s, so that a pension cannot count as income from
 * one age while being projected forward from another (W-0036). Moving the pair to 68
 * would break that; moving the two 68s to 67 does not. **68 was the outlier, not the
 * pair.**
 *
 * ## Why the retirement profile is consulted first
 *
 * `retirement_profiles.target_retirement_age` is the field the user sets deliberately,
 * through the retirement module and through Fyn's `capture_retirement_goals` — W-0035
 * made it the canonical write target for every surface. `users.target_retirement_age`
 * is the older general-purpose column and can be stale relative to it. Two of the four
 * old chains read the user record first and so preferred the staler source.
 *
 * ## This is NOT the State Pension age
 *
 * State Pension age is legislated by cohort and is a different question with a
 * different answer (W-0197, W-0516). Nothing here should be reused for it.
 */
final class RetirementAgeResolver
{
    /**
     * The age assumed when the user has told us nothing. See the class docblock for
     * why it is 67; do not change it here without changing
     * `App\Models\DBPension::DEFAULT_NORMAL_RETIREMENT_AGE` in the same edit.
     *
     * Written as plain text rather than `{@see}`: Pint normalises a fully-qualified
     * class reference in that tag back to a short name and ADDS the import, which the
     * `StoreBoundary` architecture test then rejects — a service may not use a model
     * directly. The reference is documentation, not a dependency.
     */
    public const DEFAULT_RETIREMENT_AGE = 67;

    public const SOURCE_RETIREMENT_PROFILE = 'retirement_profile';

    public const SOURCE_USER_PROFILE = 'user_profile';

    public const SOURCE_PENSION = 'pension';

    public const SOURCE_ASSUMED = 'assumed';

    /**
     * The age alone, for callers that do not surface where it came from.
     */
    public function forUser(User $user): int
    {
        return $this->withSource($user)['age'];
    }

    /**
     * The age and the source that produced it. Callers that tell the user their
     * target was inferred rather than chosen read `source` — anything other than
     * `assumed` is a figure the user actually gave us somewhere.
     *
     * @return array{age: int, source: string}
     */
    public function withSource(User $user): array
    {
        if ($user->retirementProfile?->target_retirement_age) {
            return [
                'age' => (int) $user->retirementProfile->target_retirement_age,
                'source' => self::SOURCE_RETIREMENT_PROFILE,
            ];
        }

        if ($user->target_retirement_age) {
            return [
                'age' => (int) $user->target_retirement_age,
                'source' => self::SOURCE_USER_PROFILE,
            ];
        }

        foreach ($user->dcPensions as $pension) {
            if ($pension->retirement_age) {
                return [
                    'age' => (int) $pension->retirement_age,
                    'source' => self::SOURCE_PENSION,
                ];
            }
        }

        return ['age' => self::DEFAULT_RETIREMENT_AGE, 'source' => self::SOURCE_ASSUMED];
    }
}
