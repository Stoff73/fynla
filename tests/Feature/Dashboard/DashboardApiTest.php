<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->user = User::factory()->create([
        'first_name' => 'Test',
        'surname' => 'User',
        'email' => 'test@example.com',
    ]);
});

afterEach(function () {
    Cache::flush();
});

it('requires authentication for dashboard index', function () {
    $response = $this->getJson('/api/dashboard');

    $response->assertStatus(401);
});

it('returns aggregated data from dashboard index', function () {
    $response = $this->actingAs($this->user)
        ->getJson('/api/dashboard');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'data' => [
                'protection',
                'savings',
                'investment',
                'retirement',
                'estate',
            ],
        ]);
});

it('caches dashboard index data for 5 minutes', function () {
    // First request
    $response1 = $this->actingAs($this->user)
        ->getJson('/api/dashboard');

    $response1->assertStatus(200);

    // Check cache exists
    $cacheKey = "dashboard_{$this->user->id}";
    expect(Cache::has($cacheKey))->toBeTrue();

    // Second request should use cache
    $response2 = $this->actingAs($this->user)
        ->getJson('/api/dashboard');

    $response2->assertStatus(200);
});

it('requires authentication for alerts', function () {
    $response = $this->getJson('/api/dashboard/alerts');

    $response->assertStatus(401);
});

it('returns prioritised alerts from all modules', function () {
    $response = $this->actingAs($this->user)
        ->getJson('/api/dashboard/alerts');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'data',
        ]);

    $alerts = $response->json('data');
    expect($alerts)->toBeArray();
});

it('sorts alerts by severity', function () {
    $response = $this->actingAs($this->user)
        ->getJson('/api/dashboard/alerts');

    $response->assertStatus(200);

    $alerts = $response->json('data');

    if (count($alerts) > 1) {
        $severityOrder = ['critical' => 0, 'important' => 1, 'info' => 2];

        for ($i = 0; $i < count($alerts) - 1; $i++) {
            $currentSeverity = $severityOrder[$alerts[$i]['severity']] ?? 2;
            $nextSeverity = $severityOrder[$alerts[$i + 1]['severity']] ?? 2;

            expect($currentSeverity)->toBeLessThanOrEqual($nextSeverity);
        }
    }
});

it('includes module information in alerts', function () {
    $response = $this->actingAs($this->user)
        ->getJson('/api/dashboard/alerts');

    $response->assertStatus(200);

    $alerts = $response->json('data');

    foreach ($alerts as $alert) {
        expect($alert)->toHaveKeys(['module', 'severity', 'title', 'message', 'action_link', 'action_text']);
    }
});

it('caches alert data for 15 minutes', function () {
    $response = $this->actingAs($this->user)
        ->getJson('/api/dashboard/alerts');

    $response->assertStatus(200);

    $cacheKey = "alerts_{$this->user->id}";
    expect(Cache::has($cacheKey))->toBeTrue();
});

it('requires authentication to dismiss an alert', function () {
    $response = $this->postJson('/api/dashboard/alerts/1/dismiss');

    $response->assertStatus(401);
});

it('invalidates cache when dismissing an alert', function () {
    // Prime the cache
    $this->actingAs($this->user)
        ->getJson('/api/dashboard/alerts');

    $cacheKey = "alerts_{$this->user->id}";
    expect(Cache::has($cacheKey))->toBeTrue();

    // Dismiss an alert
    $response = $this->actingAs($this->user)
        ->postJson('/api/dashboard/alerts/1/dismiss');

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
        ]);

    // Cache should be cleared
    expect(Cache::has($cacheKey))->toBeFalse();
});

it('requires authentication to invalidate cache', function () {
    $response = $this->postJson('/api/dashboard/invalidate-cache');

    $response->assertStatus(401);
});

it('clears all dashboard caches on invalidation', function () {
    // Prime all caches
    $this->actingAs($this->user)->getJson('/api/dashboard');
    $this->actingAs($this->user)->getJson('/api/dashboard/alerts');

    $dashboardKey = "dashboard_{$this->user->id}";
    $alertsKey = "alerts_{$this->user->id}";

    expect(Cache::has($dashboardKey))->toBeTrue();
    expect(Cache::has($alertsKey))->toBeTrue();

    // Invalidate cache
    $response = $this->actingAs($this->user)
        ->postJson('/api/dashboard/invalidate-cache');

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
        ]);

    // All caches should be cleared
    expect(Cache::has($dashboardKey))->toBeFalse();
    expect(Cache::has($alertsKey))->toBeFalse();
});

it('provides separate cached data for different users', function () {
    $user2 = User::factory()->create([
        'first_name' => 'Test',
        'surname' => 'User 2',
        'email' => 'test2@example.com',
    ]);

    // User 1 request
    $this->actingAs($this->user)
        ->getJson('/api/dashboard');

    // User 2 request
    $this->actingAs($user2)
        ->getJson('/api/dashboard');

    // Both should have separate cache keys
    $cacheKey1 = "dashboard_{$this->user->id}";
    $cacheKey2 = "dashboard_{$user2->id}";

    expect(Cache::has($cacheKey1))->toBeTrue();
    expect(Cache::has($cacheKey2))->toBeTrue();

    // Keys should be different
    expect($cacheKey1)->not->toBe($cacheKey2);
});

it('handles errors gracefully in dashboard', function () {
    // This test ensures the endpoint doesn't crash even with missing data
    $response = $this->actingAs($this->user)
        ->getJson('/api/dashboard');

    $response->assertStatus(200);
    expect($response->json('success'))->toBeTrue();
});
