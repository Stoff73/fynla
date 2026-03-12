<?php

declare(strict_types=1);

use App\Agents\EstateAgent;
use App\Agents\GoalsAgent;
use App\Agents\InvestmentAgent;
use App\Agents\ProtectionAgent;
use App\Agents\RetirementAgent;
use App\Agents\SavingsAgent;
use App\Agents\TaxOptimisationAgent;
use App\Models\User;

beforeEach(function () {
    // Mock all agents to avoid complex dependency chains
    $agentClasses = [
        ProtectionAgent::class,
        SavingsAgent::class,
        InvestmentAgent::class,
        RetirementAgent::class,
        EstateAgent::class,
        GoalsAgent::class,
        TaxOptimisationAgent::class,
    ];

    foreach ($agentClasses as $agentClass) {
        $mock = Mockery::mock($agentClass);
        $mock->shouldReceive('analyze')->andReturn([
            'summary' => ['total_value' => 10000.00],
            'message' => 'Analysis complete',
        ]);
        $this->app->instance($agentClass, $mock);
    }
});

afterEach(function () {
    Mockery::close();
});

describe('Module Summary API', function () {
    it('returns module summary for authenticated user', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/mobile/modules/savings');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'module',
                    'summary',
                    'cached_at',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'module' => 'savings',
                ],
            ]);
    });

    it('returns 401 for unauthenticated requests', function () {
        $this->getJson('/api/v1/mobile/modules/savings')
            ->assertUnauthorized();
    });

    it('returns 404 for invalid module name', function () {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/v1/mobile/modules/invalid')
            ->assertNotFound();
    });

    it('returns data for all 7 valid modules', function () {
        $user = User::factory()->create();
        $modules = ['protection', 'savings', 'investment', 'retirement', 'estate', 'goals', 'tax'];

        foreach ($modules as $module) {
            $response = $this->actingAs($user)->getJson("/api/v1/mobile/modules/{$module}");

            $response->assertOk()
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'module' => $module,
                    ],
                ]);
        }
    });

    it('includes ETag header in response', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/mobile/modules/savings');

        $response->assertOk();
        expect($response->headers->has('ETag'))->toBeTrue();
    });

    it('strips score-related keys from response', function () {
        $savingsMock = Mockery::mock(SavingsAgent::class);
        $savingsMock->shouldReceive('analyze')->andReturn([
            'summary' => ['total_savings' => 25000.00],
            'emergency_fund' => [
                'runway_months' => 4.2,
                'adequacy_score' => 70,
                'category' => 'moderate',
            ],
            'diversification_score' => 85,
        ]);
        $this->app->instance(SavingsAgent::class, $savingsMock);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/mobile/modules/savings');

        $response->assertOk();

        $data = $response->json('data.summary');

        // Score keys should be stripped
        expect($data)->not->toHaveKey('diversification_score');
        expect($data['emergency_fund'] ?? [])->not->toHaveKey('adequacy_score');

        // Non-score keys should remain
        expect($data['summary'])->toHaveKey('total_savings');
        expect($data['emergency_fund'] ?? [])->toHaveKey('runway_months');
    });

    it('returns 304 when ETag matches If-None-Match header', function () {
        $user = User::factory()->create();

        // First request to get ETag
        $response = $this->actingAs($user)->getJson('/api/v1/mobile/modules/savings');
        $response->assertOk();
        $etag = $response->headers->get('ETag');

        expect($etag)->not->toBeNull();

        // Second request with matching If-None-Match
        $response = $this->actingAs($user)
            ->withHeader('If-None-Match', $etag)
            ->getJson('/api/v1/mobile/modules/savings');

        $response->assertStatus(304);
    });
});
