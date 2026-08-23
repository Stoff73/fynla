<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Grandfather every reciprocal spouse link that predates the consent flow.
 *
 * `User::hasAcceptedSpousePermission()` used to return true for any linked,
 * married pair without consulting `spouse_permissions` at all. That shortcut is
 * gone (W-0347): an accepted row is now the only thing that grants access.
 * Without this backfill, deploying the change would silently switch off spouse
 * data for every household whose permission rows were never written — measured
 * on the development database at the time: 6 reciprocal couples, 5 pairs of
 * rows, so one couple would have lost access with no way to tell why.
 *
 * Scope is deliberately narrow: RECIPROCAL links only. A one-sided link is
 * precisely what the old flow could forge, and granting it an accepted row here
 * would launder exactly the defect being fixed.
 *
 * `requested_at` and `responded_at` are left NULL to mark these as inherited
 * rather than granted. That NULL is the marker for the consent audit W-0347
 * acceptance 3 asks for: nobody agreed to these, they are being honoured
 * because withdrawing access from live households without warning is worse.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('users as u')
            ->join('users as s', 's.id', '=', 'u.spouse_id')
            ->whereColumn('s.spouse_id', 'u.id')
            ->whereNull('u.deleted_at')
            ->whereNull('s.deleted_at')
            ->select('u.id as user_id', 's.id as spouse_id')
            ->orderBy('u.id')
            ->chunk(500, function ($pairs) use ($now) {
                $rows = [];

                foreach ($pairs as $pair) {
                    $rows[] = [
                        'user_id' => $pair->user_id,
                        'spouse_id' => $pair->spouse_id,
                        'status' => 'accepted',
                        'requested_at' => null,
                        'responded_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                // insertOrIgnore, not updateOrInsert: the unique key on
                // (user_id, spouse_id) makes an existing row a no-op. An update
                // would reach into rows a real user actually granted — or
                // rejected — and reset them to `accepted` with the timestamps
                // wiped.
                DB::table('spouse_permissions')->insertOrIgnore($rows);
            });
    }

    public function down(): void
    {
        // Irreversible by design. Deleting these rows would revoke sharing for
        // households that have it today, and there is no record of which rows
        // this migration created versus which a user granted.
    }
};
