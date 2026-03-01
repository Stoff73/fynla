<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('version')->default('1.0');
            $table->json('config_data');
            $table->boolean('is_active')->default(false);
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_configurations');
    }
};
