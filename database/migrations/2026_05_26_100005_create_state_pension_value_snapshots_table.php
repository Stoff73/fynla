<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('state_pension_value_snapshots')) {
            return;
        }

        Schema::create('state_pension_value_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('state_pension_id')->constrained('state_pensions')->cascadeOnDelete();
            $table->string('column_name', 64);
            $table->decimal('value', 16, 2);
            $table->char('currency', 3)->default('GBP');
            $table->decimal('value_gbp', 16, 2)->nullable();
            $table->timestamp('taken_at');
            $table->string('trigger_reason', 64);
            $table->string('ingest_source', 16);
            $table->timestamps();
            $table->index(['state_pension_id', 'column_name', 'taken_at'], 'spvs_id_column_taken_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('state_pension_value_snapshots');
    }
};
