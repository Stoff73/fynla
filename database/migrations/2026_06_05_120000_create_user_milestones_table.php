<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_milestones')) {
            return;
        }

        Schema::create('user_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // 'net_worth' | 'goal'
            $table->string('milestone_type', 32);
            // goal id for goal milestones; null for net-worth milestones
            $table->unsignedBigInteger('reference_id')->nullable();
            // the crossed threshold: a £ value (net_worth) or a percentage (goal)
            $table->decimal('threshold', 15, 2);
            $table->timestamp('achieved_at');
            $table->timestamp('shared_at')->nullable();
            $table->timestamps();

            // Each milestone fires exactly once per user.
            $table->unique(['user_id', 'milestone_type', 'reference_id', 'threshold'], 'user_milestones_unique');
            $table->index(['user_id', 'milestone_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_milestones');
    }
};
