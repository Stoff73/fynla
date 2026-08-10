<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('isa_contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('account_type');
            $table->unsignedBigInteger('account_id');
            $table->string('tax_year', 7);
            $table->date('contribution_date')->nullable();
            $table->string('entry_type', 32)->default('annual_summary');
            $table->decimal('amount', 15, 2);
            $table->string('source', 32);
            $table->string('provenance', 64);
            $table->timestamps();

            $table->index(['user_id', 'tax_year']);
            $table->index(
                ['account_type', 'account_id', 'tax_year', 'entry_type'],
                'isa_contributions_account_year_type_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('isa_contributions');
    }
};
