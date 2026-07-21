<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table): void {
            $table->text('summary')->nullable()->after('onboarding_parked_facts');
            $table->json('topics')->nullable()->after('summary');
            $table->json('entities_mentioned')->nullable()->after('topics');
            $table->json('intents_stated')->nullable()->after('entities_mentioned');
            $table->timestamp('summarised_at')->nullable()->after('intents_stated');
            $table->index('summarised_at');
        });
    }

    public function down(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table): void {
            $table->dropIndex(['summarised_at']);
            $table->dropColumn(['summary', 'topics', 'entities_mentioned', 'intents_stated', 'summarised_at']);
        });
    }
};
