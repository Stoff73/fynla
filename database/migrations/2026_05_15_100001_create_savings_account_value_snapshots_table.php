<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('savings_account_value_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('savings_account_id')->constrained('savings_accounts')->cascadeOnDelete();
            $table->string('column_name', 64);                 // balance_gbp | annual_interest_projected_gbp | ...
            $table->decimal('value', 14, 2);
            $table->char('currency', 3)->default('GBP');
            $table->decimal('value_gbp', 14, 2)->nullable();
            $table->timestamp('taken_at');
            $table->string('trigger_reason', 64);              // 'create' | 'update' | 'recalc_daily'
            $table->string('ingest_source', 16);               // mirrors IngestSource enum
            $table->timestamps();
            $table->index(['savings_account_id', 'column_name', 'taken_at'], 'savings_acct_snap_acct_col_taken_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('savings_account_value_snapshots');
    }
};
