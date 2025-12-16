<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a demo user
        User::create([
            'first_name' => 'Demo',
            'surname' => 'User',
            'email' => 'demo@fps.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        $this->command->info('Demo user created successfully!');
        $this->command->info('Email: demo@fps.com');
        $this->command->info('Password: password');
    }
}
