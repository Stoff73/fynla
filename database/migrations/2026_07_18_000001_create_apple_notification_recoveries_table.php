<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('apple_notification_recoveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('apple_notification_log_id')
                ->constrained('apple_notification_logs')
                ->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('original_transaction_id', 191);
            $table->enum('status', ['pending', 'failed', 'processed', 'rejected']);
            $table->string('error_code', 80)->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->dateTime('next_attempt_at')->nullable();
            $table->dateTime('last_attempted_at')->nullable();
            $table->dateTime('processed_at')->nullable();
            $table->timestamps();

            $table->unique(
                'apple_notification_log_id',
                'apple_notification_recoveries_notification_unique',
            );
            $table->index(
                ['status', 'next_attempt_at', 'id'],
                'apple_notification_recoveries_due_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('apple_notification_recoveries');
    }
};
