<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('proposed_semantic_facts')) {
            return;
        }

        Schema::create('proposed_semantic_facts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('derived_from_conversation_id')->nullable()->constrained('ai_conversations')->nullOnDelete();
            $t->string('derived_from_episode_id')->nullable();
            $t->string('category', 32)->default('user_profile');
            $t->string('fact_id', 128);
            $t->string('title');
            $t->text('body');
            $t->date('valid_from')->nullable();
            $t->date('valid_to')->nullable();
            $t->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamps();

            $t->index(['status', 'created_at']);
            $t->index('user_id');
            $t->index(['user_id', 'fact_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposed_semantic_facts');
    }
};
