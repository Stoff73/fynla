<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserConsent;

beforeEach(function () {
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
