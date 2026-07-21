<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('property_value_snapshots')) {
            return;
        }

        Schema::create('property_value_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('column_name', 64);
            $table->decimal('value', 15, 2);
            $table->string('currency', 3)->default('GBP');
            $table->decimal('value_gbp', 15, 2);
            $table->timestamp('taken_at');
            $table->string('trigger_reason', 16);  // 'create' | 'update'
            $table->string('ingest_source', 16);

            // MySQL 64-char identifier limit — use a short explicit index name.
            $table->index(['property_id', 'column_name', 'taken_at'], 'pvs_id_column_taken_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_value_snapshots');
    }
};
