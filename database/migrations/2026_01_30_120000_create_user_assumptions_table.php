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
        Schema::create('user_assumptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('assumption_type', ['pensions', 'investments']);
            $table->decimal('inflation_rate', 5, 2)->nullable();
            $table->decimal('return_rate', 5, 2)->nullable();
            $table->integer('compound_periods')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'assumption_type']);
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_assumptions');
    }
};
