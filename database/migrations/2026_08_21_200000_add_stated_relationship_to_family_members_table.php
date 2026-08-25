<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W-0114 — record the relationship the user actually chose.
 *
 * `family_members.relationship` is `enum('spouse','child','parent',
 * 'other_dependent')` and the product offers six options, so `partner` is stored
 * as `other_dependent` and `step_child` as `child`. Rendering the stored value
 * back to the user means the application tells someone their partner is a
 * dependent — a false statement about their household, in the software's own
 * voice. Storing an alias is fine; displaying the alias as though it were the
 * truth is not.
 *
 * This column holds what was chosen, so the card can show it. Deliberately
 * ADDITIVE, not semantic:
 *
 *  - the enum keeps its four values, so every existing `where('relationship',
 *    'child')` across estate, protection, plans, intestacy and the
 *    shared-children logic behaves exactly as before;
 *  - NULL means "as stated equals as stored", so no backfill is needed and every
 *    existing row is already correct;
 *  - nothing reads this column to make a decision — it is display only.
 *
 * Widening the enum instead would make every one of those queries silently stop
 * counting step-children, which is a larger regression than the defect. That
 * remains an open question for CSJ; this does not pre-empt it, and if the enum
 * is ever widened this column becomes redundant rather than wrong.
 *
 * Not `notes`: that column is the user's own free text, and a system fact parked
 * inside it gets edited away by the person it describes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('family_members', function (Blueprint $table) {
            $table->string('stated_relationship', 32)
                ->nullable()
                ->after('relationship')
                ->comment('The relationship the user chose, when it differs from the stored enum value. NULL means they match. Display only (W-0114).');
        });
    }

    public function down(): void
    {
        Schema::table('family_members', function (Blueprint $table) {
            $table->dropColumn('stated_relationship');
        });
    }
};
