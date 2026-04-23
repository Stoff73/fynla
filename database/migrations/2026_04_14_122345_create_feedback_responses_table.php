<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('feedback_responses')) {
            return;
        }

        Schema::create('feedback_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('campaign', 50);
            $table->string('reason_code', 50);
            $table->text('free_text')->nullable();
            $table->timestamp('clicked_at');
            $table->timestamp('text_submitted_at')->nullable();

            $table->index(['user_id', 'campaign']);
            $table->index('reason_code');
            $table->index(['campaign', 'clicked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_responses');
    }
};
