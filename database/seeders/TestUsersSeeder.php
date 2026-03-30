<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Household;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get household IDs by name (created by HouseholdSeeder)
        $smithHousehold = Household::where('household_name', 'Smith Family')->first();
        $jonesHousehold = Household::where('household_name', 'Jones Family')->first();

        if (! $smithHousehold || ! $jonesHousehold) {
            $this->command->warn('Households not found. Run HouseholdSeeder first.');

            return;
        }

        // Trial: 1 year from now so test users always have full access
        $trialEnd = now()->addYear();

        // Create first spouse (primary account holder)
        $johnSmith = User::firstOrCreate(
            ['email' => 'john@example.com'],
            [
                'first_name' => 'John',
                'surname' => 'Smith',
                'password' => Hash::make('password'),
                'household_id' => $smithHousehold->id,
                'is_primary_account' => true,
                'role_id' => \App\Models\Role::findByName(\App\Models\Role::ROLE_USER)?->id,
                'date_of_birth' => '1980-05-15',
                'gender' => 'male',
                'marital_status' => 'married',
                'national_insurance_number' => 'AB123456C',
                'address_line_1' => '123 Main Street',
                'city' => 'London',
                'postcode' => 'SW1A 1AA',
                'phone' => '07700900123',
                'occupation' => 'Software Engineer',
                'employer' => 'Tech Corp Ltd',
                'industry' => 'Technology',
                'employment_status' => 'employed',
                'annual_employment_income' => 75000.00,
                'trial_ends_at' => $trialEnd,
            ]
        );
        $johnSmith->update(['trial_ends_at' => $trialEnd]);

        // Create second spouse
        $janeSmith = User::firstOrCreate(
            ['email' => 'jane@example.com'],
            [
                'first_name' => 'Jane',
                'surname' => 'Smith',
                'password' => Hash::make('password'),
                'household_id' => $smithHousehold->id,
                'is_primary_account' => false,
                'role_id' => \App\Models\Role::findByName(\App\Models\Role::ROLE_USER)?->id,
                'date_of_birth' => '1982-08-22',
                'gender' => 'female',
                'marital_status' => 'married',
                'national_insurance_number' => 'CD789012D',
                'address_line_1' => '123 Main Street',
                'city' => 'London',
                'postcode' => 'SW1A 1AA',
                'phone' => '07700900456',
                'occupation' => 'Marketing Manager',
                'employer' => 'Marketing Solutions Ltd',
                'industry' => 'Marketing',
                'employment_status' => 'employed',
                'annual_employment_income' => 55000.00,
                'trial_ends_at' => $trialEnd,
            ]
        );
        $janeSmith->update(['trial_ends_at' => $trialEnd]);

        // Link spouses to each other
        $johnSmith->update(['spouse_id' => $janeSmith->id]);
        $janeSmith->update(['spouse_id' => $johnSmith->id]);

        // Create single user in second household
        User::firstOrCreate(
            ['email' => 'sarah@example.com'],
            [
                'first_name' => 'Sarah',
                'surname' => 'Jones',
                'password' => Hash::make('password'),
                'household_id' => $jonesHousehold->id,
                'is_primary_account' => true,
                'role_id' => \App\Models\Role::findByName(\App\Models\Role::ROLE_USER)?->id,
                'date_of_birth' => '1985-03-10',
                'gender' => 'female',
                'marital_status' => 'single',
                'national_insurance_number' => 'EF345678E',
                'address_line_1' => '456 High Street',
                'city' => 'Manchester',
                'postcode' => 'M1 1AA',
                'phone' => '07700900789',
                'occupation' => 'Teacher',
                'employer' => 'Manchester Primary School',
                'industry' => 'Education',
                'employment_status' => 'employed',
                'annual_employment_income' => 35000.00,
                'trial_ends_at' => $trialEnd,
            ]
        );
        $sarahJones = User::where('email', 'sarah@example.com')->first();
        $sarahJones?->update(['trial_ends_at' => $trialEnd]);

        // Create trial subscriptions for all test users
        foreach ([$johnSmith, $janeSmith, $sarahJones] as $user) {
            if ($user) {
                Subscription::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'plan' => 'standard',
                        'billing_cycle' => 'monthly',
                        'status' => 'trialing',
                        'amount' => 0,
                        'trial_started_at' => now(),
                        'trial_ends_at' => $trialEnd,
                        'current_period_start' => now(),
                        'current_period_end' => $trialEnd,
                    ]
                );
            }
        }
    }
}
