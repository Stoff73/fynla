<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_gamification')) {
            return;
        }

        Schema::create('user_gamification', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->unique();
            $table->unsignedInteger('total_points')->default(0);   // monotonic
            $table->unsignedTinyInteger('level')->default(1);
            $table->unsignedTinyInteger('pending_celebration_level')->nullable();
            $table->date('last_login_award_date')->nullable();
            $table->unsignedInteger('login_streak_days')->default(0);
            $table->date('streak_started_on')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_gamification');
    }
};
