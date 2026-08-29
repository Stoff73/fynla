<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * W-0528 — marks which gifts a trust settlement wrote.
 *
 * `TrustObserver` records a chargeable lifetime transfer when a trust is created,
 * and that gift is what withholds the settlor's nil rate band for seven years.
 * Nothing linked the two, so the observer could only ever handle `created`:
 * editing the settled amount left the old figure withholding the old band, and
 * deleting the trust left the gift behind withholding a band for a settlement
 * that no longer existed.
 *
 * Matching on the trust NAME was the alternative and it does not survive a
 * rename, which is exactly the edit most likely to happen. Follows the
 * `bequests.will_document_id` precedent (W-0023): the sync owns the rows it
 * stamped, and a NULL means the user entered the gift themselves.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gifts', function (Blueprint $table) {
            $table->foreignId('trust_id')
                ->nullable()
                ->after('user_id')
                ->constrained('trusts')
                ->cascadeOnDelete();
        });

        // Adopt the gifts the observer already wrote, so an existing trust starts
        // linked rather than orphaned. Narrowed by the observer's own note, so a
        // chargeable lifetime transfer the USER entered by hand is never claimed
        // even where it matches a trust on every other column.
        DB::table('gifts')
            ->join('trusts', function ($join) {
                $join->on('gifts.user_id', '=', 'trusts.user_id')
                    ->on('gifts.recipient', '=', 'trusts.trust_name')
                    ->on('gifts.gift_date', '=', 'trusts.trust_creation_date');
            })
            ->where('gifts.gift_type', 'clt')
            ->where('gifts.notes', 'like', 'Chargeable Lifetime Transfer%Auto-recorded.')
            ->whereNull('gifts.trust_id')
            ->update(['gifts.trust_id' => DB::raw('trusts.id')]);
    }

    public function down(): void
    {
        Schema::table('gifts', function (Blueprint $table) {
            $table->dropForeign(['trust_id']);
            $table->dropColumn('trust_id');
        });
    }
};
