<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/*
 * Regression walk 2026-10-09, R17: the password step called Auth::attempt,
 * which signed the web session in before the emailed verification code (or
 * the authenticator) was checked. API requests from our own domain are
 * session-authenticated (Sanctum stateful), so a correct password alone
 * reached the account, and the session outlived sign-out: an invited partner
 * who then registered in the same browser was shown the inviter's account.
 */
it('signs nothing in when the password is right but the code is still to come', function () {
    User::factory()->create([
        'email' => 'awaiting-code@example.com',
        'password' => bcrypt('password123'),
        'is_preview_user' => false,
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email' => 'awaiting-code@example.com',
        'password' => 'password123',
    ]);

    $response->assertOk()->assertJsonPath('requires_verification', true);
    expect($response->json('data.access_token'))->toBeNull();
    expect(Auth::guard('web')->check())->toBeFalse();
});

it('still refuses a wrong password', function () {
    User::factory()->create([
        'email' => 'wrong-password@example.com',
        'password' => bcrypt('password123'),
        'is_preview_user' => false,
    ]);

    $this->postJson('/api/auth/login', [
        'email' => 'wrong-password@example.com',
        'password' => 'not-it',
    ])->assertStatus(401);

    expect(Auth::guard('web')->check())->toBeFalse();
});
