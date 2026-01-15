<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add 'other' to the trust_type enum
        DB::statement("ALTER TABLE trusts MODIFY COLUMN trust_type ENUM('bare','interest_in_possession','discretionary','accumulation_maintenance','life_insurance','discounted_gift','loan','mixed','settlor_interested','other') NOT NULL");

        Schema::table('trusts', function (Blueprint $table) {
            $table->string('other_type_description')->nullable()->after('trust_type')->comment('Description when trust_type is other');
            $table->string('country', 100)->nullable()->after('other_type_description')->comment('Country where trust is located');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // First update any 'other' types to 'mixed' before removing the enum option
        DB::statement("UPDATE trusts SET trust_type = 'mixed' WHERE trust_type = 'other'");

        // Remove 'other' from the enum
        DB::statement("ALTER TABLE trusts MODIFY COLUMN trust_type ENUM('bare','interest_in_possession','discretionary','accumulation_maintenance','life_insurance','discounted_gift','loan','mixed','settlor_interested') NOT NULL");

        Schema::table('trusts', function (Blueprint $table) {
            $table->dropColumn(['other_type_description', 'country']);
        });
    }
};
