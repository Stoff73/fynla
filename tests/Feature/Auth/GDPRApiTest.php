<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserConsent;

beforeEach(function () {
    $this->user = User::factory()->create([
        'email' => 'gdpr-test@example.com',
        'password' => bcrypt('password123'),
        'is_preview_user' => true,
    ]);
});

describe('Consent Management', function () {
    it('returns user consents', function () {
        UserConsent::create([
            'user_id' => $this->user->id,
            'consent_type' => 'analytics',
            'granted' => true,
            'ip_address' => '127.0.0.1',
        ]);

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
                ],
            ]);
    });

    it('updates user consent', function () {
        $response = $this->actingAs($this->user)
            ->putJson('/api/auth/gdpr/consents', [
                'consent_type' => 'analytics',
                'granted' => true,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('user_consents', [
            'user_id' => $this->user->id,
            'consent_type' => 'analytics',
            'granted' => true,
        ]);
    });

    it('requires valid consent type', function () {
        $response = $this->actingAs($this->user)
            ->putJson('/api/auth/gdpr/consents', [
                'consent_type' => 'invalid_type',
                'granted' => true,
            ]);

        $response->assertStatus(422);
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
            ]);
    });

    it('returns export status', function () {
        $response = $this->actingAs($this->user)
            ->getJson('/api/auth/gdpr/export/status');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'exports',
                ],
            ]);
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
                'password' => 'password123',
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

    it('fails with invalid password', function () {
        $response = $this->actingAs($this->user)
            ->postJson('/api/auth/gdpr/erasure', [
                'password' => 'wrongpassword',
            ]);

        $response->assertStatus(422);
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
            'password' => 'password123',
        ]);

        $response->assertStatus(401);
    });
});

describe('Consent History', function () {
    it('returns consent history', function () {
        // Create some consent history
        UserConsent::create([
            'user_id' => $this->user->id,
            'consent_type' => 'analytics',
            'granted' => true,
            'ip_address' => '127.0.0.1',
        ]);

        UserConsent::create([
            'user_id' => $this->user->id,
            'consent_type' => 'analytics',
            'granted' => false,
            'ip_address' => '127.0.0.1',
        ]);

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
