<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W-0368 — keep the fact the user already tells us.
 *
 * Whether a property's co-owner is the user's spouse decides an Inheritance Tax
 * valuation: IHTA 1984 s161 values related property together, which removes the
 * restricted marketability an undivided-share discount pays for under s160.
 *
 * **The form already asks.** `PropertyForm.vue` offers "<name> (Spouse)" and
 * "Other (Enter Name)" as distinct choices, and offers the spouse option even when
 * the spouse has no account. But `handleJointOwnerSelection()` wrote only the name,
 * so the distinction was discarded one line after the user made it — and
 * `populateForm()` reconstructed any named co-owner as "Other", so re-saving
 * silently converted a spouse into a third party.
 *
 * **Nullable, deliberately, and this is the load-bearing part.**
 * `database/CLAUDE.md` records the `expenditure_sharing_mode` case: a NOT NULL
 * DEFAULT made "never asked" indistinguishable from "chose this", and 19 users all
 * read as having chosen when none had. Here NULL means **we have not asked**, and
 * the valuation treats it as such — no discount until someone says, which
 * overstates tax rather than understating it.
 *
 * **Why this is stored rather than inferred.** Both available heuristics fail on
 * the live data, measured before this was written:
 *
 *   - `marital_status` — the one property whose co-owner is named "wife" belongs to
 *     a user marked `single`, so the status would miss it; and it would wrongly
 *     refuse the discount to a `married` user co-owning with "Mike Jones".
 *   - name matching — "wife" matches spousal vocabulary, "GLW" does not, and
 *     initials could perfectly well be a spouse.
 *
 * Three rows were enough to rule out both. Do not reintroduce either.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('properties', 'joint_owner_is_spouse')) {
            return;
        }

        Schema::table('properties', function (Blueprint $table) {
            $table->boolean('joint_owner_is_spouse')
                ->nullable()
                ->after('joint_owner_name')
                ->comment('W-0368: is the co-owner the user\'s spouse (IHTA 1984 s161)? NULL = never asked, and the Inheritance Tax valuation treats it as unknown rather than as "no".');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('properties', 'joint_owner_is_spouse')) {
            return;
        }

        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('joint_owner_is_spouse');
        });
    }
};
