<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('goal_savings_account')) {
            return;
        }

        Schema::create('goal_savings_account', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('savings_account_id')->constrained()->cascadeOnDelete();
            $table->decimal('allocated_amount', 15, 2)->default(0);
            $table->boolean('is_primary')->default(false);
            $table->integer('priority_rank')->default(0);
            $table->timestamps();

            $table->unique(['goal_id', 'savings_account_id']);
            $table->index('savings_account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goal_savings_account');
    }
};
