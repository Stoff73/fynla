<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notification_preferences')) {
            return;
        }

        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('policy_renewals')->default(true);
            $table->boolean('goal_milestones')->default(true);
            $table->boolean('contribution_reminders')->default(true);
            $table->boolean('market_updates')->default(false);
            $table->boolean('fyn_daily_insight')->default(true);
            $table->boolean('security_alerts')->default(true);
            $table->boolean('payment_alerts')->default(true);
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
