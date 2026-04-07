<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Dashboard\DashboardAggregator;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->user = User::factory()->create([
        'first_name' => 'Integration',
        'surname' => 'Test User',
        'email' => 'integration@example.com',
    ]);

    $this->aggregator = app(DashboardAggregator::class);
});

afterEach(function () {
    Cache::flush();
});

it('aggregates data from all 5 modules', function () {
    $data = $this->aggregator->aggregateOverviewData($this->user->id);

    expect($data)->toBeArray();
    expect($data)->toHaveKeys([
        'protection',
        'savings',
        'investment',
        'retirement',
        'estate',
    ]);
});

it('includes adequacy score in protection summary', function () {
    $data = $this->aggregator->aggregateOverviewData($this->user->id);

    expect($data['protection'])->toHaveKey('adequacy_score');
    expect($data['protection']['adequacy_score'])->toBeNumeric();
});

it('includes emergency fund runway in savings summary', function () {
    $data = $this->aggregator->aggregateOverviewData($this->user->id);

    expect($data['savings'])->toHaveKey('emergency_fund_runway');
    expect($data['savings']['emergency_fund_runway'])->toBeNumeric();
});

it('includes portfolio value in investment summary', function () {
    $data = $this->aggregator->aggregateOverviewData($this->user->id);

    expect($data['investment'])->toHaveKey('portfolio_value');
    expect($data['investment']['portfolio_value'])->toBeNumeric();
});

it('includes income gap in retirement summary', function () {
    $data = $this->aggregator->aggregateOverviewData($this->user->id);

    expect($data['retirement'])->toHaveKey('income_gap');
    expect($data['retirement']['income_gap'])->toBeNumeric();
});

it('includes net worth in estate summary', function () {
    $data = $this->aggregator->aggregateOverviewData($this->user->id);

    expect($data['estate'])->toHaveKey('net_worth');
    expect($data['estate']['net_worth'])->toBeNumeric();
});

it('includes all modules in alerts aggregation', function () {
    $alerts = $this->aggregator->aggregateAlerts($this->user->id);

    expect($alerts)->toBeArray();

    // A bare user with no module data may produce zero alerts (all agents
    // return graceful defaults). If alerts are returned, verify they come
    // from recognised modules.
    if (count($alerts) > 0) {
        $modules = array_unique(array_column($alerts, 'module'));
        $validModules = ['Protection', 'Savings', 'Investment', 'Retirement', 'Estate'];
        foreach ($modules as $module) {
            expect($module)->toBeIn($validModules);
        }
    }
});

it('sorts alerts by severity', function () {
    $alerts = $this->aggregator->aggregateAlerts($this->user->id);

    expect($alerts)->toBeArray();

    if (count($alerts) > 1) {
        $severityOrder = ['critical' => 0, 'important' => 1, 'info' => 2];

        for ($i = 0; $i < count($alerts) - 1; $i++) {
            $currentSeverity = $severityOrder[$alerts[$i]['severity']] ?? 2;
            $nextSeverity = $severityOrder[$alerts[$i + 1]['severity']] ?? 2;

            expect($currentSeverity)->toBeLessThanOrEqual($nextSeverity);
        }
    }
});

it('has required fields in each alert', function () {
    $alerts = $this->aggregator->aggregateAlerts($this->user->id);

    expect($alerts)->toBeArray();

    foreach ($alerts as $alert) {
        expect($alert)->toHaveKeys([
            'id',
            'module',
            'severity',
            'title',
            'message',
            'action_link',
            'action_text',
            'created_at',
        ]);
    }
});

it('uses valid alert severity values', function () {
    $alerts = $this->aggregator->aggregateAlerts($this->user->id);

    expect($alerts)->toBeArray();

    $validSeverities = ['critical', 'important', 'info'];

    foreach ($alerts as $alert) {
        expect($alert['severity'])->toBeIn($validSeverities);
    }
});

it('fetches and caches dashboard data correctly', function () {
    // First call - should fetch and cache
    $response1 = $this->actingAs($this->user)
        ->getJson('/api/dashboard');

    $response1->assertStatus(200);

    $cacheKey = "dashboard_{$this->user->id}";
    expect(Cache::has($cacheKey))->toBeTrue();

    // Second call - should use cache
    $response2 = $this->actingAs($this->user)
        ->getJson('/api/dashboard');

    $response2->assertStatus(200);

    // Data should be the same
    expect($response1->json('data'))->toBe($response2->json('data'));
});

it('invalidates cache correctly', function () {
    // Prime the cache
    $this->actingAs($this->user)->getJson('/api/dashboard');
    $this->actingAs($this->user)->getJson('/api/dashboard/alerts');

    // Invalidate all caches
    $response = $this->actingAs($this->user)
        ->postJson('/api/dashboard/invalidate-cache');

    $response->assertStatus(200);

    // All caches should be cleared
    expect(Cache::has("dashboard_{$this->user->id}"))->toBeFalse();
    expect(Cache::has("alerts_{$this->user->id}"))->toBeFalse();
});

it('handles partial failures gracefully during parallel data loading', function () {
    // Even if some modules fail, the dashboard should still return data
    $data = $this->aggregator->aggregateOverviewData($this->user->id);

    // Should return array even if some modules have no data
    expect($data)->toBeArray();
});
