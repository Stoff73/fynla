<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('renewal_reminder_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->date('period_end_date');
            $table->timestamp('sent_at');

            $table->unique(['subscription_id', 'period_end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('renewal_reminder_log');
    }
};
