<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\User;

beforeEach(function () {
    $mock = Mockery::mock(CoordinatingAgent::class);
    $mock->shouldReceive('analyze')->andReturn([
        'modules' => [
            'savings' => [
                'emergency_fund' => [
                    'runway_months' => 3.5,
                ],
                'isa_allowance' => [
                    'remaining' => 12000.00,
                ],
            ],
            'protection' => [
                'gaps' => ['life_insurance'],
            ],
        ],
    ]);
    $this->app->instance(CoordinatingAgent::class, $mock);
});

afterEach(function () {
    Mockery::close();
});

describe('Daily Insights API', function () {
    it('returns daily insight for authenticated user', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/mobile/insights/daily');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'insight',
                    'category',
                    'cached_at',
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);

        $data = $response->json('data');
        expect($data['insight'])->toBeString()->not->toBeEmpty();
        expect($data['category'])->toBeIn([
            'savings', 'protection', 'investment', 'retirement', 'estate', 'goals', 'tax',
        ]);
    });

    it('returns 401 for unauthenticated requests', function () {
        $this->getJson('/api/v1/mobile/insights/daily')
            ->assertUnauthorized();
    });

    it('includes ETag header in response', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/mobile/insights/daily');

        $response->assertOk();
        expect($response->headers->has('ETag'))->toBeTrue();
    });

    it('returns fallback insight when analysis fails', function () {
        $failingMock = Mockery::mock(CoordinatingAgent::class);
        $failingMock->shouldReceive('analyze')->andThrow(new \RuntimeException('Analysis failed'));
        $this->app->instance(CoordinatingAgent::class, $failingMock);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/mobile/insights/daily');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'insight',
                    'category',
                    'cached_at',
                ],
            ]);

        $data = $response->json('data');
        expect($data['insight'])->toBeString()->not->toBeEmpty();
    });

    it('returns 304 when ETag matches If-None-Match header', function () {
        $user = User::factory()->create();

        // First request to get ETag
        $response = $this->actingAs($user)->getJson('/api/v1/mobile/insights/daily');
        $response->assertOk();
        $etag = $response->headers->get('ETag');

        expect($etag)->not->toBeNull();

        // Second request with matching If-None-Match
        $response = $this->actingAs($user)
            ->withHeader('If-None-Match', $etag)
            ->getJson('/api/v1/mobile/insights/daily');

        $response->assertStatus(304);
    });
});
