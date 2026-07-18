<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('apple_notification_logs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('notification_uuid')->unique();
            $table->enum('environment', ['sandbox', 'production']);
            $table->string('notification_type', 64);
            $table->string('subtype', 64)->nullable();
            $table->char('signed_payload_sha256', 64);
            $table->enum('status', ['received', 'processed', 'duplicate', 'rejected', 'failed']);
            $table->string('error_code', 80)->nullable();
            $table->dateTime('processed_at')->nullable();
            $table->timestamps();

            $table->index('created_at');
            $table->index(['status', 'created_at'], 'apple_notification_logs_status_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('apple_notification_logs');
    }
};
