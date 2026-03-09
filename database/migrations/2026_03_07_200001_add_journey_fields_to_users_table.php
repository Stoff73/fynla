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
            $table->json('journey_states')->nullable()->default(null)->after('onboarding_asset_flags');
            $table->json('journey_selections')->nullable()->default(null)->after('journey_states');
            $table->json('dismissed_prompts')->nullable()->default(null)->after('journey_selections');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['journey_states', 'journey_selections', 'dismissed_prompts']);
        });
    }
};
