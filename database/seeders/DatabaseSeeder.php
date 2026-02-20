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
 * 1. Required Data (MUST RUN) - Tax config, life tables, product info, admin, preview users
 * 2. Optional Data - Additional test users for development
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // ============================================================
        // PHASE 1: Required Data (MUST RUN for app to function)
        // These seeders populate lookup tables, configuration data,
        // and essential user accounts
        // ============================================================
        $this->call([
            // Tax configuration - rates, allowances, thresholds
            TaxConfigurationSeeder::class,

            // Tax product reference - ISA/GIA/Bond tax treatment info
            TaxProductReferenceSeeder::class,

            // Actuarial life tables - life expectancy data for estate/retirement projections
            ActuarialLifeTablesSeeder::class,

            // Roles and permissions (must run before AdminUserSeeder)
            RolesPermissionsSeeder::class,

            // Admin account (demo@fps.com, admin@fps.com)
            AdminUserSeeder::class,

            // Preview personas (young_family, peak_earners, etc.)
            PreviewUserSeeder::class,
        ]);

        // ============================================================
        // PHASE 2: Optional Data (for development/testing only)
        // These create additional test accounts beyond the required ones
        // ============================================================
        if (app()->environment(['local', 'development', 'staging'])) {
            $this->call([
                // Households for multi-user testing
                HouseholdSeeder::class,

                // Additional test user accounts
                TestUsersSeeder::class,
            ]);
        }
    }

    /**
     * Seed only required data (for production use).
     * Call with: php artisan db:seed --class=DatabaseSeeder -- --reference-only
     */
    public function seedRequiredDataOnly(): void
    {
        $this->call([
            TaxConfigurationSeeder::class,
            TaxProductReferenceSeeder::class,
            ActuarialLifeTablesSeeder::class,
            RolesPermissionsSeeder::class,
            AdminUserSeeder::class,
            PreviewUserSeeder::class,
        ]);
    }
}
