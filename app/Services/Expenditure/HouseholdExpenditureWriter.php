<?php

declare(strict_types=1);

namespace App\Services\Expenditure;

use App\Models\ExpenditureProfile;
use App\Models\User;
use App\Services\Cache\CacheInvalidationService;
use App\Support\SharedExpenditure;
use Illuminate\Support\Facades\DB;

/**
 * The one place a household's spending is written (Rule 20).
 *
 * `SharedExpenditure` is the one home for the RULE — how a household figure
 * divides. This is the one home for the WRITE: the household total is the
 * source of truth, and both accounts' halves are derived from it here, in one
 * transaction, from one payload.
 *
 * W-0190 made the profile path apply the rule to the account doing the typing.
 * It did not give that path anywhere to put the other half. The spouse's row
 * was left to a SECOND, INDEPENDENT HTTP request the frontend was trusted to
 * send (`PUT /api/users/{id}/expenditure`) — which the backend never required,
 * never verified, and could not compensate for. On 2026-08-22 at 20:24 it did
 * not arrive: `audit_logs` #1376 moved David's row from £1,225 to £1,250 and
 * there is no matching row for Sarah. Her half stayed at half of the old,
 * wrong £2,450.
 *
 * The visible result was a table headed "Joint (50/50) expenditure" reading
 * 50.5 / 49.5, an Essential Living household of £775 where the categories give
 * £800, and a household total of £2,475 where it is £2,500 — because the
 * household was being computed as *David's half plus Sarah's half*, so when the
 * halves disagreed THE HOUSEHOLD INHERITED THE ERROR instead of being the
 * source of truth. It drifted the first time anyone edited a category, which is
 * the ordinary case and not an edge case.
 *
 * Two halves kept in step by two separate requests will come apart. One
 * household figure, divided once, written to both rows together, cannot.
 *
 * The live-spouse test is deliberate. The old predicate was `spouse_id !== null`,
 * which is still set when the spouse's account has been deleted and the record
 * retained — so a household with nobody left to share with had its spending
 * halved into a row that no longer existed. `liveSpouse()` is the same accessor
 * the rest of the profile uses, and it is lazy-loading safe.
 */
final class HouseholdExpenditureWriter
{
    public function __construct(
        private readonly CacheInvalidationService $cacheInvalidation
    ) {}

    /**
     * Write a household's spending to every account that carries a share of it.
     *
     * @param  User  $user  the account doing the entering — its declared sharing
     *                      mode is the HOUSEHOLD's, because it is the one that
     *                      has just declared it
     * @param  array<string, mixed>  $household  the figures as the household
     *                                           spends them; partial payloads
     *                                           stay partial
     * @return array<string, mixed> the share actually stored on each row
     */
    public function write(User $user, array $household): array
    {
        // **If you add the sharing mode to a payload, check every caller that
        // pre-divides.** This prefers the payload; `CoordinatingAgent::handleSetExpenditure`
        // reads `$user->expenditure_sharing_mode` directly when deciding whether to
        // double stored halves back to household terms. The two agree today only
        // because that method never writes the mode into what it passes here.
        //
        // Should a turn ever carry a mode change alongside categories, they would
        // disagree — doubling on the OLD mode and dividing on the NEW — halving or
        // doubling a household's spending in one write. Flagged by quality-lead
        // 2026-08-24 as "one line away from firing".
        $mode = $household['expenditure_sharing_mode'] ?? $user->expenditure_sharing_mode;
        // W-0350 — RECIPROCAL, not merely live. This writes the other half of the
        // household into the SPOUSE'S ROW, so it is a cross-account write and needs the
        // link both parties made rather than the one this account claimed. A one-sided
        // link now falls to the unshared branch below, which is the same treatment an
        // unlinked user gets: the whole share stays on the account that submitted it.
        $spouse = $user->reciprocalLiveSpouse();
        $isShared = $spouse !== null && SharedExpenditure::isShared($mode);

        $share = SharedExpenditure::shareOf($household, $isShared);

        DB::transaction(function () use ($user, $spouse, $share, $isShared): void {
            $user->update($share);
            $this->syncProfileTotal($user, $share);

            if ($spouse === null) {
                return;
            }

            if ($isShared) {
                // Both halves, from the one household figure, in the one write.
                $spouse->update($this->spousePortionOf($share));
                $this->syncProfileTotal($spouse, $share);

                return;
            }

            // Separate spending, but the sharing mode is a fact about the
            // HOUSEHOLD, not about one row. Left on one account it drifts, and
            // the two halves of the next save get divided by different rules.
            if (isset($share['expenditure_sharing_mode'])) {
                $spouse->update(['expenditure_sharing_mode' => $share['expenditure_sharing_mode']]);
            }
        });

        $this->cacheInvalidation->invalidateForUserAndSpouse($user->id, $spouse?->id);

        return $share;
    }

    /**
     * The part of a share that belongs on the SECOND account.
     *
     * `SharedExpenditure::shareOf()` divides the fields in `SHARED_FIELDS` and
     * passes everything else through untouched — which is right for the account
     * doing the entering and wrong for the one beside it. Mirroring an
     * undivided money field would put the whole of it on both rows and make the
     * household read double: `charitable_donations` is deliberately outside the
     * list (it is a Gift Aid input, and halving it would move a tax relief
     * figure), and `rent` / `utilities` are outside it too.
     *
     * So the spouse's row receives the divided fields, and the entry and
     * sharing modes, and nothing else. An undivided figure stays whole on the
     * one row, which is exactly what makes the household sum come out right for
     * it.
     *
     * @param  array<string, mixed>  $share
     * @return array<string, mixed>
     */
    private function spousePortionOf(array $share): array
    {
        return array_intersect_key(
            $share,
            array_flip([
                ...SharedExpenditure::SHARED_FIELDS,
                'expenditure_entry_mode',
                'expenditure_sharing_mode',
            ])
        );
    }

    /**
     * Mirror the monthly total into `ExpenditureProfile`.
     *
     * `ResolvesExpenditure` prefers this row over `users.monthly_expenditure`,
     * so an account with a stale profile row and a fresh user row reports the
     * stale figure to every affordability statement that reads it. Both
     * accounts get one or neither.
     */
    /**
     * Put a survivor's stored halves back into household terms. W-0477.
     *
     * Called wherever a household stops being two accounts. There are four such
     * moments and they do not share a signature — an unlink nulls `spouse_id` on
     * both rows, a purge nulls it on the survivor, and a soft-deleted account leaves
     * `spouse_id` set while `liveSpouse()` goes null — so this cannot be detected by
     * a reader and has to be applied at each of them.
     *
     * **Why not detect it on read instead.** The obvious signal — mode `joint` with
     * no live spouse — is ambiguous: `DEFAULT_MODE` is `joint`, so a user who has
     * never had a spouse carries it too, and their stored figures were never divided.
     * Doubling those would invent spending. A marker column could disambiguate it;
     * correcting the data at the one moment its meaning changes is cheaper and leaves
     * every reader alone.
     *
     * **CSJ's choice of the two the board offered** (W-0477 acceptance 1): restore
     * the household figure onto the survivor, rather than record that the stored
     * value is a share of a household that no longer exists.
     *
     * Idempotent by its guard: it only fires for an account still declaring the
     * shared mode with no live spouse, and it clears that mode as it goes, so a
     * second call is a no-op rather than a second doubling.
     */
    public function promoteSharesToHousehold(User $survivor): void
    {
        if (! SharedExpenditure::isShared($survivor->expenditure_sharing_mode)) {
            return;
        }

        if ($survivor->liveSpouse() !== null) {
            return;
        }

        $stored = [];
        foreach (SharedExpenditure::SHARED_FIELDS as $field) {
            if ($survivor->{$field} !== null) {
                $stored[$field] = $survivor->{$field};
            }
        }

        if ($stored === []) {
            $survivor->update(['expenditure_sharing_mode' => SharedExpenditure::MODE_SEPARATE]);

            return;
        }

        $household = SharedExpenditure::householdOf($stored);
        $household['expenditure_sharing_mode'] = SharedExpenditure::MODE_SEPARATE;

        DB::transaction(function () use ($survivor, $household): void {
            $survivor->update($household);
            $this->syncProfileTotal($survivor, $household);
        });

        $this->cacheInvalidation->invalidateForUserAndSpouse($survivor->id, null);
    }

    private function syncProfileTotal(User $user, array $share): void
    {
        if (! ($share['monthly_expenditure'] ?? null)) {
            return;
        }

        ExpenditureProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'monthly_housing' => 0,
                'monthly_food' => 0,
                'monthly_utilities' => 0,
                'monthly_transport' => 0,
                'monthly_insurance' => 0,
                'monthly_loans' => 0,
                'monthly_discretionary' => 0,
                'total_monthly_expenditure' => $share['monthly_expenditure'],
            ]
        );
    }
}
