<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_retention_email_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('day_number');
            $table->timestamp('sent_at');

            $table->unique(['subscription_id', 'day_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_retention_email_log');
    }
};
