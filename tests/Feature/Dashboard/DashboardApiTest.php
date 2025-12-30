<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->user = User::factory()->create([
        'first_name' => 'Test',
        'surname' => 'User',
        'email' => 'test@example.com',
    ]);
});

afterEach(function () {
    Cache::flush();
});

test('dashboard index requires authentication', function () {
    $response = $this->getJson('/api/dashboard');

    $response->assertStatus(401);
});

test('dashboard index returns aggregated data', function () {
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

test('dashboard index caches data for 5 minutes', function () {
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

test('financial health score requires authentication', function () {
    $response = $this->getJson('/api/dashboard/financial-health-score');

    $response->assertStatus(401);
});

test('financial health score returns composite score', function () {
    $response = $this->actingAs($this->user)
        ->getJson('/api/dashboard/financial-health-score');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'data' => [
                'composite_score',
                'breakdown' => [
                    'protection',
                    'emergency_fund',
                    'retirement',
                    'investment',
                    'estate',
                ],
                'label',
                'recommendation',
            ],
        ]);
});

test('financial health score includes weighted breakdown', function () {
    $response = $this->actingAs($this->user)
        ->getJson('/api/dashboard/financial-health-score');

    $response->assertStatus(200);

    $data = $response->json('data');
    $breakdown = $data['breakdown'];

    // Check weights
    expect($breakdown['protection']['weight'])->toBe(0.20);
    expect($breakdown['emergency_fund']['weight'])->toBe(0.15);
    expect($breakdown['retirement']['weight'])->toBe(0.25);
    expect($breakdown['investment']['weight'])->toBe(0.20);
    expect($breakdown['estate']['weight'])->toBe(0.20);

    // Check contributions exist
    expect($breakdown['protection'])->toHaveKey('contribution');
    expect($breakdown['emergency_fund'])->toHaveKey('contribution');
    expect($breakdown['retirement'])->toHaveKey('contribution');
    expect($breakdown['investment'])->toHaveKey('contribution');
    expect($breakdown['estate'])->toHaveKey('contribution');
});

test('financial health score caches data for 1 hour', function () {
    $response = $this->actingAs($this->user)
        ->getJson('/api/dashboard/financial-health-score');

    $response->assertStatus(200);

    $cacheKey = "financial_health_score_{$this->user->id}";
    expect(Cache::has($cacheKey))->toBeTrue();
});

test('alerts requires authentication', function () {
    $response = $this->getJson('/api/dashboard/alerts');

    $response->assertStatus(401);
});

test('alerts returns prioritized alerts from all modules', function () {
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

test('alerts are sorted by severity', function () {
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

test('alerts include module information', function () {
    $response = $this->actingAs($this->user)
        ->getJson('/api/dashboard/alerts');

    $response->assertStatus(200);

    $alerts = $response->json('data');

    foreach ($alerts as $alert) {
        expect($alert)->toHaveKeys(['module', 'severity', 'title', 'message', 'action_link', 'action_text']);
    }
});

test('alerts cache data for 15 minutes', function () {
    $response = $this->actingAs($this->user)
        ->getJson('/api/dashboard/alerts');

    $response->assertStatus(200);

    $cacheKey = "alerts_{$this->user->id}";
    expect(Cache::has($cacheKey))->toBeTrue();
});

test('dismiss alert requires authentication', function () {
    $response = $this->postJson('/api/dashboard/alerts/1/dismiss');

    $response->assertStatus(401);
});

test('dismiss alert invalidates cache', function () {
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

test('invalidate cache requires authentication', function () {
    $response = $this->postJson('/api/dashboard/invalidate-cache');

    $response->assertStatus(401);
});

test('invalidate cache clears all dashboard caches', function () {
    // Prime all caches
    $this->actingAs($this->user)->getJson('/api/dashboard');
    $this->actingAs($this->user)->getJson('/api/dashboard/financial-health-score');
    $this->actingAs($this->user)->getJson('/api/dashboard/alerts');

    $dashboardKey = "dashboard_{$this->user->id}";
    $healthScoreKey = "financial_health_score_{$this->user->id}";
    $alertsKey = "alerts_{$this->user->id}";

    expect(Cache::has($dashboardKey))->toBeTrue();
    expect(Cache::has($healthScoreKey))->toBeTrue();
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
    expect(Cache::has($healthScoreKey))->toBeFalse();
    expect(Cache::has($alertsKey))->toBeFalse();
});

test('different users get separate cached data', function () {
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

test('dashboard handles errors gracefully', function () {
    // This test ensures the endpoint doesn't crash even with missing data
    $response = $this->actingAs($this->user)
        ->getJson('/api/dashboard');

    $response->assertStatus(200);
    expect($response->json('success'))->toBeTrue();
});

test('financial health score label is correct for excellent score', function () {
    $response = $this->actingAs($this->user)
        ->getJson('/api/dashboard/financial-health-score');

    $response->assertStatus(200);

    $data = $response->json('data');
    $score = $data['composite_score'];
    $label = $data['label'];

    if ($score >= 80) {
        expect($label)->toBe('Excellent');
    } elseif ($score >= 60) {
        expect($label)->toBe('Good');
    } elseif ($score >= 40) {
        expect($label)->toBe('Fair');
    } else {
        expect($label)->toBe('Needs Improvement');
    }
});

test('financial health score recommendation is appropriate', function () {
    $response = $this->actingAs($this->user)
        ->getJson('/api/dashboard/financial-health-score');

    $response->assertStatus(200);

    $data = $response->json('data');
    expect($data['recommendation'])->toBeString();
    expect(strlen($data['recommendation']))->toBeGreaterThan(10);
});
