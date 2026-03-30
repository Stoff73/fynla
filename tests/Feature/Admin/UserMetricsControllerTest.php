<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(RolesPermissionsSeeder::class);

    // Create admin user with admin role
    $adminRole = Role::where('name', Role::ROLE_ADMIN)->first();
    $this->adminUser = User::factory()->create([
        'role_id' => $adminRole->id,
        'is_admin' => true,
    ]);
    $this->adminToken = $this->adminUser->createToken('test-token')->plainTextToken;

    // Create regular (non-admin) user
    $this->regularUser = User::factory()->create();
    $this->regularToken = $this->regularUser->createToken('test-token')->plainTextToken;
});

describe('UserMetricsController', function () {
    describe('snapshot endpoint', function () {
        it('returns snapshot data for admin user', function () {
            // Create some real users (non-preview)
            User::factory()->count(3)->create(['is_preview_user' => false]);

            $response = $this->withToken($this->adminToken)
                ->getJson('/api/admin/user-metrics/snapshot');

            $response->assertOk()
                ->assertJsonStructure([
                    'total_registered',
                    'active_subscribers',
                    'on_trial',
                    'never_paid',
                ]);

            // total_registered should include the 3 factory users + admin + regular user (5 total non-preview)
            expect($response->json('total_registered'))->toBeGreaterThanOrEqual(3);
        });

        it('returns 403 for non-admin user', function () {
            $response = $this->withToken($this->regularToken)
                ->getJson('/api/admin/user-metrics/snapshot');

            $response->assertStatus(403);
        });

        it('returns 401 for unauthenticated request', function () {
            $response = $this->getJson('/api/admin/user-metrics/snapshot');

            $response->assertStatus(401);
        });
    });

    describe('trials endpoint', function () {
        it('returns trial breakdown for admin user', function () {
            // Create a trialing subscription
            $user = User::factory()->create(['is_preview_user' => false]);
            Subscription::factory()->trialing()->create([
                'user_id' => $user->id,
                'trial_ends_at' => now()->addDays(5),
            ]);

            $response = $this->withToken($this->adminToken)
                ->getJson('/api/admin/user-metrics/trials');

            $response->assertOk()
                ->assertJsonStructure([
                    'four_plus_days',
                    'three_days',
                    'two_days',
                    'one_day',
                    'expiring_today',
                    'expired',
                ]);

            expect($response->json('four_plus_days'))->toBeGreaterThanOrEqual(1);
        });

        it('returns 403 for non-admin user', function () {
            $response = $this->withToken($this->regularToken)
                ->getJson('/api/admin/user-metrics/trials');

            $response->assertStatus(403);
        });
    });

    describe('plans endpoint', function () {
        it('returns plan breakdown for admin user', function () {
            // Create active subscriptions
            $user1 = User::factory()->create(['is_preview_user' => false]);
            Subscription::factory()->create([
                'user_id' => $user1->id,
                'plan' => 'standard',
                'billing_cycle' => 'monthly',
                'status' => 'active',
                'amount' => 1099,
            ]);

            $user2 = User::factory()->create(['is_preview_user' => false]);
            Subscription::factory()->create([
                'user_id' => $user2->id,
                'plan' => 'pro',
                'billing_cycle' => 'yearly',
                'status' => 'active',
                'amount' => 20000,
            ]);

            $response = $this->withToken($this->adminToken)
                ->getJson('/api/admin/user-metrics/plans');

            $response->assertOk();

            $data = $response->json();
            expect($data)->toBeArray();
            expect(count($data))->toBe(4); // student, standard, family, pro

            // Verify structure of each plan entry
            foreach ($data as $plan) {
                expect($plan)->toHaveKeys([
                    'plan',
                    'total',
                    'monthly',
                    'yearly',
                    'monthly_revenue',
                    'yearly_revenue',
                ]);
            }

            // Find standard plan and verify it has our subscription
            $standardPlan = collect($data)->firstWhere('plan', 'standard');
            expect($standardPlan['monthly'])->toBe(1);
            expect($standardPlan['monthly_revenue'])->toBe(1099);

            // Find pro plan and verify
            $proPlan = collect($data)->firstWhere('plan', 'pro');
            expect($proPlan['yearly'])->toBe(1);
            expect($proPlan['yearly_revenue'])->toBe(20000);
        });

        it('returns 403 for non-admin user', function () {
            $response = $this->withToken($this->regularToken)
                ->getJson('/api/admin/user-metrics/plans');

            $response->assertStatus(403);
        });
    });

    describe('activity endpoint', function () {
        it('returns activity data with default parameters for admin user', function () {
            $response = $this->withToken($this->adminToken)
                ->getJson('/api/admin/user-metrics/activity');

            $response->assertOk();

            $data = $response->json();
            expect($data)->toBeArray();
            expect(count($data))->toBe(7); // default range = 7

            // Verify structure of each bucket
            foreach ($data as $bucket) {
                expect($bucket)->toHaveKeys([
                    'period',
                    'registrations',
                    'conversions',
                    'cancellations',
                    'trial_expired',
                    'revenue',
                ]);
            }
        });

        it('accepts custom period and range parameters', function () {
            $response = $this->withToken($this->adminToken)
                ->getJson('/api/admin/user-metrics/activity?period=month&range=3');

            $response->assertOk();

            $data = $response->json();
            expect(count($data))->toBe(3);
        });

        it('returns 422 for invalid period', function () {
            $response = $this->withToken($this->adminToken)
                ->getJson('/api/admin/user-metrics/activity?period=invalid');

            $response->assertStatus(422)
                ->assertJson(['error' => 'Invalid period']);
        });

        it('clamps range to valid bounds', function () {
            // Range above 365 should be clamped to 365
            $response = $this->withToken($this->adminToken)
                ->getJson('/api/admin/user-metrics/activity?period=day&range=500');

            $response->assertOk();

            $data = $response->json();
            expect(count($data))->toBe(365);
        });

        it('returns 403 for non-admin user', function () {
            $response = $this->withToken($this->regularToken)
                ->getJson('/api/admin/user-metrics/activity');

            $response->assertStatus(403);
        });
    });

    describe('engagement endpoint', function () {
        it('returns engagement stats for admin user', function () {
            // Create users without active subscriptions (non-converters)
            User::factory()->count(2)->create([
                'is_preview_user' => false,
                'onboarding_completed' => true,
            ]);

            User::factory()->create([
                'is_preview_user' => false,
                'onboarding_completed' => false,
            ]);

            $response = $this->withToken($this->adminToken)
                ->getJson('/api/admin/user-metrics/engagement');

            $response->assertOk()
                ->assertJsonStructure([
                    'total',
                    'onboarding_completed_pct',
                    'used_one_plus_modules_pct',
                    'used_three_plus_modules_pct',
                ]);

            // total should include admin + regular + 3 new users = at least 5 non-converters
            expect($response->json('total'))->toBeGreaterThanOrEqual(3);
        });

        it('returns 403 for non-admin user', function () {
            $response = $this->withToken($this->regularToken)
                ->getJson('/api/admin/user-metrics/engagement');

            $response->assertStatus(403);
        });
    });
});
