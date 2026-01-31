<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds include_in_retirement flag to savings_accounts table.
     * This allows users to explicitly include/exclude savings accounts
     * from retirement income planning calculations.
     *
     * Default is false - savings accounts must be explicitly included.
     */
    public function up(): void
    {
        Schema::table('savings_accounts', function (Blueprint $table) {
            $table->boolean('include_in_retirement')->default(false)->after('beneficiary_dob');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('savings_accounts', function (Blueprint $table) {
            $table->dropColumn('include_in_retirement');
        });
    }
};
