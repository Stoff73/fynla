<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pension_input_history')) {
            return;
        }

        Schema::create('pension_input_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('tax_year', 9);
            $table->decimal('pension_input_amount', 12, 2);
            $table->timestamps();

            $table->unique(['user_id', 'tax_year']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pension_input_history');
    }
};
