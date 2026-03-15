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
        Schema::table('email_verification_codes', function (Blueprint $table) {
            if (! Schema::hasColumn('email_verification_codes', 'challenge_token')) {
                $table->string('challenge_token', 64)->nullable()->after('type');
                $table->index('challenge_token');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_verification_codes', function (Blueprint $table) {
            if (Schema::hasColumn('email_verification_codes', 'challenge_token')) {
                $table->dropIndex(['challenge_token']);
                $table->dropColumn('challenge_token');
            }
        });
    }
};
