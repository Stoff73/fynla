<?php

declare(strict_types=1);

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
                'password' => Hash::make(env('ADMIN_SEED_PASSWORD', 'Fynl@Adm1n2026!')),
                'role_id' => $adminRole?->id,
                'is_primary_account' => true,
                'email_verified_at' => now(),
                'date_of_birth' => '1975-01-01',
                'gender' => 'male',
                'marital_status' => 'single',
            ]
        );

        // Sync is_admin flag with role assignment
        $user->is_admin = true;
        $user->save();

        // Promote any existing users listed in ADMIN_EMAILS to admin role
        $adminEmails = config('auth.admin_emails', []);
        foreach ($adminEmails as $email) {
            $existingUser = User::where('email', $email)->first();
            if ($existingUser && ! $existingUser->is_admin) {
                $existingUser->role_id = $adminRole?->id;
                $existingUser->is_admin = true;
                $existingUser->save();
                $this->command?->info("Promoted {$email} to admin.");
            }
        }
    }
}
