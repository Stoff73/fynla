<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_advice_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained('ai_conversations')->nullOnDelete();
            $table->unsignedBigInteger('message_id')->nullable();
            $table->string('query_type', 50)->index();
            $table->json('classification')->nullable();
            $table->json('kyc_status')->nullable();
            $table->json('recommendations')->nullable();
            $table->json('tools_called')->nullable();
            $table->json('user_data_snapshot')->nullable(); // snapshot of key user data at advice time
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'query_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_advice_logs');
    }
};
