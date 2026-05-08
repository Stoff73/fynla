<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('account_deletion_reminder_log')) {
            return;
        }

        Schema::create('account_deletion_reminder_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('days_remaining'); // 7 or 1
            $table->timestamp('sent_at');
            $table->index(['user_id', 'days_remaining']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_deletion_reminder_log');
    }
};
