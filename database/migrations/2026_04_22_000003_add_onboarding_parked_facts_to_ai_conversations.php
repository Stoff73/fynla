<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->json('onboarding_parked_facts')
                ->nullable()
                ->after('persona_state')
                ->comment('OnboardingFactExtractor output: speculative facts extracted per user turn, consumed by OnboardingChatDirector state handlers. Writes to backing records (users.*, family_members) happen only at state commit points, not at parking.');
        });
    }

    public function down(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->dropColumn('onboarding_parked_facts');
        });
    }
};
