<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('proposed_procedure_amendments')) {
            return;
        }

        Schema::create('proposed_procedure_amendments', function (Blueprint $t) {
            $t->id();
            $t->string('procedure_id', 128);
            $t->text('problem_observed');
            $t->text('proposed_fix');
            $t->text('rationale')->nullable();
            $t->string('failure_type', 64)->nullable();
            $t->foreignId('conversation_id')->nullable()->constrained('ai_conversations')->nullOnDelete();
            $t->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamps();

            $t->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposed_procedure_amendments');
    }
};
