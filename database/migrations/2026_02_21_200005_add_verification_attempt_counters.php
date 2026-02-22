<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pending_registrations', function (Blueprint $table) {
            $table->unsignedTinyInteger('verification_attempts')->default(0)->after('verification_code');
        });

        Schema::table('email_verification_codes', function (Blueprint $table) {
            $table->unsignedTinyInteger('failed_attempts')->default(0)->after('resend_count');
        });
    }

    public function down(): void
    {
        Schema::table('pending_registrations', function (Blueprint $table) {
            $table->dropColumn('verification_attempts');
        });

        Schema::table('email_verification_codes', function (Blueprint $table) {
            $table->dropColumn('failed_attempts');
        });
    }
};
