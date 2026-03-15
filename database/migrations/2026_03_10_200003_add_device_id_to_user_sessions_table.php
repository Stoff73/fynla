<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('user_sessions', 'device_id')) {
            return;
        }

        Schema::table('user_sessions', function (Blueprint $table) {
            $table->string('device_id', 255)->nullable()->after('device_name');
        });
    }

    public function down(): void
    {
        Schema::table('user_sessions', function (Blueprint $table) {
            $table->dropColumn('device_id');
        });
    }
};
