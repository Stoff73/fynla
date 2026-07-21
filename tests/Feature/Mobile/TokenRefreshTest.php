<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\TransientToken;

describe('Token Refresh API', function () {
    it('issues a new token and revokes the old one', function () {
        $user = User::factory()->create();
        $token = $user->createToken('test-token');
        $plainToken = $token->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$plainToken)
            ->postJson('/api/v1/auth/refresh-token');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => ['token', 'expires_at', 'token_age_days'],
            ])
            ->assertJson(['success' => true]);

        // Old token should be revoked
        expect(PersonalAccessToken::findToken($plainToken))->toBeNull();

        // New token should work
        $newToken = $response->json('data.token');
        $this->withHeader('Authorization', 'Bearer '.$newToken)
            ->getJson('/api/v1/health')
            ->assertOk();
    });

    it('returns 401 for unauthenticated requests', function () {
        $this->postJson('/api/v1/auth/refresh-token')
            ->assertUnauthorized();
    });

    it('returns new token data with correct structure', function () {
        $user = User::factory()->create();
        $token = $user->createToken('test-token');
        $plainToken = $token->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$plainToken)
            ->postJson('/api/v1/auth/refresh-token');

        $response->assertOk();
        $data = $response->json('data');

        expect($data['token'])->toBeString()->not->toBeEmpty()
            ->and($data['expires_at'])->toBeString()
            ->and($data['token_age_days'])->toBe(0);
    });

    describe('TransientToken (stateful SPA auth) handling', function () {
        it('fails closed with 400 when current token is a TransientToken', function () {
            // Sanctum::actingAs() mocks a PersonalAccessToken — wrong shape for
            // this test. Drive the controller directly with a hand-built request
            // whose user has a TransientToken attached.
            $user = User::factory()->create();
            $user->withAccessToken(new TransientToken);

            $controller = app(\App\Http\Controllers\Api\V1\Auth\TokenRefreshController::class);
            $request = \Illuminate\Http\Request::create('/api/v1/auth/refresh-token', 'POST');
            $request->setUserResolver(fn () => $user);

            $response = $controller->refresh($request);

            expect($response->getStatusCode())->toBe(400);
            $body = json_decode($response->getContent(), true);
            expect($body['success'])->toBeFalse()
                ->and($body['message'])->toBe('Token refresh requires Bearer authentication. Cookie-based session authentication is not supported on this endpoint.');
        });
    });
});
