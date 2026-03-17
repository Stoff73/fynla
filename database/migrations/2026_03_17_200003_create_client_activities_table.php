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
     * Creates client_activities table to log advisor interactions with clients
     * including emails, calls, meetings, suitability reports, and follow-ups.
     */
    public function up(): void
    {
        if (Schema::hasTable('client_activities')) {
            return;
        }

        Schema::create('client_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advisor_client_id')->constrained('advisor_clients')->cascadeOnDelete();
            $table->foreignId('advisor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->enum('activity_type', ['email', 'phone', 'meeting', 'letter', 'suitability_report', 'review', 'note']);
            $table->string('summary', 500);
            $table->text('details')->nullable();
            $table->dateTime('activity_date');
            $table->date('follow_up_date')->nullable();
            $table->boolean('follow_up_completed')->default(false);
            $table->string('report_type', 100)->nullable();
            $table->date('report_sent_date')->nullable();
            $table->date('report_acknowledged_date')->nullable();
            $table->timestamps();

            $table->index('advisor_client_id', 'idx_advisor_client_id');
            $table->index(['advisor_id', 'client_id'], 'idx_advisor_client');
            $table->index('activity_type', 'idx_activity_type');
            $table->index('activity_date', 'idx_activity_date');
            $table->index(['follow_up_date', 'follow_up_completed'], 'idx_follow_up');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_activities');
    }
};
