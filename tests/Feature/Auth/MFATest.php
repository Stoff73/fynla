<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->user = User::factory()->create([
        'email' => 'mfa-test@example.com',
        'password' => bcrypt('password123'),
        'is_preview_user' => true,
    ]);
});

describe('MFA Status', function () {
    it('returns MFA disabled status for user without MFA', function () {
        $response = $this->actingAs($this->user)
            ->getJson('/api/auth/mfa/status');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'mfa_enabled' => false,
                ],
            ]);
    });

    it('returns MFA enabled status for user with MFA', function () {
        $this->user->update([
            'mfa_enabled' => true,
            'mfa_secret' => 'TESTSECRET123456',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/auth/mfa/status');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'mfa_enabled' => true,
                ],
            ]);
    });

    it('requires authentication', function () {
        $response = $this->getJson('/api/auth/mfa/status');

        $response->assertStatus(401);
    });
});

describe('MFA Setup', function () {
    it('generates QR code and secret', function () {
        $response = $this->actingAs($this->user)
            ->postJson('/api/auth/mfa/setup');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'qr_code',
                    'secret',
                ],
            ]);

        expect($response->json('data.secret'))->not()->toBeNull();
        expect($response->json('data.qr_code'))->toContain('data:image');
    });

    it('requires authentication', function () {
        $response = $this->postJson('/api/auth/mfa/setup');

        $response->assertStatus(401);
    });
});

describe('MFA Disable', function () {
    it('disables MFA with valid password', function () {
        $this->user->update([
            'mfa_enabled' => true,
            'mfa_secret' => 'TESTSECRET123456',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/auth/mfa/disable', [
                'password' => 'password123',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->user->refresh();
        expect($this->user->mfa_enabled)->toBeFalse();
    });

    it('fails with invalid password', function () {
        $this->user->update([
            'mfa_enabled' => true,
            'mfa_secret' => 'TESTSECRET123456',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/auth/mfa/disable', [
                'password' => 'wrongpassword',
            ]);

        $response->assertStatus(422);
    });

    it('requires authentication', function () {
        $response = $this->postJson('/api/auth/mfa/disable', [
            'password' => 'password123',
        ]);

        $response->assertStatus(401);
    });
});

describe('MFA Challenge Token', function () {
    it('returns mfa_token when MFA is required during login', function () {
        $this->user->update([
            'mfa_enabled' => true,
            'mfa_secret' => 'TESTSECRET123456',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'mfa-test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'requires_mfa' => true,
            ])
            ->assertJsonStructure([
                'data' => [
                    'mfa_token',
                ],
            ]);

        expect($response->json('data.mfa_token'))->toHaveLength(64);
    });
});
