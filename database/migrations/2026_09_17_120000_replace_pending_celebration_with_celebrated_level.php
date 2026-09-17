<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The celebration was a single pending level, so a user who crossed three
 * levels at once could only ever be shown the last. It becomes "the highest
 * level already celebrated", and what is owed is the range above it.
 *
 * The backfill is the whole point of the up() ordering: every existing user
 * is marked as already celebrated to their current level. Without it, a live
 * user at level 6 gets a five-step climb on their next dashboard view.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_gamification', function (Blueprint $table): void {
            $table->unsignedInteger('celebrated_level')->nullable()->after('level');
        });

        DB::table('user_gamification')->update(['celebrated_level' => DB::raw('`level`')]);

        Schema::table('user_gamification', function (Blueprint $table): void {
            $table->dropColumn('pending_celebration_level');
        });
    }

    public function down(): void
    {
        Schema::table('user_gamification', function (Blueprint $table): void {
            $table->unsignedInteger('pending_celebration_level')->nullable()->after('level');
        });

        Schema::table('user_gamification', function (Blueprint $table): void {
            $table->dropColumn('celebrated_level');
        });
    }
};
