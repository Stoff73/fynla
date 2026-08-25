<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * W-0051 — repair households whose spouse record does not match their spouse link.
 *
 * Two states exist in live data, both created by writers that could name a
 * relationship but not establish a link:
 *
 *  1. **Orphan.** `relationship='spouse'` with `linked_user_id` NULL on a user
 *     whose account IS linked. The card claimed a link it never had and the row
 *     could not be edited or deleted from any surface.
 *  2. **Duplicate.** The same household holding the orphan AND the real linked
 *     row — reached by adding a spouse during onboarding and then linking their
 *     account from settings. Two cards for one person, both captioned "Account
 *     Linked", neither removable, and every consumer that iterates
 *     `family_members` (Fyn's household context, the data export) seeing two
 *     spouses.
 *
 * The repair adopts an orphan where there is nothing else to keep, and folds it
 * into the real row where there is — filling only fields the keeper is missing,
 * so it can add information but never overwrite it.
 *
 * Nothing is hard-deleted. `FamilyMember` uses SoftDeletes, so a folded
 * duplicate is retained exactly as the retention rule requires
 * (August/August19Updates/spec/deleted-spouse-visibility.md §1).
 *
 * Dry-run by default, matching `estate:backfill-bequests` and `fyn:episodic:purge`.
 *
 * Idempotent: after a run no household holds both a keeper and an adoptable
 * duplicate, so a second run reports nothing to do. A household with no live
 * spouse link is left alone on purpose — those rows are now ordinary editable
 * records, which is the correct end state, not a defect to repair.
 */
class ReconcileSpouseFamilyLinks extends Command
{
    protected $signature = 'family:reconcile-spouse-links
        {--force : Apply the changes (default is a dry-run that rolls back)}
        {--user= : Restrict to one user id}';

    protected $description = 'Reconcile spouse family_members rows with the account link they claim. Dry-run unless --force.';

    /**
     * Copied from a folded duplicate onto the keeper only where the keeper has
     * nothing. `national_insurance_number` is handled separately: it is excluded
     * from $fillable for mass-assignment safety and has to be assigned directly.
     *
     * @var list<string>
     */
    private const FOLDABLE_FIELDS = [
        'first_name',
        'middle_name',
        'last_name',
        'date_of_birth',
        'gender',
        'annual_income',
        'notes',
    ];

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $onlyUser = $this->option('user') !== null ? (int) $this->option('user') : null;

        $userIds = $this->householdsWithSpouseRows($onlyUser);

        if ($userIds->isEmpty()) {
            $this->info('Nothing to reconcile: no user holds a spouse family member record.');

            return self::SUCCESS;
        }

        $this->line($force ? 'Reconciling.' : 'DRY RUN — nothing will be written. Re-run with --force to apply.');
        $this->newLine();

        $adopted = 0;
        $folded = 0;
        $leftUnlinked = 0;
        $conflicts = 0;
        $rows = [];

        // One transaction for the whole run so a dry-run reports the REAL
        // outcome — produced by the same writes --force performs — and then
        // rolls it back, rather than a second implementation guessing at it.
        DB::beginTransaction();

        try {
            foreach ($userIds as $userId) {
                $user = User::find($userId);

                if ($user === null) {
                    continue;
                }

                $outcome = $this->reconcileUser($user);

                $adopted += $outcome['adopted'];
                $folded += $outcome['folded'];
                $leftUnlinked += $outcome['left_unlinked'];
                $conflicts += $outcome['conflicts'];

                if ($outcome['notes'] !== []) {
                    $rows[] = [
                        $user->id,
                        $user->email,
                        implode('; ', $outcome['notes']),
                    ];
                }
            }

            if ($force) {
                DB::commit();
            } else {
                DB::rollBack();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        if ($rows !== []) {
            $this->table(['User', 'Email', 'What happens'], $rows);
            $this->newLine();
        }

        $this->line("Orphan rows adopted onto the live link: {$adopted}");
        $this->line("Duplicate rows folded into the linked row and retired: {$folded}");
        $this->line("Spouse rows left as ordinary records (no live account link): {$leftUnlinked}");

        if ($conflicts > 0) {
            $this->warn("Rows linked to a DIFFERENT account, left untouched: {$conflicts}");
        }

        if (! $force && ($adopted > 0 || $folded > 0)) {
            $this->newLine();
            $this->line('Re-run with --force to apply.');
        }

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, int>
     */
    private function householdsWithSpouseRows(?int $onlyUser): Collection
    {
        $query = FamilyMember::query()
            ->where('relationship', 'spouse')
            ->when($onlyUser !== null, fn ($q) => $q->where('user_id', $onlyUser))
            ->distinct()
            ->orderBy('user_id')
            ->pluck('user_id');

        return $query->map(static fn ($id) => (int) $id);
    }

    /**
     * @return array{adopted: int, folded: int, left_unlinked: int, conflicts: int, notes: list<string>}
     */
    private function reconcileUser(User $user): array
    {
        $result = ['adopted' => 0, 'folded' => 0, 'left_unlinked' => 0, 'conflicts' => 0, 'notes' => []];

        $spouseRows = FamilyMember::where('user_id', $user->id)
            ->where('relationship', 'spouse')
            ->orderBy('id')
            ->get();

        // The link the rows should agree with. `liveSpouseId()` rather than the
        // raw column: a spouse who has closed their account leaves `spouse_id`
        // behind for retention, and adopting a row onto a dead account would
        // recreate the very dead end this repairs.
        $liveSpouseId = $user->liveSpouseId();

        $unlinked = $spouseRows->whereNull('linked_user_id');
        $conflicting = $spouseRows->filter(
            static fn (FamilyMember $row) => $row->linked_user_id !== null && $row->linked_user_id !== $liveSpouseId
        );

        if ($conflicting->isNotEmpty()) {
            $result['conflicts'] = $conflicting->count();
            $result['notes'][] = $conflicting->count().' row(s) link to another account — left alone';
        }

        if ($liveSpouseId === null) {
            if ($unlinked->isNotEmpty()) {
                $result['left_unlinked'] = $unlinked->count();
                $result['notes'][] = $unlinked->count().' spouse record(s) with no account to link to — now editable, left as they are';
            }

            return $result;
        }

        $keeper = $spouseRows->firstWhere('linked_user_id', $liveSpouseId);

        if ($keeper === null) {
            // Nothing carries the link. Adopt the oldest orphan onto it, and
            // treat any others as duplicates of that one.
            $keeper = $unlinked->first();

            if ($keeper === null) {
                return $result;
            }

            $keeper->linked_user_id = $liveSpouseId;
            $keeper->save();

            $result['adopted'] = 1;
            $result['notes'][] = "record {$keeper->id} adopted onto the link with user {$liveSpouseId}";
        }

        foreach ($unlinked as $duplicate) {
            if ($duplicate->id === $keeper->id) {
                continue;
            }

            $this->foldInto($keeper, $duplicate);
            $duplicate->delete();

            $result['folded']++;
            $result['notes'][] = "record {$duplicate->id} folded into {$keeper->id} and retired";
        }

        // More than one row already carrying the same link is the same
        // duplicate in a later state — fold those too, keeping the oldest.
        $extraLinked = $spouseRows
            ->where('linked_user_id', $liveSpouseId)
            ->reject(static fn (FamilyMember $row) => $row->id === $keeper->id);

        foreach ($extraLinked as $duplicate) {
            $this->foldInto($keeper, $duplicate);
            $duplicate->delete();

            $result['folded']++;
            $result['notes'][] = "duplicate linked record {$duplicate->id} folded into {$keeper->id} and retired";
        }

        return $result;
    }

    /**
     * Fill gaps on the keeper from a duplicate. Never overwrites — a value the
     * keeper already holds is the one the user last confirmed through the
     * linking form, and the duplicate is by definition the older, unverified
     * copy.
     */
    private function foldInto(FamilyMember $keeper, FamilyMember $duplicate): void
    {
        $changed = false;

        foreach (self::FOLDABLE_FIELDS as $field) {
            $existing = $keeper->getAttribute($field);
            $incoming = $duplicate->getAttribute($field);

            if (($existing === null || $existing === '') && $incoming !== null && $incoming !== '') {
                $keeper->setAttribute($field, $incoming);
                $changed = true;
            }
        }

        if ($keeper->national_insurance_number === null && $duplicate->national_insurance_number !== null) {
            $keeper->national_insurance_number = $duplicate->national_insurance_number;
            $changed = true;
        }

        if ($changed) {
            $keeper->save();
        }
    }
}
