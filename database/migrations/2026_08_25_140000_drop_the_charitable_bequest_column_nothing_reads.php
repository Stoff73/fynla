<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W-0221 — a write-only column with a live endpoint.
 *
 * `users.charitable_bequest` was read by nothing. W-0132 removed the last two
 * readers (the estate toggle's client-side model and the family card), and whether
 * a user leaves anything to charity is now answered from the recorded bequests on
 * their will — the instrument, rather than a boolean beside it.
 *
 * **It could still be written**, which is worse than merely unused: the endpoint
 * accepted the field, returned success, and discarded it. Two paths reached it —
 * `UpdatePersonalInfoRequest` (named on the item) and
 * `OnboardingService::processFamilyInfo()` (found while sweeping for this one).
 * Both are closed in the same change as this drop, because a column dropped while
 * its endpoint still accepts the field trades a silent discard for a 500.
 *
 * The real risk was regrowth. The next feature wanting an answer about charity
 * would have found a column with a plausible name, a cast and a working endpoint,
 * and read it — reintroducing the fourth mechanism W-0132 had just removed.
 *
 * NOT touched, despite sharing the name: `EstateAgent:826,868` use the string
 * `'charitable_bequest'` as a recommendation CATEGORY label, as do
 * `EstateRecommendationAdapter`, `RecommendationPersonaliser` and
 * `EstatePlanService`. Different thing, same name.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'charitable_bequest')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('charitable_bequest');
        });
    }

    /**
     * Restores the column, not its contents. Nothing read it, so nothing is
     * recomputed from it — a rollback gets the shape back and every row NULL.
     */
    public function down(): void
    {
        if (Schema::hasColumn('users', 'charitable_bequest')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('charitable_bequest')->nullable();
        });
    }
};
