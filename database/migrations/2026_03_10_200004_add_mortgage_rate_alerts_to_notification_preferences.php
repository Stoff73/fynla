<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('notification_preferences', 'mortgage_rate_alerts')) {
            return;
        }

        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->boolean('mortgage_rate_alerts')->default(true)->after('payment_alerts');
        });
    }

    public function down(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->dropColumn('mortgage_rate_alerts');
        });
    }
};
