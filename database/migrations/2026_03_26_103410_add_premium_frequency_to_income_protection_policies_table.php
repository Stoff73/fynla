<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('income_protection_policies', function (Blueprint $table) {
            if (! Schema::hasColumn('income_protection_policies', 'premium_frequency')) {
                $table->string('premium_frequency', 20)->default('monthly')->after('premium_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('income_protection_policies', function (Blueprint $table) {
            $table->dropColumn('premium_frequency');
        });
    }
};
