<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds 'civil_partnership' to the users.marital_status enum.
 *
 * The Fyn onboarding flow collects civil partnership as a distinct
 * marital status (fynOnboardFix.md §5.2 base_marital bubbles), and
 * nine existing services already branch on
 * `in_array($user->marital_status, ['married', 'civil_partnership'])`:
 *
 *   - ProfileCompletenessChecker
 *   - EstatePlanService / IHTCalculationService / IntestacyCalculator
 *   - InvestmentPlanService / SpouseOptimisationService
 *   - RetirementPlanService / ProtectionPlanService
 *   - UserContextBuilder / IHTProfileFactory
 *
 * The enum constraint was a latent bug — the app expected the value
 * but the DB rejected it. This migration aligns the schema with the code.
 *
 * Uses raw ALTER TABLE because Doctrine DBAL does not handle MySQL ENUM
 * value additions through the Blueprint API.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            'ALTER TABLE users MODIFY COLUMN marital_status '.
            "enum('single','married','civil_partnership','divorced','widowed') DEFAULT NULL"
        );
    }

    public function down(): void
    {
        // Downgrade: map any existing civil_partnership rows to 'married'
        // before shrinking the enum, otherwise the ALTER fails on violated
        // values.
        DB::statement("UPDATE users SET marital_status = 'married' WHERE marital_status = 'civil_partnership'");
        DB::statement(
            'ALTER TABLE users MODIFY COLUMN marital_status '.
            "enum('single','married','divorced','widowed') DEFAULT NULL"
        );
    }
};
