<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('net_worth_forecast_assumptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('property', 6, 3)->nullable();
            $table->decimal('investments', 6, 3)->nullable();
            $table->decimal('pensions', 6, 3)->nullable();
            $table->decimal('cash', 6, 3)->nullable();
            $table->decimal('business', 6, 3)->nullable();
            $table->decimal('valuables', 6, 3)->nullable();
            $table->decimal('mortgages', 6, 3)->nullable();
            $table->decimal('other_liabilities', 6, 3)->nullable();
            $table->enum('basis', ['nominal', 'real'])->default('nominal');
            $table->date('effective_from');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('net_worth_forecast_assumptions');
    }
};
