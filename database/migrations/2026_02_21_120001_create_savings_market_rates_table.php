<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('savings_market_rates', function (Blueprint $table) {
            $table->id();
            $table->string('rate_key');
            $table->string('label');
            $table->decimal('rate', 5, 4);
            $table->string('tax_year');
            $table->date('effective_from');
            $table->timestamps();
            $table->unique(['rate_key', 'tax_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('savings_market_rates');
    }
};
