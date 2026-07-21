<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('point_awards')) {
            return;
        }

        Schema::create('point_awards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // data | onboarding | milestone | recommendation | login | streak
            $table->string('source_type', 32);
            $table->unsignedInteger('points');
            $table->string('dedup_key', 191);
            $table->json('meta')->nullable();
            $table->timestamps();

            // The single-award guarantee: a given dedup_key awards exactly once per user.
            $table->unique(['user_id', 'dedup_key'], 'point_awards_unique');
            $table->index(['user_id', 'source_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('point_awards');
    }
};
