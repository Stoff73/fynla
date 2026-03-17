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
            $table->string('life_stage', 20)->nullable()->after('journey_states');
            $table->json('life_stage_completed_steps')->nullable()->after('life_stage');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['life_stage', 'life_stage_completed_steps']);
        });
    }
};
