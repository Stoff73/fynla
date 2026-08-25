<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('native_refresh_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('native_device_session_id')
                ->constrained('native_device_sessions')
                ->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(
                ['native_device_session_id', 'expires_at'],
                'native_refresh_session_expiry_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('native_refresh_tokens');
    }
};
