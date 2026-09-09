<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The legacy plan catalogue (student / standard / family / pro) is dead since
 * the 2026-09-08 collapse to Free and Premium: no user, subscription, payment
 * or invoice carries those slugs, pricing comes from tier_configurations, and
 * renewals bill the amount locked on the subscription row. CSJ 2026-09-09:
 * remove it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('subscription_plans');
    }

    public function down(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->integer('monthly_price');
            $table->integer('yearly_price');
            $table->boolean('is_active')->default(true);
            $table->json('features')->nullable();
            $table->integer('sort_order')->default(0);
            $table->string('revolut_plan_id')->nullable();
            $table->string('revolut_monthly_variation_id')->nullable();
            $table->string('revolut_yearly_variation_id')->nullable();
            $table->integer('launch_monthly_price')->nullable();
            $table->integer('launch_yearly_price')->nullable();
            $table->timestamps();
        });
    }
};
