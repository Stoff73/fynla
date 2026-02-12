<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trial_reminder_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('days_remaining');
            $table->timestamp('sent_at');

            $table->unique(['user_id', 'days_remaining']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trial_reminder_log');
    }
};
