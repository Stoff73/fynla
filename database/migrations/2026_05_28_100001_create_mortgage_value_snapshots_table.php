<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mortgage_value_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mortgage_id')->constrained('mortgages')->cascadeOnDelete();
            $table->string('snapshot_type', 32);  // 'mortgageBalance' or 'mortgageRate'
            $table->decimal('value', 15, 4);       // store balance or rate per snapshot_type
            $table->timestamp('snapshotted_at');
            $table->timestamps();
            $table->index(['mortgage_id', 'snapshot_type', 'snapshotted_at'], 'mvs_mortgage_type_snapshotted_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mortgage_value_snapshots');
    }
};
