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
     * Adds charity tracking fields to bequests table for IHT reduced rate calculation.
     * - beneficiary_type: Categorizes the beneficiary for charitable analysis
     * - notes: Free text for wish detection (triggers trust recommendations)
     * - charity_registration_number: UK charity registration for validation
     */
    public function up(): void
    {
        Schema::table('bequests', function (Blueprint $table) {
            // Beneficiary type for categorisation (individual, charity, trust, organization)
            $table->enum('beneficiary_type', ['individual', 'charity', 'trust', 'organization'])
                ->default('individual')
                ->after('beneficiary_user_id');

            // Notes field for wish detection (triggers trust recommendations)
            $table->text('notes')->nullable()->after('conditions');

            // UK charity registration number for validation
            $table->string('charity_registration_number', 20)->nullable()->after('beneficiary_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bequests', function (Blueprint $table) {
            $table->dropColumn(['beneficiary_type', 'notes', 'charity_registration_number']);
        });
    }
};
