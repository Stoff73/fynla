<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('db_pension_value_snapshots')) {
            return;
        }

        Schema::create('db_pension_value_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('db_pension_id')->constrained('db_pensions')->cascadeOnDelete();
            $table->string('column_name', 64);
            $table->decimal('value', 16, 2);
            $table->char('currency', 3)->default('GBP');
            $table->decimal('value_gbp', 16, 2)->nullable();
            $table->timestamp('taken_at');
            $table->string('trigger_reason', 64);
            $table->string('ingest_source', 16);
            $table->timestamps();
            $table->index(['db_pension_id', 'column_name', 'taken_at'], 'dbpvs_id_column_taken_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('db_pension_value_snapshots');
    }
};
