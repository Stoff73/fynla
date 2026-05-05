<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'signup_source')) {
            Schema::table('users', function (Blueprint $table): void {
                // Marketing-channel attribution captured from utm_source on
                // landing-page URL (e.g. ?utm_source=linkedin). Allowlist
                // enforced at write time — see Auth\RegisterController.
                $table->string('signup_source', 32)->nullable()->after('referral_code');
                $table->index('signup_source', 'users_signup_source_idx');
            });
        }

        if (Schema::hasTable('pending_registrations') && ! Schema::hasColumn('pending_registrations', 'signup_source')) {
            Schema::table('pending_registrations', function (Blueprint $table): void {
                $table->string('signup_source', 32)->nullable()->after('referral_code');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'signup_source')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropIndex('users_signup_source_idx');
                $table->dropColumn('signup_source');
            });
        }

        if (Schema::hasTable('pending_registrations') && Schema::hasColumn('pending_registrations', 'signup_source')) {
            Schema::table('pending_registrations', function (Blueprint $table): void {
                $table->dropColumn('signup_source');
            });
        }
    }
};
