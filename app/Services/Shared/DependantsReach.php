<?php

declare(strict_types=1);

namespace App\Services\Shared;

use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Who depends financially on THIS user — the one home for that question
 * (Rule 20).
 *
 * A household's children are entered once, on whichever account was in front of
 * the person typing. `family_members.user_id` therefore records **who typed it**,
 * not **whose children they are**. Every consumer read `user_id` alone, so the
 * parent who did not do the data entry was assessed as childless: Sarah Jones was
 * told "No dependants means you can afford to take more investment risk" about the
 * same two children that told her husband "Multiple dependants means financial
 * stability is a priority" (W-0272). One household, two parents, opposite
 * investment guidance, from one pair of rows.
 *
 * This is F-0019's **reach** failure — the derived set omits the counterparty's
 * side — and it is answered the same way `LifeCoverReach` answers it for a
 * joint-life policy: follow the account link, and follow it symmetrically so the
 * two accounts cannot describe one household differently.
 *
 * **Three rules the naive union gets wrong, and why each is here:**
 *
 * 1. **The viewer is not their own dependant.** The spouse's list contains a row
 *    describing the viewer. If that row is flagged dependent — which it is for a
 *    non-earning spouse, a legitimate and common state — a plain union would count
 *    the reader as depending on themselves. Rows reached THROUGH the spouse that
 *    describe the spouse relationship are dropped; the user's own record of their
 *    partner is kept, because a financially dependent partner genuinely is a
 *    dependant of theirs. `UserProfileService` already draws the line in exactly
 *    this place ("spouse's children, NOT the spouse record itself").
 *
 * 2. **The same child recorded on both accounts is one child.** Nothing stops both
 *    parents entering William. Identity is `linked_user_id` where the row is backed
 *    by an account, and otherwise name plus date of birth — the same key
 *    `UserProfileService::buildFamilyMembers()` already dedupes spouse children on.
 *    A row with neither a link nor anything to identify it by keeps its own id as
 *    its key, so an unnameable row is never silently merged with another.
 *
 * 3. **The link must be LIVE.** `users.spouse_id` survives the partner deleting
 *    their account — everything is retained for regulatory purposes — so raw
 *    `spouse_id` would keep reaching into a closed account's records forever.
 *    `liveSpouseId()` is the established answer (CSJ decision D1/D2, 2026-08-19:
 *    retain the rows, ignore them at read time).
 *
 * **No spouse-permission gate, deliberately.** `hasAcceptedSpousePermission()`
 * governs disclosing a partner's *financial* data — their assets, their estate,
 * their income. The count of children in a household is not that, and the
 * application already treats it as jointly known without a gate:
 * `ProfileCompletenessChecker::hasDependants()` reads the spouse's children to
 * decide whether the USER's own profile is complete. Adding a gate here would make
 * this the one place in the application where a linked parent's children stop
 * being their children.
 */
class DependantsReach
{
    /**
     * The columns every consumer of this collection needs.
     *
     * `stated_relationship` is selected because `display_relationship` is computed
     * from it — a partial select without it silently falls back to the stored enum
     * and prints "Other Dependent" for someone's partner, on the one page about
     * their financial dependants (W-0115). `linked_user_id` and `date_of_birth`
     * are selected because the appended accessors read them, and because identity
     * is derived from them here.
     *
     * @var list<string>
     */
    private const COLUMNS = [
        'id',
        'user_id',
        'linked_user_id',
        'relationship',
        'stated_relationship',
        'first_name',
        'last_name',
        'name',
        'date_of_birth',
        'is_dependent',
    ];

    /**
     * Everyone financially dependent on this user, across the linked household.
     *
     * The user's own rows sort first, so where both accounts recorded the same
     * person the row the viewer can actually edit is the one that survives.
     *
     * @return Collection<int, FamilyMember>
     */
    public function dependantsOf(User $user): Collection
    {
        $spouseId = $user->liveSpouseId();

        $rows = FamilyMember::query()
            ->whereIn('user_id', $spouseId === null ? [$user->id] : [$user->id, $spouseId])
            ->where('is_dependent', true)
            ->get(self::COLUMNS);

        return $rows
            ->reject(fn (FamilyMember $member): bool => $this->describesTheViewer($member, $user))
            ->sortBy(fn (FamilyMember $member): int => $member->user_id === $user->id ? 0 : 1)
            ->unique(fn (FamilyMember $member): string => $this->identity($member))
            ->values();
    }

    /**
     * How many people depend on this user.
     */
    public function countFor(User $user): int
    {
        return $this->dependantsOf($user)->count();
    }

    /**
     * A row reached through the spouse that describes the person reading it.
     *
     * Only rows on the SPOUSE's account can do this — the user's own record of
     * their partner is theirs, and a dependent partner is a real dependant.
     */
    private function describesTheViewer(FamilyMember $member, User $user): bool
    {
        if ($member->user_id === $user->id) {
            return false;
        }

        return $member->linked_user_id === $user->id || $member->relationship === 'spouse';
    }

    /**
     * What makes this person the same person on the other account's list.
     */
    private function identity(FamilyMember $member): string
    {
        if ($member->linked_user_id !== null) {
            return 'account:'.$member->linked_user_id;
        }

        $name = strtolower(trim((string) $member->name));
        $dateOfBirth = $member->date_of_birth?->format('Y-m-d');

        if ($name === '' || $name === 'unknown') {
            return 'row:'.$member->id;
        }

        return 'person:'.$name.'|'.($dateOfBirth ?? 'unknown');
    }
}
