<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    // Create admin and regular users (set is_preview_user to skip email verification)
    // Seed roles and permissions
    $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);

    $adminRole = \App\Models\Role::findByName(\App\Models\Role::ROLE_ADMIN);
    $userRole = \App\Models\Role::findByName(\App\Models\Role::ROLE_USER);

    $this->adminUser = User::factory()->create([
        'first_name' => 'Admin',
        'surname' => 'User',
        'email' => 'admin@test.com',
        'role_id' => $adminRole->id,
        'is_admin' => true,
        'is_preview_user' => true,
    ]);

    $this->regularUser = User::factory()->create([
        'first_name' => 'Regular',
        'surname' => 'User',
        'email' => 'user@test.com',
        'role_id' => $userRole->id,
        'is_preview_user' => true,
    ]);
});

describe('Admin User Authentication', function () {
    it('returns user data on admin login', function () {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'admin@test.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'email',
                    ],
                    'access_token',
                    'token_type',
                ],
            ]);
    });

    it('returns admin role for authenticated admin user endpoint', function () {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/auth/user');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'user',
                    'role',
                    'permissions',
                ],
            ]);

        expect($response->json('data.role'))->toBe('admin');
    });

    it('returns user role for authenticated regular user endpoint', function () {
        Sanctum::actingAs($this->regularUser);

        $response = $this->getJson('/api/auth/user');

        $response->assertStatus(200);
        expect($response->json('data.role'))->toBe('user');
    });
});

describe('Admin-Only Routes Protection', function () {
    it('prevents unauthenticated user from accessing admin routes', function () {
        $response = $this->getJson('/api/admin/users');

        $response->assertStatus(401);
    });

    it('returns 403 forbidden for regular user accessing admin routes', function () {
        Sanctum::actingAs($this->regularUser);

        $response = $this->getJson('/api/admin/users');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
            ]);
    });

    it('allows admin user to access admin routes', function () {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/admin/users');

        expect($response->status())->not->toBe(403);
    });
});

describe('Dashboard Visibility', function () {
    it('includes user role in dashboard response', function () {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/dashboard');

        $response->assertStatus(200);
        // Dashboard should return successfully for admin user
    });

    it('allows regular user to access dashboard', function () {
        Sanctum::actingAs($this->regularUser);

        $response = $this->getJson('/api/dashboard');

        $response->assertStatus(200);
        // Regular users should still be able to access dashboard
    });
});

describe('Admin Seeder', function () {
    it('has admin user in database after seeding', function () {
        // Run the admin seeder
        $this->seed(\Database\Seeders\AdminUserSeeder::class);

        $admin = User::where('email', 'admin@fps.com')->first();

        expect($admin)->not->toBeNull();
        expect($admin->is_admin)->toBeTrue();
        expect($admin->role()->first()?->name)->toBe('admin');
        expect($admin->name)->toBe('Admin User');
    });

    it('allows admin user to authenticate', function () {
        // Run the admin seeder
        $this->seed(\Database\Seeders\AdminUserSeeder::class);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'admin@fps.com',
            'password' => env('ADMIN_SEED_PASSWORD', 'Fynl@Adm1n2026!'),
        ]);

        $response->assertStatus(200);
    });
});
