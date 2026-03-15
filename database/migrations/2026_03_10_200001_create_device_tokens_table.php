<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('device_tokens')) {
            return;
        }

        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('device_token', 500);
            $table->string('device_id', 255);
            $table->enum('platform', ['ios', 'android']);
            $table->string('device_name', 255)->nullable();
            $table->string('app_version', 20)->nullable();
            $table->string('os_version', 50)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'device_id']);
            $table->index('device_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};
