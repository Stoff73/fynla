<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('discount_code_usages')) {
            return;
        }

        Schema::create('discount_code_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discount_code_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('applied_at');
            $table->timestamps();

            $table->index(['discount_code_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_code_usages');
    }
};
