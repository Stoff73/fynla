<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COLUMNS = [
        'lifecycle_empty_trialer',
        'lifecycle_engaged_trialer',
        'lifecycle_cancelled_trialer',
        'lifecycle_churned_subscriber',
        'lifecycle_lapsed_subscriber',
    ];

    public function up(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            foreach (self::COLUMNS as $column) {
                if (! Schema::hasColumn('notification_preferences', $column)) {
                    $table->boolean($column)->default(true);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table) {
            foreach (self::COLUMNS as $column) {
                if (Schema::hasColumn('notification_preferences', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
