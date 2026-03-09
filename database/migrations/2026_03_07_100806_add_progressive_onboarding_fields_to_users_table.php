<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'onboarding_mode')) {
                $table->enum('onboarding_mode', ['quick', 'full'])->nullable()->after('onboarding_completed_at');
            }
            if (! Schema::hasColumn('users', 'onboarding_asset_flags')) {
                $table->json('onboarding_asset_flags')->nullable()->after('onboarding_mode');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'onboarding_mode')) {
                $table->dropColumn('onboarding_mode');
            }
            if (Schema::hasColumn('users', 'onboarding_asset_flags')) {
                $table->dropColumn('onboarding_asset_flags');
            }
        });
    }
};
