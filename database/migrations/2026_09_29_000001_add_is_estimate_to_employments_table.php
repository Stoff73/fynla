<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks a job whose figure someone else gave: the income a user entered for
 * their spouse during onboarding, copied onto the spouse's account when it
 * links (SpouseHoldingTransfer). The spouse's own first job replaces that row
 * rather than joining it — production 2026-09-29 summed the two, £32,000 each,
 * into £64,000 (docs/testing/2026-09-29-prod-savetax-mobile-couple.md, C1).
 *
 * An unnamed row cannot carry this by itself: a person may give their own
 * salary without an employer, and a named second job must not overwrite it.
 * Existing rows default to false — every one was entered by its owner or
 * copied before this column existed, and none can be told apart afterwards.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employments', function (Blueprint $table) {
            $table->boolean('is_estimate')->default(false)->after('income_type');
        });
    }

    public function down(): void
    {
        Schema::table('employments', function (Blueprint $table) {
            $table->dropColumn('is_estimate');
        });
    }
};
