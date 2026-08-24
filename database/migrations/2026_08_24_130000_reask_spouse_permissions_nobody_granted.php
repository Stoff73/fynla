<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ask again, wherever sharing is switched on and nobody ever agreed to it.
 *
 * **CSJ decision, 2026-08-24**, answering the question W-0347 acceptance 3 left
 * open: *is retrospectively legitimising forged consent acceptable, or must those
 * households be re-asked?* **Re-asked.** This migration is that decision.
 *
 * It replaces `2026_08_23_120000_backfill_spouse_permissions_for_existing_links`,
 * which granted the same population an `accepted` row so nothing would go dark on
 * deploy. That migration never shipped anywhere, so it is deleted rather than
 * undone. Its docblock also asserted a safeguard it did not perform —
 * "RECIPROCAL links only … a one-sided link is precisely what the old flow could
 * forge" — when the removed code (`70c5014da`) wrote **both** sides every time.
 * Every link the defect created was reciprocal by construction, so that filter
 * excluded nothing it claimed to exclude (compliance-lead F1). Nothing here
 * filters on reciprocity for protection; it filters on it because a one-sided
 * link is not a household.
 *
 * ## Two populations, one rule
 *
 * | | fingerprint | written by |
 * |---|---|---|
 * | **A — forged** | `requested_at IS NULL`, `responded_at` set | the old `createSpousePermissions()`, which wrote `accepted` on both rows with nothing from the other party |
 * | **B — inherited** | both timestamps NULL | the deleted backfill |
 *
 * `requested_at IS NULL` is the single fingerprint of "no request was ever made",
 * and it covers both. A row with `requested_at` set was genuinely asked for, and
 * this migration does not touch it — including a `rejected` one, which is a
 * decision somebody made.
 *
 * ## What the user sees afterwards
 *
 * Sharing is **off** until the invitee accepts: `hasAcceptedSpousePermission()`
 * reads the status, and `pending` is not `accepted`. The invitee sees a request on
 * the sharing screen on web and `/m`; the requester sees it waiting.
 *
 * **No email is sent from here.** Notifying every affected household at deploy is
 * an outward-facing action and CSJ's call to make deliberately, not a side effect
 * of running migrations.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        // 1. One row per couple. The old flow wrote both directions and the unique
        //    key on (user_id, spouse_id) permits the mirror, so a couple can hold
        //    two rows that disagree — and both `revoke()` and
        //    `hasAcceptedSpousePermission()` pick one with `first()`. Withdraw on
        //    the row one query finds, and the other query still says yes
        //    (compliance-lead F5). Only ever collapses rows nobody granted.
        $unconfirmed = DB::table('spouse_permissions')
            ->whereNull('requested_at')
            ->where('status', '<>', 'rejected')
            ->orderBy('id')
            ->get(['id', 'user_id', 'spouse_id']);

        $keptCouples = [];
        $duplicateIds = [];

        foreach ($unconfirmed as $row) {
            $couple = $row->user_id < $row->spouse_id
                ? "{$row->user_id}:{$row->spouse_id}"
                : "{$row->spouse_id}:{$row->user_id}";

            if (isset($keptCouples[$couple])) {
                $duplicateIds[] = $row->id;

                continue;
            }

            $keptCouples[$couple] = $row->id;
        }

        if ($duplicateIds !== []) {
            DB::table('spouse_permissions')->whereIn('id', $duplicateIds)->delete();
        }

        // 2. Turn what survives into an unanswered request.
        DB::table('spouse_permissions')
            ->whereNull('requested_at')
            // W-0347 G10 — a row somebody actively withdrew is a decision, and
            // flipping it to `pending` would destroy the record of it and put a
            // request in its place. No such row can exist today (the old `revoke()`
            // deleted rather than marked), so this is defensive — but the guarantee
            // belongs in the code, not in a paragraph.
            ->where('status', '<>', 'rejected')
            ->update([
                'status' => 'pending',
                'requested_at' => $now,
                'responded_at' => null,
                'updated_at' => $now,
            ]);

        // 3. A reciprocal link with no row at all reads as consent too:
        //    `hasAcceptedSpousePermission()` returns true when it finds nothing,
        //    on the reasoning that a link predating the consent flow should be
        //    honoured. Under this decision it should be asked instead, so every
        //    couple gets a row and that branch stops deciding anything for
        //    existing data.
        DB::table('users as u')
            ->join('users as s', 's.id', '=', 'u.spouse_id')
            ->whereColumn('s.spouse_id', 'u.id')
            ->whereColumn('u.id', '<', 's.id')
            ->whereNull('u.deleted_at')
            ->whereNull('s.deleted_at')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('spouse_permissions as p')
                    ->where(function ($inner) {
                        $inner->where(function ($direction) {
                            $direction->whereColumn('p.user_id', 'u.id')
                                ->whereColumn('p.spouse_id', 's.id');
                        })->orWhere(function ($direction) {
                            $direction->whereColumn('p.user_id', 's.id')
                                ->whereColumn('p.spouse_id', 'u.id');
                        });
                    });
            })
            ->select('u.id as user_id', 's.id as spouse_id')
            ->orderBy('u.id')
            ->chunk(500, function ($pairs) use ($now) {
                $rows = [];

                foreach ($pairs as $pair) {
                    $rows[] = [
                        'user_id' => $pair->user_id,
                        'spouse_id' => $pair->spouse_id,
                        'status' => 'pending',
                        'requested_at' => $now,
                        'responded_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                DB::table('spouse_permissions')->insertOrIgnore($rows);
            });
    }

    public function down(): void
    {
        // Irreversible by design. The rows this touched asserted an acceptance
        // that never happened; putting that assertion back is not a rollback
        // anyone should be able to run by accident.
    }
};
