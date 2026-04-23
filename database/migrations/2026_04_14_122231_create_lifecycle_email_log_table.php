<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lifecycle_email_log')) {
            return;
        }

        Schema::create('lifecycle_email_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('campaign', 50);
            $table->timestamp('sent_at');
            $table->timestamp('clicked_at')->nullable();
            $table->string('action_taken', 50)->nullable();
            $table->json('context')->nullable();

            $table->unique(['user_id', 'campaign']);
            $table->index(['campaign', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lifecycle_email_log');
    }
};
