<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W-0202 — make "nobody has ever been asked" expressible. CSJ, 2026-08-24.
 *
 * `users.expenditure_sharing_mode` is `enum('joint','separate') NOT NULL DEFAULT
 * 'joint'`. Every row has had a value from the moment it was created, so
 * **joint-by-declaration and joint-by-never-having-been-asked are
 * indistinguishable**. Measured on dev the day this was written: 19 users, all
 * `joint`, none `separate`, 13 of them with a spouse. Every value is the default;
 * nobody has ever chosen.
 *
 * That matters because W-0202's decision was *"an unanswered question must not
 * become an answer"*, and its third branch — Fyn asks when no mode is recorded —
 * **could never fire**, because there was no such state. The schema had already
 * turned the unanswered question into an answer before Fyn saw it.
 *
 * **Why a companion timestamp rather than making the enum nullable.** Nullable
 * would carry the same meaning, and would also change what every existing reader
 * of that column receives — `SharedExpenditure::isShared()`, `UserResource`,
 * `OnboardingService`, the profile controller and the writer all read it today and
 * all treat it as always-present. This is additive: nothing that reads the mode
 * changes behaviour, the effective default is untouched, and the new column answers
 * a question the old one cannot — *was this chosen, and when*.
 *
 * **No backfill, deliberately.** NULL is the correct value for all 19 rows: none of
 * them was a declaration. Backfilling a timestamp would recreate the exact defect
 * this column exists to remove, one layer up.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('expenditure_sharing_mode_declared_at')
                ->nullable()
                ->after('expenditure_sharing_mode');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('expenditure_sharing_mode_declared_at');
        });
    }
};
