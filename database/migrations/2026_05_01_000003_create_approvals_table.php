<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->enum('gate', ['gate_1_publish', 'gate_2_boost']);
            $table->timestamp('sent_at');
            $table->string('subject');
            $table->string('reply_token')->unique(); // used to match inbound replies
            $table->enum('status', ['pending', 'approved', 'rejected', 'amended', 'expired'])->default('pending');
            $table->timestamp('responded_at')->nullable();
            $table->string('response_command')->nullable(); // APPROVE / REJECT / AMEND xxx / BUDGET=N
            $table->text('response_raw')->nullable();
            $table->json('payload')->nullable(); // links / preview snapshots
            $table->timestamps();
            $table->index('status');
            $table->index(['article_id', 'gate']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approvals');
    }
};
