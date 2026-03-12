<?php

declare(strict_types=1);

use App\Models\User;

describe('Share Content API', function () {
    it('returns share content for goal milestone', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/mobile/share/goal_milestone/1');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => ['title', 'text', 'url'],
            ])
            ->assertJson(['success' => true]);

        // Verify no PII in response
        $text = $response->json('data.text');
        expect($text)->not->toContain('£')
            ->and($text)->not->toMatch('/\d{4,}/'); // No large numbers
    });

    it('returns share content for app referral', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/mobile/share/app_referral');

        $response->assertOk()
            ->assertJson(['success' => true]);
    });

    it('rejects invalid share types', function () {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/v1/mobile/share/invalid_type')
            ->assertStatus(422);
    });

    it('returns 401 for unauthenticated requests', function () {
        $this->getJson('/api/v1/mobile/share/app_referral')
            ->assertUnauthorized();
    });

    it('never includes monetary values in share text', function () {
        $user = User::factory()->create();

        $types = ['goal_milestone', 'net_worth_milestone', 'fyn_insight', 'app_referral'];

        foreach ($types as $type) {
            $response = $this->actingAs($user)->getJson("/api/v1/mobile/share/{$type}");
            $text = $response->json('data.text');

            expect($text)->not->toContain('£')
                ->and($text)->not->toContain('$')
                ->and($text)->not->toMatch('/\b\d{3,}\b/'); // No 3+ digit numbers
        }
    });
});
