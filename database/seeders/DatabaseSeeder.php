<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Main Database Seeder
 *
 * This seeder orchestrates all other seeders in the correct order.
 * See /seedMigration.md for full documentation on seeding procedures.
 *
 * Seeder Categories:
 * 1. Reference Data (REQUIRED) - Tax config, life tables, product info
 * 2. User Data (OPTIONAL) - Demo users, admin, preview personas
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // ============================================================
        // PHASE 1: Reference Data (REQUIRED for app to function)
        // These seeders populate lookup tables and configuration data
        // ============================================================
        $this->call([
            // Tax configuration - rates, allowances, thresholds
            TaxConfigurationSeeder::class,

            // Tax product reference - ISA/GIA/Bond tax treatment info
            TaxProductReferenceSeeder::class,

            // Life expectancy tables - used for retirement/estate projections
            UKLifeExpectancySeeder::class,

            // Actuarial tables - detailed mortality data for calculations
            ActuarialLifeTablesSeeder::class,
        ]);

        // ============================================================
        // PHASE 2: User Data (for development/demo environments)
        // Skip these in production - they create test accounts
        // ============================================================
        if (app()->environment(['local', 'development', 'staging'])) {
            $this->call([
                // Households for multi-user testing
                HouseholdSeeder::class,

                // Demo/test user accounts
                TestUsersSeeder::class,

                // Admin account (demo@fps.com, admin@fps.com)
                AdminUserSeeder::class,

                // Preview personas (young_family, peak_earners, etc.)
                PreviewUserSeeder::class,
            ]);
        }
    }

    /**
     * Seed only reference data (for production use).
     * Call with: php artisan db:seed --class=DatabaseSeeder -- --reference-only
     */
    public function seedReferenceDataOnly(): void
    {
        $this->call([
            TaxConfigurationSeeder::class,
            TaxProductReferenceSeeder::class,
            UKLifeExpectancySeeder::class,
            ActuarialLifeTablesSeeder::class,
        ]);
    }
}
