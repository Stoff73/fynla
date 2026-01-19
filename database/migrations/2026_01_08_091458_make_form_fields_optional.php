<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add sensible defaults to NOT NULL columns to allow forms to be saved with empty data.
     */
    public function up(): void
    {
        // Mortgages table - add defaults for NOT NULL columns
        Schema::table('mortgages', function (Blueprint $table) {
            $table->decimal('outstanding_balance', 15, 2)->default(0)->change();
            $table->integer('remaining_term_months')->default(0)->change();
        });

        // For enum columns, we need raw SQL to add default
        DB::statement("ALTER TABLE mortgages MODIFY COLUMN rate_type ENUM('fixed','variable','tracker','discount','mixed') NOT NULL DEFAULT 'fixed'");

        // Family members table - add defaults
        Schema::table('family_members', function (Blueprint $table) {
            $table->string('name')->default('Unknown')->change();
        });

        // For relationship enum
        DB::statement("ALTER TABLE family_members MODIFY COLUMN relationship ENUM('spouse','child','parent','other_dependent') NOT NULL DEFAULT 'other_dependent'");

        // Business interests table - make nullable or add defaults
        Schema::table('business_interests', function (Blueprint $table) {
            $table->string('business_name')->nullable()->change();
            $table->decimal('current_valuation', 15, 2)->default(0)->change();
            $table->date('valuation_date')->nullable()->change();
        });

        // For business_type enum
        DB::statement("ALTER TABLE business_interests MODIFY COLUMN business_type ENUM('sole_trader','partnership','limited_company','llp','other') NOT NULL DEFAULT 'other'");

        // Chattels table - make nullable or add defaults
        Schema::table('chattels', function (Blueprint $table) {
            $table->string('name')->nullable()->change();
            $table->decimal('current_value', 15, 2)->default(0)->change();
            $table->date('valuation_date')->nullable()->change();
        });

        // For chattel_type enum
        DB::statement("ALTER TABLE chattels MODIFY COLUMN chattel_type ENUM('vehicle','art','antique','jewelry','collectible','other') NOT NULL DEFAULT 'other'");

        // Gifts table - make nullable or add defaults
        Schema::table('gifts', function (Blueprint $table) {
            $table->date('gift_date')->nullable()->change();
            $table->string('recipient')->nullable()->change();
            $table->decimal('gift_value', 15, 2)->default(0)->change();
        });

        // For gift_type enum
        DB::statement("ALTER TABLE gifts MODIFY COLUMN gift_type ENUM('pet','clt','exempt','small_gift','annual_exemption') NOT NULL DEFAULT 'exempt'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Mortgages table - remove defaults (keep NOT NULL)
        Schema::table('mortgages', function (Blueprint $table) {
            $table->decimal('outstanding_balance', 15, 2)->default(null)->change();
            $table->integer('remaining_term_months')->default(null)->change();
        });
        DB::statement("ALTER TABLE mortgages MODIFY COLUMN rate_type ENUM('fixed','variable','tracker','discount','mixed') NOT NULL");

        // Family members table
        Schema::table('family_members', function (Blueprint $table) {
            $table->string('name')->default(null)->change();
        });
        DB::statement("ALTER TABLE family_members MODIFY COLUMN relationship ENUM('spouse','child','parent','other_dependent') NOT NULL");

        // Business interests table
        Schema::table('business_interests', function (Blueprint $table) {
            $table->string('business_name')->nullable(false)->change();
            $table->decimal('current_valuation', 15, 2)->default(null)->change();
            $table->date('valuation_date')->nullable(false)->change();
        });
        DB::statement("ALTER TABLE business_interests MODIFY COLUMN business_type ENUM('sole_trader','partnership','limited_company','llp','other') NOT NULL");

        // Chattels table
        Schema::table('chattels', function (Blueprint $table) {
            $table->string('name')->nullable(false)->change();
            $table->decimal('current_value', 15, 2)->default(null)->change();
            $table->date('valuation_date')->nullable(false)->change();
        });
        DB::statement("ALTER TABLE chattels MODIFY COLUMN chattel_type ENUM('vehicle','art','antique','jewelry','collectible','other') NOT NULL");

        // Gifts table
        Schema::table('gifts', function (Blueprint $table) {
            $table->date('gift_date')->nullable(false)->change();
            $table->string('recipient')->nullable(false)->change();
            $table->decimal('gift_value', 15, 2)->default(null)->change();
        });
        DB::statement("ALTER TABLE gifts MODIFY COLUMN gift_type ENUM('pet','clt','exempt','small_gift','annual_exemption') NOT NULL");
    }
};
