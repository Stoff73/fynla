<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Campaign re-entry marker for users who already completed
            // onboarding (map §4). Null = no campaign in flight. Read by the
            // sendMessage dispatch guard every message — a column, not a JSON
            // context key, deliberately.
            $table->string('active_campaign', 32)->nullable()->after('onboarding_fyn_context');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('active_campaign');
        });
    }
};
