<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->integer('failed_login_count')->default(0)->after('mfa_confirmed_at');
            $table->timestamp('locked_until')->nullable()->after('failed_login_count');
            $table->timestamp('last_failed_login_at')->nullable()->after('locked_until');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'failed_login_count',
                'locked_until',
                'last_failed_login_at',
            ]);
        });
    }
};
