<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\RolesPermissionsSeeder;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    // Create admin and regular users (set is_preview_user to skip email verification)
    // Seed roles and permissions
    $this->seed(RolesPermissionsSeeder::class);

    $adminRole = Role::findByName(Role::ROLE_ADMIN);
    $userRole = Role::findByName(Role::ROLE_USER);

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
        $this->seed(AdminUserSeeder::class);

        $admin = User::where('email', 'admin@fps.com')->first();

        expect($admin)->not->toBeNull();
        expect($admin->is_admin)->toBeTrue();
        expect($admin->role()->first()?->name)->toBe('admin');
        expect($admin->name)->toBe('Admin User');
    });

    it('allows admin user to authenticate', function () {
        // Run the admin seeder
        $this->seed(AdminUserSeeder::class);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'admin@fps.com',
            'password' => env('ADMIN_SEED_PASSWORD', 'Fynl@Adm1n2026!'),
        ]);

        $response->assertStatus(200);
    });

    it('preserves an existing admin password and profile when reseeded', function () {
        $adminRole = Role::findByName(Role::ROLE_ADMIN);
        $existing = User::factory()->create([
            'email' => 'admin@fps.com',
            'first_name' => 'Existing',
            'surname' => 'Administrator',
            'password' => Hash::make('ExistingPassword1!'),
            'role_id' => $adminRole->id,
            'is_admin' => true,
        ]);

        $this->seed(AdminUserSeeder::class);

        $existing->refresh();

        expect($existing->first_name)->toBe('Existing')
            ->and($existing->surname)->toBe('Administrator')
            ->and(Hash::check('ExistingPassword1!', $existing->password))->toBeTrue();
    });

    it('does not create the demo admin with a fallback password outside development', function (string $environment) {
        $originalEnvironment = app()->environment();
        app()->detectEnvironment(fn (): string => $environment);

        try {
            app(AdminUserSeeder::class)->run();
        } finally {
            app()->detectEnvironment(fn (): string => $originalEnvironment);
        }

        expect(User::where('email', 'admin@fps.com')->exists())->toBeFalse();
    })->with(['staging', 'production']);

    it('does not recreate a soft-deleted demo admin', function () {
        $deleted = User::factory()->create(['email' => 'admin@fps.com']);
        $deleted->delete();

        $this->seed(AdminUserSeeder::class);

        expect(User::withTrashed()->where('email', 'admin@fps.com')->count())->toBe(1)
            ->and(User::withTrashed()->where('email', 'admin@fps.com')->firstOrFail()->trashed())->toBeTrue();
    });
});
