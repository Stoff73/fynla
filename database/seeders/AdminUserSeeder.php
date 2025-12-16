<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin user (not linked to any household)
        User::updateOrCreate(
            ['email' => 'admin@fps.com'],
            [
                'first_name' => 'Admin',
                'surname' => 'User',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
                'is_admin' => true,  // Required for admin access checks
                'is_primary_account' => true,
                'date_of_birth' => '1975-01-01',
                'gender' => 'male',
                'marital_status' => 'single',
            ]
        );
    }
}
