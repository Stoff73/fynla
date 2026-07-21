<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Models\Household;
use App\Models\User;

/**
 * Ensures every user going through Fyn onboarding has a household_id before
 * spouse / dependant / family_member records get written.
 *
 * B-2 — Session 3 found that newly-registered users entered onboarding with
 * `users.household_id = NULL`. The SpouseLinkingService and director's
 * inline family_member writes propagated `$currentUser->household_id`
 * unconditionally, so both the primary user AND their newly-linked spouse
 * ended up with NULL household_ids. That silently broke the "plan together"
 * value proposition (every household-scoped query returned nothing).
 *
 * This service creates a Household row on demand and backfills the user's
 * `household_id`, making it idempotent so it's safe to call from multiple
 * entry points without double-creating.
 */
final class HouseholdProvisioner
{
    /**
     * Return the user's household_id, creating a Household row if the user
     * doesn't yet have one. The user record is updated in place.
     */
    public function ensureFor(User $user): int
    {
        if ($user->household_id !== null) {
            return (int) $user->household_id;
        }

        $household = Household::create([
            'household_name' => $this->defaultName($user),
        ]);

        $user->household_id = $household->id;
        $user->save();

        return (int) $household->id;
    }

    private function defaultName(User $user): string
    {
        $first = trim((string) ($user->first_name ?? ''));
        if ($first !== '') {
            return $first.' Household';
        }

        $surname = trim((string) ($user->surname ?? ''));
        if ($surname !== '') {
            return $surname.' Household';
        }

        return 'Household';
    }
}
