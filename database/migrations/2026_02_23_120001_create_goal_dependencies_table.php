<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goal_dependencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goal_id')->constrained('goals')->cascadeOnDelete();
            $table->foreignId('depends_on_goal_id')->constrained('goals')->cascadeOnDelete();
            $table->enum('dependency_type', ['blocks', 'funds', 'prerequisite'])
                ->default('prerequisite')
                ->comment('blocks: must complete first; funds: proceeds fund this goal; prerequisite: informational ordering');
            $table->text('notes')->nullable();
            $table->timestamps();

            // Prevent duplicate dependencies
            $table->unique(['goal_id', 'depends_on_goal_id'], 'goal_dep_unique');

            // Index for reverse lookups
            $table->index('depends_on_goal_id', 'goal_dep_reverse_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goal_dependencies');
    }
};
