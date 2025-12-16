<?php

namespace Database\Seeders;

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
        // Create first spouse (primary account holder)
        $johnSmith = User::create([
            'first_name' => 'John',
            'surname' => 'Smith',
            'email' => 'john@example.com',
            'password' => Hash::make('password'),
            'household_id' => 1, // Smith Family
            'is_primary_account' => true,
            'role' => 'user',
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
        ]);

        // Create second spouse
        $janeSmith = User::create([
            'first_name' => 'Jane',
            'surname' => 'Smith',
            'email' => 'jane@example.com',
            'password' => Hash::make('password'),
            'household_id' => 1, // Smith Family
            'is_primary_account' => false,
            'role' => 'user',
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
        ]);

        // Link spouses to each other
        $johnSmith->update(['spouse_id' => $janeSmith->id]);
        $janeSmith->update(['spouse_id' => $johnSmith->id]);

        // Create single user in second household
        User::create([
            'first_name' => 'Sarah',
            'surname' => 'Jones',
            'email' => 'sarah@example.com',
            'password' => Hash::make('password'),
            'household_id' => 2, // Jones Family
            'is_primary_account' => true,
            'role' => 'user',
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
        ]);
    }
}
