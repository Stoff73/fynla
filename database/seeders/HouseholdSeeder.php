<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Household;
use Illuminate\Database\Seeder;

class HouseholdSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create test household for spouse linking
        Household::firstOrCreate(
            ['household_name' => 'Smith Family'],
            ['notes' => 'Test household for development - married couple with joint assets']
        );

        // Create another test household
        Household::firstOrCreate(
            ['household_name' => 'Jones Family'],
            ['notes' => 'Test household for development - second family']
        );
    }
}
