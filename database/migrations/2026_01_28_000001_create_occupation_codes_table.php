<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates table for ONS Standard Occupational Classification (SOC) 2020 codes.
     * See: https://www.ons.gov.uk/methodology/classificationsandstandards/standardoccupationalclassificationsoc/soc2020
     */
    public function up(): void
    {
        Schema::create('occupation_codes', function (Blueprint $table) {
            $table->id();
            $table->string('soc_code', 10)->index()->comment('SOC 2020 4-digit unit group code');
            $table->string('title', 255)->index()->comment('Job title or occupation name');
            $table->string('unit_group', 255)->nullable()->comment('SOC 2020 unit group description');
            $table->string('minor_group', 100)->nullable()->comment('SOC 2020 minor group (3-digit)');
            $table->string('sub_major_group', 100)->nullable()->comment('SOC 2020 sub-major group (2-digit)');
            $table->string('major_group', 100)->nullable()->comment('SOC 2020 major group (1-digit)');
            $table->boolean('is_primary')->default(false)->comment('Is this the primary title for the SOC code');
            $table->timestamps();

            // Full-text search index for faster lookups
            $table->fullText(['title']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('occupation_codes');
    }
};
