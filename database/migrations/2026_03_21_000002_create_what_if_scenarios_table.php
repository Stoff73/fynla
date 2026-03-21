<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('what_if_scenarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->enum('scenario_type', ['retirement', 'property', 'family', 'income', 'custom'])->default('custom');
            $table->json('parameters');
            $table->json('affected_modules');
            $table->enum('created_via', ['ai_chat', 'manual'])->default('manual');
            $table->text('ai_narrative')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('what_if_scenarios');
    }
};
