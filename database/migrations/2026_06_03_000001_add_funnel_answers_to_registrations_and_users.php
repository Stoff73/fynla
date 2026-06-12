<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Carry the /savetax acquisition-funnel answers (employment, income band,
 * spouse, spouse income, assets) from the public funnel through registration
 * onto the user, so onboarding/Fyn and the dashboard can start from the user's
 * real responses instead of a demo persona. One JSON column mirrors the
 * existing signup_source pattern; the funnel set is coarse acquisition data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pending_registrations', function (Blueprint $table) {
            $table->json('funnel_answers')->nullable()->after('signup_source');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->json('funnel_answers')->nullable()->after('signup_source');
        });
    }

    public function down(): void
    {
        Schema::table('pending_registrations', function (Blueprint $table) {
            $table->dropColumn('funnel_answers');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('funnel_answers');
        });
    }
};
