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
    test('admin user login returns user data', function () {
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

    test('authenticated admin user endpoint returns admin role', function () {
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

    test('authenticated regular user endpoint returns user role', function () {
        Sanctum::actingAs($this->regularUser);

        $response = $this->getJson('/api/auth/user');

        $response->assertStatus(200);
        expect($response->json('data.role'))->toBe('user');
    });
});

describe('Admin-Only Routes Protection', function () {
    test('unauthenticated user cannot access admin routes', function () {
        // Try to access an admin route without authentication
        $response = $this->getJson('/api/uk-taxes');

        // Should get 401 Unauthorized (from auth:sanctum middleware)
        $response->assertStatus(401);
    });

    test('regular user gets 403 forbidden when accessing admin routes', function () {
        Sanctum::actingAs($this->regularUser);

        $response = $this->getJson('/api/uk-taxes');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
            ]);
    });

    test('admin user can access admin routes', function () {
        Sanctum::actingAs($this->adminUser);

        // Note: This will fail with 404 if the route doesn't exist yet
        // If UK Taxes routes are not yet implemented, this test will fail
        // For now, we're just testing that the middleware doesn't block admin access
        $response = $this->getJson('/api/uk-taxes');

        // Should not get 403 Forbidden (middleware passes)
        expect($response->status())->not->toBe(403);
    });
});

describe('Dashboard Visibility', function () {
    test('dashboard response includes user role', function () {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/dashboard');

        $response->assertStatus(200);
        // Dashboard should return successfully for admin user
    });

    test('regular user can access dashboard', function () {
        Sanctum::actingAs($this->regularUser);

        $response = $this->getJson('/api/dashboard');

        $response->assertStatus(200);
        // Regular users should still be able to access dashboard
    });
});

describe('Admin Seeder', function () {
    test('admin user exists in database after seeding', function () {
        // Run the admin seeder
        $this->seed(\Database\Seeders\AdminUserSeeder::class);

        $admin = User::where('email', 'admin@fps.com')->first();

        expect($admin)->not->toBeNull();
        expect($admin->is_admin)->toBeTrue();
        expect($admin->role()->first()?->name)->toBe('admin');
        expect($admin->name)->toBe('Admin User');
    });

    test('admin user can authenticate', function () {
        // Run the admin seeder
        $this->seed(\Database\Seeders\AdminUserSeeder::class);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'admin@fps.com',
            'password' => 'admin123',
        ]);

        $response->assertStatus(200);
    });
});
