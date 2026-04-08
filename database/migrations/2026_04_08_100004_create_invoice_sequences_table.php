<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('invoice_sequences')) {
            return;
        }

        Schema::create('invoice_sequences', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('next_value')->default(1);
        });

        DB::table('invoice_sequences')->insert(['next_value' => 1]);
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_sequences');
    }
};
