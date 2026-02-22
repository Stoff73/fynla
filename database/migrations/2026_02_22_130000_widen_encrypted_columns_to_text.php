<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Widen columns that hold Crypt::encryptString() values.
     * Encrypted strings are ~200 chars, too large for varchar(10/13)
     * and tight for varchar(255).
     */
    public function up(): void
    {
        Schema::table('family_members', function (Blueprint $table) {
            $table->text('national_insurance_number')->nullable()->change();
        });

        Schema::table('cash_accounts', function (Blueprint $table) {
            $table->text('sort_code')->nullable()->change();
            $table->text('account_number')->nullable()->change();
        });

        Schema::table('investment_accounts', function (Blueprint $table) {
            $table->text('account_number')->nullable()->change();
        });

        Schema::table('mortgages', function (Blueprint $table) {
            $table->text('mortgage_account_number')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('family_members', function (Blueprint $table) {
            $table->string('national_insurance_number', 13)->nullable()->change();
        });

        Schema::table('cash_accounts', function (Blueprint $table) {
            $table->string('sort_code', 10)->nullable()->change();
            $table->string('account_number', 255)->nullable()->change();
        });

        Schema::table('investment_accounts', function (Blueprint $table) {
            $table->string('account_number', 255)->nullable()->change();
        });

        Schema::table('mortgages', function (Blueprint $table) {
            $table->string('mortgage_account_number', 255)->nullable()->change();
        });
    }
};
