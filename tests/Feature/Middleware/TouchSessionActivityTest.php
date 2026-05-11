<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserSession;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('refreshes last_activity_at on an authenticated API request', function () {
    $token = $this->user->createToken('test-token');
    $accessToken = $token->accessToken;

    $session = UserSession::createForToken($this->user, $accessToken);
    UserSession::where('id', $session->id)->update([
        'last_activity_at' => now()->subHours(2),
    ]);

    $before = UserSession::find($session->id)->last_activity_at;
    expect($before->diffInMinutes(now()))->toBeGreaterThan(60);

    $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
        ->getJson('/api/auth/user')
        ->assertOk();

    $after = UserSession::find($session->id)->last_activity_at;
    expect($after->diffInSeconds(now()))->toBeLessThan(5)
        ->and($after->greaterThan($before))->toBeTrue();
});

it('does not error on public API routes when no user is authenticated', function () {
    $response = $this->getJson('/api/preview/personas');

    // Middleware must not crash on unauthenticated public routes.
    expect($response->status())->toBeLessThan(500);
});

it('silently no-ops when current token has no matching user_sessions row', function () {
    Sanctum::actingAs($this->user);

    $response = $this->getJson('/api/auth/user');

    $response->assertOk();
    expect(UserSession::where('user_id', $this->user->id)->count())->toBe(0);
});
