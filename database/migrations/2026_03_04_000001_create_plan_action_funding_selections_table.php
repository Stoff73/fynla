<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_action_funding_selections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('plan_type', 20);
            $table->string('action_category', 50);
            $table->unsignedBigInteger('target_account_id')->default(0);
            $table->string('funding_source_type', 30);
            $table->unsignedBigInteger('funding_source_id');
            $table->timestamps();

            $table->unique(
                ['user_id', 'plan_type', 'action_category', 'target_account_id'],
                'plan_funding_user_plan_category_target_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_action_funding_selections');
    }
};
