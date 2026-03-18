<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates advisor_clients table to track advisor-client relationships,
     * review schedules, and relationship status.
     */
    public function up(): void
    {
        if (Schema::hasTable('advisor_clients')) {
            return;
        }

        Schema::create('advisor_clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advisor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['active', 'inactive', 'pending'])->default('active');
            $table->date('assigned_date');
            $table->date('last_review_date')->nullable();
            $table->date('next_review_due')->nullable();
            $table->unsignedTinyInteger('review_frequency_months')->default(12);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['advisor_id', 'client_id'], 'unique_advisor_client');
            $table->index(['advisor_id', 'status'], 'idx_advisor_status');
            $table->index('next_review_due', 'idx_next_review');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('advisor_clients');
    }
};
