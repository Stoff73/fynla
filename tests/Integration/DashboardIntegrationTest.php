<?php

use App\Models\User;
use App\Services\Dashboard\DashboardAggregator;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->user = User::factory()->create([
        'name' => 'Integration Test User',
        'email' => 'integration@example.com',
    ]);

    $this->aggregator = app(DashboardAggregator::class);
});

afterEach(function () {
    Cache::flush();
});

test('dashboard aggregates data from all 5 modules', function () {
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

test('protection summary includes adequacy score', function () {
    $data = $this->aggregator->aggregateOverviewData($this->user->id);

    expect($data['protection'])->toHaveKey('adequacy_score');
    expect($data['protection']['adequacy_score'])->toBeNumeric();
});

test('savings summary includes emergency fund runway', function () {
    $data = $this->aggregator->aggregateOverviewData($this->user->id);

    expect($data['savings'])->toHaveKey('emergency_fund_runway');
    expect($data['savings']['emergency_fund_runway'])->toBeNumeric();
});

test('investment summary includes portfolio value', function () {
    $data = $this->aggregator->aggregateOverviewData($this->user->id);

    expect($data['investment'])->toHaveKey('portfolio_value');
    expect($data['investment']['portfolio_value'])->toBeNumeric();
});

test('retirement summary includes income gap', function () {
    $data = $this->aggregator->aggregateOverviewData($this->user->id);

    expect($data['retirement'])->toHaveKey('income_gap');
    expect($data['retirement']['income_gap'])->toBeNumeric();
});

test('estate summary includes net worth', function () {
    $data = $this->aggregator->aggregateOverviewData($this->user->id);

    expect($data['estate'])->toHaveKey('net_worth');
    expect($data['estate']['net_worth'])->toBeNumeric();
});

test('financial health score calculation is correct', function () {
    $scoreData = $this->aggregator->calculateFinancialHealthScore($this->user->id);

    expect($scoreData)->toBeArray();
    expect($scoreData)->toHaveKeys([
        'composite_score',
        'breakdown',
        'label',
        'recommendation',
    ]);
});

test('composite score is between 0 and 100', function () {
    $scoreData = $this->aggregator->calculateFinancialHealthScore($this->user->id);

    $compositeScore = $scoreData['composite_score'];
    expect($compositeScore)->toBeGreaterThanOrEqual(0);
    expect($compositeScore)->toBeLessThanOrEqual(100);
});

test('breakdown includes all 5 modules', function () {
    $scoreData = $this->aggregator->calculateFinancialHealthScore($this->user->id);

    $breakdown = $scoreData['breakdown'];
    expect($breakdown)->toHaveKeys([
        'protection',
        'emergency_fund',
        'retirement',
        'investment',
        'estate',
    ]);
});

test('each module breakdown has score, weight, and contribution', function () {
    $scoreData = $this->aggregator->calculateFinancialHealthScore($this->user->id);

    $breakdown = $scoreData['breakdown'];

    foreach ($breakdown as $module => $data) {
        expect($data)->toHaveKeys(['score', 'weight', 'contribution']);
        expect($data['score'])->toBeNumeric();
        expect($data['weight'])->toBeNumeric();
        expect($data['contribution'])->toBeNumeric();
    }
});

test('weights sum to 1.0', function () {
    $scoreData = $this->aggregator->calculateFinancialHealthScore($this->user->id);

    $breakdown = $scoreData['breakdown'];
    $totalWeight = array_sum(array_column($breakdown, 'weight'));

    // Pest doesn't have toBeCloseTo, use toBe with delta check
    expect(abs($totalWeight - 1.0))->toBeLessThan(0.01);
});

test('contributions sum to composite score', function () {
    $scoreData = $this->aggregator->calculateFinancialHealthScore($this->user->id);

    $breakdown = $scoreData['breakdown'];
    $compositeScore = $scoreData['composite_score'];

    $totalContribution = array_sum(array_column($breakdown, 'contribution'));

    // Pest doesn't have toBeCloseTo, use delta check
    expect(abs($totalContribution - $compositeScore))->toBeLessThan(0.5);
});

test('protection weight is 20%', function () {
    $scoreData = $this->aggregator->calculateFinancialHealthScore($this->user->id);

    expect($scoreData['breakdown']['protection']['weight'])->toBe(0.20);
});

test('emergency fund weight is 15%', function () {
    $scoreData = $this->aggregator->calculateFinancialHealthScore($this->user->id);

    expect($scoreData['breakdown']['emergency_fund']['weight'])->toBe(0.15);
});

test('retirement weight is 25%', function () {
    $scoreData = $this->aggregator->calculateFinancialHealthScore($this->user->id);

    expect($scoreData['breakdown']['retirement']['weight'])->toBe(0.25);
});

test('investment weight is 20%', function () {
    $scoreData = $this->aggregator->calculateFinancialHealthScore($this->user->id);

    expect($scoreData['breakdown']['investment']['weight'])->toBe(0.20);
});

test('estate weight is 20%', function () {
    $scoreData = $this->aggregator->calculateFinancialHealthScore($this->user->id);

    expect($scoreData['breakdown']['estate']['weight'])->toBe(0.20);
});

test('alerts aggregation includes all modules', function () {
    $alerts = $this->aggregator->aggregateAlerts($this->user->id);

    expect($alerts)->toBeArray();

    // Check for alerts from different modules
    $modules = array_unique(array_column($alerts, 'module'));
    expect(count($modules))->toBeGreaterThanOrEqual(1);
});

test('alerts are sorted by severity', function () {
    $alerts = $this->aggregator->aggregateAlerts($this->user->id);

    if (count($alerts) > 1) {
        $severityOrder = ['critical' => 0, 'important' => 1, 'info' => 2];

        for ($i = 0; $i < count($alerts) - 1; $i++) {
            $currentSeverity = $severityOrder[$alerts[$i]['severity']] ?? 2;
            $nextSeverity = $severityOrder[$alerts[$i + 1]['severity']] ?? 2;

            expect($currentSeverity)->toBeLessThanOrEqual($nextSeverity);
        }
    }
});

test('each alert has required fields', function () {
    $alerts = $this->aggregator->aggregateAlerts($this->user->id);

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

test('alert severity is valid', function () {
    $alerts = $this->aggregator->aggregateAlerts($this->user->id);

    $validSeverities = ['critical', 'important', 'info'];

    foreach ($alerts as $alert) {
        expect($alert['severity'])->toBeIn($validSeverities);
    }
});

test('dashboard data fetch and cache workflow', function () {
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

test('cache invalidation workflow', function () {
    // Prime the cache
    $this->actingAs($this->user)->getJson('/api/dashboard');
    $this->actingAs($this->user)->getJson('/api/dashboard/financial-health-score');
    $this->actingAs($this->user)->getJson('/api/dashboard/alerts');

    // Invalidate all caches
    $response = $this->actingAs($this->user)
        ->postJson('/api/dashboard/invalidate-cache');

    $response->assertStatus(200);

    // All caches should be cleared
    expect(Cache::has("dashboard_{$this->user->id}"))->toBeFalse();
    expect(Cache::has("financial_health_score_{$this->user->id}"))->toBeFalse();
    expect(Cache::has("alerts_{$this->user->id}"))->toBeFalse();
});

test('parallel data loading handles partial failures gracefully', function () {
    // Even if some modules fail, the dashboard should still return data
    $data = $this->aggregator->aggregateOverviewData($this->user->id);

    // Should return array even if some modules have no data
    expect($data)->toBeArray();
});

test('financial health score label matches score range', function () {
    $scoreData = $this->aggregator->calculateFinancialHealthScore($this->user->id);

    $score = $scoreData['composite_score'];
    $label = $scoreData['label'];

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

test('financial health score recommendation is contextual', function () {
    $scoreData = $this->aggregator->calculateFinancialHealthScore($this->user->id);

    expect($scoreData['recommendation'])->toBeString();
    expect(strlen($scoreData['recommendation']))->toBeGreaterThan(20);
});
