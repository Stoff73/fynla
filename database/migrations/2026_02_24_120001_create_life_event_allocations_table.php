<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('life_event_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('life_event_id')->constrained('life_events')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('allocation_type', ['income', 'expense'])->default('income');
            $table->enum('allocation_step', ['goals', 'isa', 'pension', 'bond', 'cash']);
            $table->string('account_type', 50)->nullable();
            $table->unsignedBigInteger('account_id')->nullable();
            $table->string('account_label', 100)->nullable();
            $table->decimal('suggested_amount', 15, 2)->default(0);
            $table->decimal('amount', 15, 2)->default(0);
            $table->boolean('enabled')->default(true);
            $table->text('rationale')->nullable();
            $table->tinyInteger('display_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('life_event_id');
            $table->index(['user_id', 'life_event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('life_event_allocations');
    }
};
