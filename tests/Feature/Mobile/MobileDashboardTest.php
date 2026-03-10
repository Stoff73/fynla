<?php

declare(strict_types=1);

use App\Models\User;

describe('Mobile Dashboard API', function () {
    it('returns aggregated dashboard for authenticated user', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/mobile/dashboard');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'modules',
                    'net_worth' => ['total', 'breakdown'],
                    'alerts',
                    'fyn_insight',
                    'cached_at',
                ],
            ])
            ->assertJson(['success' => true]);
    });

    it('returns 401 for unauthenticated requests', function () {
        $this->getJson('/api/v1/mobile/dashboard')
            ->assertUnauthorized();
    });

    it('includes ETag header in response', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/mobile/dashboard');

        expect($response->headers->has('ETag'))->toBeTrue();
    });

    it('returns 304 for matching ETag', function () {
        $user = User::factory()->create();

        // First request to get ETag
        $response = $this->actingAs($user)->getJson('/api/v1/mobile/dashboard');
        $etag = $response->headers->get('ETag');

        // Second request with If-None-Match
        $this->actingAs($user)
            ->getJson('/api/v1/mobile/dashboard', ['If-None-Match' => $etag])
            ->assertStatus(304);
    });

    it('returns all 6 module summaries', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/mobile/dashboard');

        $modules = $response->json('data.modules');
        expect(array_keys($modules))->toContain('protection')
            ->toContain('savings')
            ->toContain('investment')
            ->toContain('retirement')
            ->toContain('estate')
            ->toContain('goals');
    });
});
