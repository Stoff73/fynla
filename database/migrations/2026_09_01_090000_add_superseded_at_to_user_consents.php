<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W-0156 — tell an abandoned visitor's consent row apart from retained evidence.
 *
 * F-0007 leaves a row UNCLAIMED when the registering user already holds a consent
 * of the same type and version: the (user_id, consent_type, version) unique key
 * forbids a duplicate, and overwriting or deleting either record would destroy
 * evidence of a decision that was actually made.
 *
 * That leaves two rows that look identical in the database — `user_id IS NULL`
 * with a `subject_token` — and mean opposite things:
 *
 *   1. A visitor who never registered. Nothing will ever claim it, and it is the
 *      row W-0156 exists to purge.
 *   2. A visitor who DID register, whose row was deliberately kept as evidence.
 *      Purging it would break F-0007's own principle.
 *
 * A purge that cannot tell them apart destroys the second while chasing the
 * first. This column is the distinction: set when the claim path deliberately
 * skips a row, and never set otherwise, so the purge can exclude it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_consents', function (Blueprint $table): void {
            $table->timestamp('superseded_at')
                ->nullable()
                ->after('withdrawn_at')
                ->comment('W-0156: set when claimAnonymousConsents deliberately kept this row as evidence because the account already held the same consent. NULL means never presented at a registration.');
        });
    }

    public function down(): void
    {
        Schema::table('user_consents', function (Blueprint $table): void {
            $table->dropColumn('superseded_at');
        });
    }
};
