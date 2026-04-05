<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserConsent;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    // Fake mail to prevent view compilation during erasure verification flow
    Mail::fake();

    $this->user = User::factory()->create([
        'email' => 'gdpr-test@example.com',
        'password' => bcrypt('password123'),
        'is_preview_user' => false, // Regular user for GDPR tests
    ]);
});

describe('Consent Management', function () {
    it('returns user consents', function () {
        UserConsent::recordConsent($this->user->id, 'terms', true);

        $response = $this->actingAs($this->user)
            ->getJson('/api/auth/gdpr/consents');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'consents',
                    'needs_reconsent',
                ],
            ]);
    });

    it('updates user consent', function () {
        $response = $this->actingAs($this->user)
            ->putJson('/api/auth/gdpr/consents', [
                'consents' => [
                    'marketing' => true,
                ],
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    });

    it('requires consents array', function () {
        $response = $this->actingAs($this->user)
            ->putJson('/api/auth/gdpr/consents', [
                'consent_type' => 'marketing',
                'granted' => true,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['consents']);
    });

    it('requires authentication', function () {
        $response = $this->getJson('/api/auth/gdpr/consents');

        $response->assertStatus(401);
    });
});

describe('Data Export', function () {
    it('requests data export', function () {
        $response = $this->actingAs($this->user)
            ->postJson('/api/auth/gdpr/export', [
                'format' => 'json',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'export_id',
                    'status',
                    'format',
                ],
            ]);
    });

    it('returns export status after requesting export', function () {
        // First request an export
        $this->actingAs($this->user)
            ->postJson('/api/auth/gdpr/export', ['format' => 'json']);

        // Then check status
        $response = $this->actingAs($this->user)
            ->getJson('/api/auth/gdpr/export/status');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'export_id',
                    'status',
                ],
            ]);
    });

    it('returns 404 when no export exists', function () {
        $response = $this->actingAs($this->user)
            ->getJson('/api/auth/gdpr/export/status');

        $response->assertStatus(404);
    });

    it('requires authentication', function () {
        $response = $this->postJson('/api/auth/gdpr/export', [
            'format' => 'json',
        ]);

        $response->assertStatus(401);
    });
});

describe('Data Erasure', function () {
    it('requests account erasure', function () {
        $response = $this->actingAs($this->user)
            ->postJson('/api/auth/gdpr/erasure', [
                'confirm' => true,
                'reason' => 'Testing erasure request',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('erasure_requests', [
            'user_id' => $this->user->id,
            'status' => 'pending',
        ]);
    });

    it('requires confirm field', function () {
        $response = $this->actingAs($this->user)
            ->postJson('/api/auth/gdpr/erasure', [
                'reason' => 'Testing',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['confirm']);
    });

    it('prevents preview users from requesting erasure', function () {
        $previewUser = User::factory()->create([
            'is_preview_user' => true,
        ]);

        $response = $this->actingAs($previewUser)
            ->postJson('/api/auth/gdpr/erasure', [
                'confirm' => true,
            ]);

        $response->assertStatus(403);
    });

    it('returns erasure status', function () {
        $response = $this->actingAs($this->user)
            ->getJson('/api/auth/gdpr/erasure/status');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
            ]);
    });

    it('requires authentication', function () {
        $response = $this->postJson('/api/auth/gdpr/erasure', [
            'confirm' => true,
        ]);

        $response->assertStatus(401);
    });
});

describe('Consent History', function () {
    it('returns consent history', function () {
        // Create some consent history using the model method
        UserConsent::recordConsent($this->user->id, 'marketing', true);
        UserConsent::recordConsent($this->user->id, 'marketing', false);

        $response = $this->actingAs($this->user)
            ->getJson('/api/auth/gdpr/consents/history');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'history',
                ],
            ]);
    });

    it('requires authentication', function () {
        $response = $this->getJson('/api/auth/gdpr/consents/history');

        $response->assertStatus(401);
    });
});

describe('Immediate Self-Service Deletion', function () {
    it('initiates account deletion for user without 2FA', function () {
        $response = $this->actingAs($this->user)
            ->postJson('/api/auth/gdpr/erasure/initiate', [
                'type' => 'account',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'requires_2fa' => false,
                'requires_email_verification' => true,
            ])
            ->assertJsonStructure([
                'success',
                'requires_2fa',
                'requires_email_verification',
                'session_token',
            ]);

        // Verify session token is 64 characters
        $this->assertEquals(64, strlen($response->json('session_token')));
    });

    it('initiates data deletion for user without 2FA', function () {
        $response = $this->actingAs($this->user)
            ->postJson('/api/auth/gdpr/erasure/initiate', [
                'type' => 'data',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'requires_2fa' => false,
                'requires_email_verification' => true,
            ]);
    });

    it('initiates deletion for user with 2FA enabled', function () {
        // Enable 2FA for user
        $this->user->update([
            'mfa_enabled' => true,
            'mfa_secret' => encrypt('TESTSECRET12345678901234567890'),
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/auth/gdpr/erasure/initiate', [
                'type' => 'account',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'requires_2fa' => true,
                'requires_email_verification' => false,
            ]);
    });

    it('prevents preview users from initiating deletion', function () {
        $previewUser = User::factory()->create([
            'is_preview_user' => true,
        ]);

        $response = $this->actingAs($previewUser)
            ->postJson('/api/auth/gdpr/erasure/initiate', [
                'type' => 'account',
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Preview accounts cannot be deleted.',
            ]);
    });

    it('validates deletion type', function () {
        $response = $this->actingAs($this->user)
            ->postJson('/api/auth/gdpr/erasure/initiate', [
                'type' => 'invalid',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    });

    it('rejects verification with invalid session token', function () {
        $response = $this->actingAs($this->user)
            ->postJson('/api/auth/gdpr/erasure/verify', [
                'session_token' => str_repeat('x', 64),
                'code' => '123456',
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid or expired session. Please start again.',
            ]);
    });

    it('rejects verification with invalid code', function () {
        // First initiate deletion to get a valid session
        $initiateResponse = $this->actingAs($this->user)
            ->postJson('/api/auth/gdpr/erasure/initiate', [
                'type' => 'account',
            ]);

        $sessionToken = $initiateResponse->json('session_token');

        // Try to verify with wrong code
        $response = $this->actingAs($this->user)
            ->postJson('/api/auth/gdpr/erasure/verify', [
                'session_token' => $sessionToken,
                'code' => '000000', // Wrong code
            ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
            ]);
    });

    it('rejects execution with unverified session', function () {
        // Initiate deletion
        $initiateResponse = $this->actingAs($this->user)
            ->postJson('/api/auth/gdpr/erasure/initiate', [
                'type' => 'account',
            ]);

        $sessionToken = $initiateResponse->json('session_token');

        // Try to execute without verifying
        $response = $this->actingAs($this->user)
            ->postJson('/api/auth/gdpr/erasure/execute', [
                'session_token' => $sessionToken,
                'confirmation' => 'Delete my Account',
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Identity not verified. Please complete verification first.',
            ]);
    });

    it('rejects execution with wrong confirmation phrase', function () {
        // For this test, we need to manually set up a verified session in cache
        $sessionToken = str_repeat('a', 64);
        \Illuminate\Support\Facades\Cache::put("deletion_session:{$this->user->id}", [
            'token' => $sessionToken,
            'type' => 'account',
            'verified' => true,
            'verified_at' => now()->timestamp,
            'attempts' => 0,
        ], now()->addMinutes(15));

        $response = $this->actingAs($this->user)
            ->postJson('/api/auth/gdpr/erasure/execute', [
                'session_token' => $sessionToken,
                'confirmation' => 'wrong phrase',
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Please type exactly: "Delete my Account"',
            ]);
    });

    it('validates confirmation phrase is case-sensitive', function () {
        $sessionToken = str_repeat('b', 64);
        \Illuminate\Support\Facades\Cache::put("deletion_session:{$this->user->id}", [
            'token' => $sessionToken,
            'type' => 'account',
            'verified' => true,
            'verified_at' => now()->timestamp,
            'attempts' => 0,
        ], now()->addMinutes(15));

        // Try lowercase
        $response = $this->actingAs($this->user)
            ->postJson('/api/auth/gdpr/erasure/execute', [
                'session_token' => $sessionToken,
                'confirmation' => 'delete my account', // lowercase
            ]);

        $response->assertStatus(400);
    });

    it('requires authentication for initiate', function () {
        $response = $this->postJson('/api/auth/gdpr/erasure/initiate', [
            'type' => 'account',
        ]);

        $response->assertStatus(401);
    });

    it('requires authentication for verify', function () {
        $response = $this->postJson('/api/auth/gdpr/erasure/verify', [
            'session_token' => str_repeat('x', 64),
            'code' => '123456',
        ]);

        $response->assertStatus(401);
    });

    it('requires authentication for execute', function () {
        $response = $this->postJson('/api/auth/gdpr/erasure/execute', [
            'session_token' => str_repeat('x', 64),
            'confirmation' => 'Delete my Account',
        ]);

        $response->assertStatus(401);
    });

    it('allows resending code for email verification', function () {
        // Initiate deletion
        $initiateResponse = $this->actingAs($this->user)
            ->postJson('/api/auth/gdpr/erasure/initiate', [
                'type' => 'account',
            ]);

        $sessionToken = $initiateResponse->json('session_token');

        // Resend code
        $response = $this->actingAs($this->user)
            ->postJson('/api/auth/gdpr/erasure/resend-code', [
                'session_token' => $sessionToken,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Verification code sent to your email.',
            ]);
    });

    it('rejects resend for user with 2FA', function () {
        // Enable 2FA
        $this->user->update([
            'mfa_enabled' => true,
            'mfa_secret' => encrypt('TESTSECRET12345678901234567890'),
        ]);

        // Initiate deletion
        $initiateResponse = $this->actingAs($this->user)
            ->postJson('/api/auth/gdpr/erasure/initiate', [
                'type' => 'account',
            ]);

        $sessionToken = $initiateResponse->json('session_token');

        // Try to resend code
        $response = $this->actingAs($this->user)
            ->postJson('/api/auth/gdpr/erasure/resend-code', [
                'session_token' => $sessionToken,
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Use your authenticator app for verification.',
            ]);
    });
});
