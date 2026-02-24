<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->integer('monthly_price');
            $table->integer('yearly_price');
            $table->integer('trial_days')->default(7);
            $table->boolean('is_active')->default(true);
            $table->json('features')->nullable();
            $table->integer('sort_order')->default(0);
            $table->string('revolut_plan_id')->nullable();
            $table->string('revolut_monthly_variation_id')->nullable();
            $table->string('revolut_yearly_variation_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
