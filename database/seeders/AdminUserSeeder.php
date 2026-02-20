<?php

namespace Database\Seeders;

use App\Models\Role;
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
        $adminRole = Role::findByName(Role::ROLE_ADMIN);

        // Create admin user (not linked to any household)
        $user = User::updateOrCreate(
            ['email' => 'admin@fps.com'],
            [
                'first_name' => 'Admin',
                'surname' => 'User',
                'password' => Hash::make('admin123'),
                'role_id' => $adminRole?->id,
                'is_primary_account' => true,
                'is_preview_user' => true,  // Skip email verification
                'date_of_birth' => '1975-01-01',
                'gender' => 'male',
                'marital_status' => 'single',
            ]
        );

        // Sync is_admin flag with role assignment
        $user->is_admin = true;
        $user->save();
    }
}
