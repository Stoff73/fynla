<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('native_device_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 20);
            $table->string('device_label', 80);
            $table->string('app_version', 32);
            $table->string('app_build', 32);
            $table->foreignId('current_access_token_id')
                ->nullable()
                ->constrained('personal_access_tokens')
                ->nullOnDelete();
            $table->timestamp('authenticated_at');
            $table->timestamp('absolute_expires_at');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoke_reason', 80)->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('absolute_expires_at');
            $table->index('revoked_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('native_device_sessions');
    }
};
