<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('currency_rates')) {
            return;
        }

        Schema::create('currency_rates', function (Blueprint $table) {
            $table->id();
            $table->char('from_ccy', 3);             // ISO 4217, e.g. 'GBP'
            $table->char('to_ccy', 3);               // ISO 4217, e.g. 'EUR'
            $table->decimal('rate', 18, 8);          // 1 from_ccy = rate to_ccy
            $table->dateTime('effective_at');         // when this rate became applicable
            $table->string('source', 64)->default('manual'); // 'manual' | 'feed:ecb' | etc.
            $table->timestamps();
            $table->index(['from_ccy', 'to_ccy', 'effective_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currency_rates');
    }
};
