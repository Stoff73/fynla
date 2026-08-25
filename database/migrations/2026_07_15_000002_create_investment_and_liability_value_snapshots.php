<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('investment_account_value_snapshots')) {
            Schema::create('investment_account_value_snapshots', function (Blueprint $table) {
                $table->id();
                $table->foreignId('investment_account_id')->constrained('investment_accounts')->cascadeOnDelete();
                $table->string('column_name', 64);
                $table->decimal('value', 16, 2);
                $table->char('currency', 3)->default('GBP');
                $table->decimal('value_gbp', 16, 2);
                $table->timestamp('taken_at');
                $table->string('trigger_reason', 64);
                $table->string('ingest_source', 16);
                $table->timestamps();
                $table->index(
                    ['investment_account_id', 'column_name', 'taken_at'],
                    'iavs_id_column_taken_idx'
                );
            });
        }

        if (! Schema::hasTable('liability_value_snapshots')) {
            Schema::create('liability_value_snapshots', function (Blueprint $table) {
                $table->id();
                $table->foreignId('liability_id')->constrained('liabilities')->cascadeOnDelete();
                $table->string('column_name', 64);
                $table->decimal('value', 16, 2);
                $table->char('currency', 3)->default('GBP');
                $table->decimal('value_gbp', 16, 2);
                $table->timestamp('taken_at');
                $table->string('trigger_reason', 64);
                $table->string('ingest_source', 16);
                $table->timestamps();
                $table->index(
                    ['liability_id', 'column_name', 'taken_at'],
                    'lvs_id_column_taken_idx'
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('liability_value_snapshots');
        Schema::dropIfExists('investment_account_value_snapshots');
    }
};
