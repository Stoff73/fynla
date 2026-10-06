<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Item 8a (CSJ 2026-10-06): one home for smoking and health, on `users`.
 *
 * - `users.smoking_status` was NOT NULL DEFAULT 'never' and `health_status`
 *   DEFAULT 'yes', so a user never asked read as a non-smoker in good health.
 *   Both become nullable with no default: null is "not answered".
 * - Every real user still holding both defaults is reset to not answered
 *   ("agree, resett all users", CSJ 2026-10-06); nobody can tell which of them
 *   chose the defaults. Users who gave any other answer keep it. Preview
 *   personas are seeded from their JSON and left alone.
 * - `protection_profiles.smoker_status` / `health_status` had no input on any
 *   surface (every production row held its defaults, 0 / 'good'), and are
 *   dropped so a second home cannot come back. Nothing was derived from them.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `users` MODIFY `smoking_status` ENUM('never','quit_recent','quit_long_ago','yes') NULL DEFAULT NULL");
        DB::statement("ALTER TABLE `users` MODIFY `health_status` ENUM('yes','yes_previous','no_previous','no_existing','no_both') NULL DEFAULT NULL");

        $resetIds = DB::table('users')
            ->where('is_preview_user', false)
            ->where('smoking_status', 'never')
            ->where('health_status', 'yes')
            ->pluck('id')
            ->all();

        if ($resetIds !== []) {
            DB::table('users')->whereIn('id', $resetIds)->update([
                'smoking_status' => null,
                'health_status' => null,
            ]);
        }

        Log::info('[migration] smoking and health on the defaults reset to not answered', [
            'count' => count($resetIds),
            'user_ids' => $resetIds,
        ]);

        foreach (['smoker_status', 'health_status'] as $column) {
            if (Schema::hasColumn('protection_profiles', $column)) {
                Schema::table('protection_profiles', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('protection_profiles', 'smoker_status')) {
            Schema::table('protection_profiles', function (Blueprint $table) {
                $table->boolean('smoker_status')->default(false)->after('occupation');
            });
        }
        if (! Schema::hasColumn('protection_profiles', 'health_status')) {
            Schema::table('protection_profiles', function (Blueprint $table) {
                $table->string('health_status')->default('good')->after('employer_benefits_recorded_at');
            });
        }

        DB::table('users')->whereNull('smoking_status')->update(['smoking_status' => 'never']);
        DB::statement("ALTER TABLE `users` MODIFY `smoking_status` ENUM('never','quit_recent','quit_long_ago','yes') NOT NULL DEFAULT 'never'");
        DB::statement("ALTER TABLE `users` MODIFY `health_status` ENUM('yes','yes_previous','no_previous','no_existing','no_both') NULL DEFAULT 'yes'");
    }
};
