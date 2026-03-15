<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('notification_preferences', 'estate_alerts')) {
            return;
        }

        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->boolean('estate_alerts')->default(true)->after('mortgage_rate_alerts');
        });
    }

    public function down(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->dropColumn('estate_alerts');
        });
    }
};
