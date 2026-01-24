<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('goal_contributions')) {
            return;
        }

        Schema::create('goal_contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goal_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            $table->decimal('amount', 12, 2);
            $table->date('contribution_date');
            $table->enum('contribution_type', ['manual', 'automatic', 'lump_sum', 'interest', 'adjustment']);
            $table->text('notes')->nullable();

            $table->decimal('goal_balance_after', 15, 2);
            $table->boolean('streak_qualifying')->default(true);

            $table->timestamps();

            $table->index(['goal_id', 'contribution_date']);
            $table->index(['user_id', 'contribution_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goal_contributions');
    }
};
