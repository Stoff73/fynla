<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'annual_charitable_donations')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->decimal('annual_charitable_donations', 12, 2)->nullable()->after('annual_dividend_income');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'annual_charitable_donations')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('annual_charitable_donations');
        });
    }
};
